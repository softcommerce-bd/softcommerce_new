<?php

namespace Tests\Concurrency;

use Illuminate\Support\Facades\DB;

/** T-5: real parallel admin create-order requests (legacy model: stock and FIFO layers are taken when the order is created). */
class AdminOrderParallelTest extends HttpWorkflowCase
{
    private function cartAndAddress(int $admin, int $product, int $qty): int
    {
        DB::table('carts')->insert(['user_id' => $admin, 'product_id' => $product, 'variation' => 'M', 'quantity' => $qty, 'price' => 1000, 'tax' => 0, 'shipping_cost' => 0, 'discount' => 0, 'shipping_type' => 'home_delivery', 'created_at' => now(), 'updated_at' => now()]);

        return (int) DB::table('addresses')->insertGetId(['user_id' => $admin, 'address' => 'House 1', 'phone' => '01700000001', 'postal_code' => '1200', 'country_id' => 18, 'state_id' => 0, 'city_id' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function place(int $admin, int $address): array
    {
        return $this->req($admin, '/admin/orders', ['address_id' => $address, 'payment_option' => 'cash_on_delivery', 'shipping_cost_amount' => 0]);
    }

    public function test_one_admin_double_submitting_the_same_cart_creates_one_order_and_takes_stock_once()
    {
        $this->flag(false);
        $p = $this->seedProduct(['M' => 10], 1000, 400);
        $admin = $this->admin();
        $address = $this->cartAndAddress($admin, $p, 2);

        $r = $this->race(array_fill(0, 4, $this->place($admin, $address)), 120);

        $orders = DB::table('orders')->where('user_id', $admin)->count();
        $this->assertSame(1, $orders, 'a double click is one order: ' . json_encode($r));
        $this->assertSame(8, (int) DB::table('product_stocks')->where('product_id', $p)->value('qty'), 'two units taken once');
        $this->assertSame(8, (int) DB::table('purchase_products')->where('product_id', $p)->sum('remaining_qty'), 'FIFO layers fell by the same two units');
        $this->assertSame(1, DB::table('order_details')->where('product_id', $p)->count());
        $this->assertSame(1, DB::table('combined_orders')->where('user_id', $admin)->count(), 'one combined order');
    }

    public function test_different_admins_racing_for_the_last_units_never_oversell_and_cost_follows_fifo()
    {
        $this->flag(false);
        $p = $this->seedProduct(['M' => 6], 1000, 400, ['M' => [2, 4]]); // two layers: 2 @ 400, 4 @ 410
        $jobs = [];
        for ($i = 0; $i < 5; $i++) {
            $admin = $this->admin();
            $jobs[] = $this->place($admin, $this->cartAndAddress($admin, $p, 2));
        }

        $r = $this->race($jobs, 120);

        $this->assertSame(3, DB::table('order_details')->where('product_id', $p)->count(), 'six units, two each: three lines: ' . json_encode($r));
        $this->assertSame(6, (int) DB::table('order_details')->where('product_id', $p)->sum('quantity'));
        $this->assertSame(0, (int) DB::table('product_stocks')->where('product_id', $p)->value('qty'));
        $this->assertSame(0, (int) DB::table('purchase_products')->where('product_id', $p)->sum('remaining_qty'));
        $cost = DB::table('order_details')->where('product_id', $p)->get()->sum(fn ($d) => $d->buying_price * $d->quantity);
        $this->assertEquals(2 * 400 + 4 * 410, $cost, 'the cost booked on the lines is exactly the cost of the layers consumed, oldest first');
        $this->assertSame(3, DB::table('orders')->whereIn('id', DB::table('order_details')->where('product_id', $p)->pluck('order_id'))->count());
    }
}
