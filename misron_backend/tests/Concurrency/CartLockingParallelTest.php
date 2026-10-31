<?php

namespace Tests\Concurrency;

use Illuminate\Support\Facades\DB;

/**
 * T-5 Phase A: checkout locking is row-level. Unrelated customers never wait for each other; one submission is one order; a different
 * submission (new cart rows, changed details) is never answered with an earlier order; lock timeouts and vanished carts leave nothing behind.
 * Real processes through the HTTP kernel against the disposable database; legacy stock model (flag off).
 */
class CartLockingParallelTest extends HttpWorkflowCase
{
    private function cart(int $admin, int $product, int $qty): array
    {
        DB::table('carts')->insert(['user_id' => $admin, 'product_id' => $product, 'variation' => 'M', 'quantity' => $qty, 'price' => 1000, 'tax' => 0, 'shipping_cost' => 0, 'discount' => 0, 'shipping_type' => 'home_delivery', 'created_at' => now(), 'updated_at' => now()]);
        $address = (int) DB::table('addresses')->insertGetId(['user_id' => $admin, 'address' => 'H', 'phone' => '01700000001', 'postal_code' => '1200', 'country_id' => 18, 'state_id' => 0, 'city_id' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return [(int) DB::table('carts')->where('user_id', $admin)->value('id'), $address];
    }

    private function place(int $admin, int $address, array $extra = []): array
    {
        return $this->req($admin, '/admin/orders', ['address_id' => $address, 'payment_option' => 'cash_on_delivery', 'shipping_cost_amount' => 0]) + $extra;
    }

    private const LOCK_CARTS = '/from `carts` where `id` in .* for update/i';

    public function test_two_customers_check_out_at_the_same_time_and_neither_waits_for_the_other()
    {
        $this->flag(false);
        $p = $this->seedProduct(['M' => 10], 1000, 400);
        $a = $this->admin();
        $b = $this->admin();
        [, $addrA] = $this->cart($a, $p, 2);
        [, $addrB] = $this->cart($b, $p, 3);
        // A holds its cart-row lock for 2.5 s (paused right after taking it). B starts a little later and must not be delayed.
        $r = $this->race([$this->place($a, $addrA, ['pause_after' => ['regex' => self::LOCK_CARTS, 'ms' => 2500]]), $this->place($b, $addrB, ['delay_ms' => 300])], 120);

        $this->assertSame(1, DB::table('orders')->where('user_id', $a)->count(), json_encode($r));
        $this->assertSame(1, DB::table('orders')->where('user_id', $b)->count(), json_encode($r));
        $durationA = $r[0]['finished'] - $r[0]['started'];
        $durationB = $r[1]['finished'] - $r[1]['started'];
        $this->assertGreaterThan(2.4, $durationA, 'A really held its lock');
        $this->assertLessThan(1.5, $durationB, "B was not blocked by A's cart lock (took {$durationB}s, A took {$durationA}s)");
        $this->assertSame(5, (int) DB::table('product_stocks')->where('product_id', $p)->value('qty'), '10 - 2 - 3');
        $this->assertSame(5, (int) DB::table('purchase_products')->where('product_id', $p)->sum('remaining_qty'));
    }

    public function test_the_same_submission_four_times_is_one_order_and_a_changed_submission_is_a_new_order()
    {
        $this->flag(false);
        $p = $this->seedProduct(['M' => 10], 1000, 400);
        $a = $this->admin();
        [, $addr1] = $this->cart($a, $p, 2);
        $addr2 = (int) DB::table('addresses')->insertGetId(['user_id' => $a, 'address' => 'Other', 'phone' => '01700000002', 'postal_code' => '1200', 'country_id' => 18, 'state_id' => 0, 'city_id' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $this->race(array_fill(0, 4, $this->place($a, $addr1)), 120);
        $this->assertSame(1, DB::table('orders')->where('user_id', $a)->count(), 'four identical submissions of one cart: one order');
        $this->assertSame(8, (int) DB::table('product_stocks')->where('product_id', $p)->value('qty'));

        // same cart rows, different delivery address: a different submission, never answered with the earlier order
        $this->runOne($this->place($a, $addr2));
        $this->assertSame(2, DB::table('orders')->where('user_id', $a)->count(), 'changed address = a new order');
        $this->assertSame(6, (int) DB::table('product_stocks')->where('product_id', $p)->value('qty'));
        $this->assertSame(6, (int) DB::table('purchase_products')->where('product_id', $p)->sum('remaining_qty'));

        // identical products added again (new cart rows) = a new purchase
        DB::table('carts')->where('user_id', $a)->delete();
        $this->cart($a, $p, 2);
        $this->runOne($this->place($a, $addr1));
        $this->assertSame(3, DB::table('orders')->where('user_id', $a)->count(), 'new cart rows with identical products = a new order');
        $this->assertSame(4, (int) DB::table('product_stocks')->where('product_id', $p)->value('qty'));
    }

    public function test_a_cart_emptied_by_another_tab_during_checkout_creates_no_order_and_takes_no_stock()
    {
        $this->flag(false);
        $p = $this->seedProduct(['M' => 10], 1000, 400);
        $a = $this->admin();
        [, $addr] = $this->cart($a, $p, 2);
        // A reads its cart, pauses 1.5 s; meanwhile another process deletes the cart rows; A then finds them gone.
        $r = $this->race([$this->place($a, $addr, ['pause_after' => ['regex' => '/^select \* from `carts` where `user_id` = \?/i', 'ms' => 1500]]), ['action' => 'delete_carts', 'user_id' => $a, 'delay_ms' => 400]], 120);

        $this->assertSame(0, DB::table('orders')->where('user_id', $a)->count(), json_encode($r));
        $this->assertSame(10, (int) DB::table('product_stocks')->where('product_id', $p)->value('qty'));
        $this->assertSame(10, (int) DB::table('purchase_products')->where('product_id', $p)->sum('remaining_qty'));
        $this->assertSame(0, DB::table('idempotency_keys')->where('scope', 'checkout_cart')->count(), 'no identity key left behind');
    }

    public function test_a_request_that_cannot_get_the_cart_lock_in_time_fails_cleanly_and_the_first_order_is_untouched()
    {
        $this->flag(false);
        $p = $this->seedProduct(['M' => 10], 1000, 400);
        $a = $this->admin();
        [, $addr] = $this->cart($a, $p, 2);
        $r = $this->race([$this->place($a, $addr, ['pause_after' => ['regex' => self::LOCK_CARTS, 'ms' => 3000]]), $this->place($a, $addr, ['delay_ms' => 500, 'lock_wait' => 1])], 120);

        $this->assertSame(1, DB::table('orders')->where('user_id', $a)->count(), 'one order only: ' . json_encode($r));
        $this->assertSame(8, (int) DB::table('product_stocks')->where('product_id', $p)->value('qty'), 'stock taken once');
        $this->assertSame(8, (int) DB::table('purchase_products')->where('product_id', $p)->sum('remaining_qty'));
        $this->assertSame(1, DB::table('idempotency_keys')->where('scope', 'checkout_cart')->count());
    }
}
