<?php

namespace Tests\Concurrency;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Inventory\DeadlockInjector;

/**
 * T-5: the service-level retry (Idempotency::once, three attempts) on REAL commits. The suite-wrapped tests cannot show it, because Laravel only
 * retries a transaction at the outermost level. A deadlock is injected after the goods-receipt rows were written.
 */
class CommittedDeadlockRetryTest extends VerificationCase
{
    private function supplier(): int
    {
        return (int) DB::table('suppliers')->insertGetId(['slug' => 'cd-' . uniqid(), 'business_name' => 'CD', 'contact_person_name' => 'C', 'mobile' => '017' . random_int(10000000, 99999999), 'opening_balance' => '0', 'due_amount' => 0, 'paid_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function data(int $supplier, int $product): array
    {
        return ['supplier_id' => $supplier, 'lines' => [['product_id' => $product, 'variant' => 'M', 'qty' => 5, 'unit_cost' => '90']]];
    }

    private function product(): int
    {
        $p = $this->seedProduct(['M' => 0], 1000, 400, []);
        DB::table('products')->where('id', $p)->update(['stock_policy' => 'backorder']);
        DB::table('purchase_products')->where('product_id', $p)->delete();

        return $p;
    }

    public function test_goods_receipt_retries_a_deadlock_and_exists_exactly_once()
    {
        $p = $this->product();
        $s = $this->supplier();
        $key = 'cd-grn-' . uniqid();
        $inj = DeadlockInjector::once('/^insert into `?goods_receipt_lines`?/i');

        $receipt = app(\App\Services\Purchasing\GoodsReceiptService::class)->receive($key, $this->data($s, $p));

        $this->assertSame(1, $inj->thrown);
        $this->assertNotEmpty($receipt);
        $this->assertSame(1, DB::table('goods_receipts')->where('idempotency_key', $key)->count());
        $this->assertSame(5, (int) DB::table('product_stocks')->where('product_id', $p)->sum('qty'));
        $this->assertSame(5, (int) DB::table('purchase_products')->where('product_id', $p)->sum('remaining_qty'), 'one FIFO layer');
        $this->assertSame(1, DB::table('purchase_products')->where('product_id', $p)->count());
        $this->assertSame(1, DB::table('journal_entries')->where('idempotency_key', 'grn:' . $key)->count());
        // replaying the same key afterwards changes nothing
        app(\App\Services\Purchasing\GoodsReceiptService::class)->receive($key, $this->data($s, $p));
        $this->assertSame(5, (int) DB::table('product_stocks')->where('product_id', $p)->sum('qty'));
    }

    public function test_goods_receipt_gives_up_after_three_attempts_and_leaves_nothing()
    {
        $p = $this->product();
        $s = $this->supplier();
        $key = 'cd-grn-' . uniqid();
        $before = ['balances' => DB::table('chart_of_accounts')->orderBy('id')->pluck('current_balance', 'id')->all(), 'due' => (float) DB::table('suppliers')->sum('due_amount')];
        $inj = DeadlockInjector::always('/^insert into `?goods_receipt_lines`?/i');

        try {
            app(\App\Services\Purchasing\GoodsReceiptService::class)->receive($key, $this->data($s, $p));
            $this->fail('expected the deadlock to surface after the last attempt');
        } catch (\Throwable $e) {
            $this->assertTrue(\App\Support\DeadlockRetry::isDeadlock($e));
        }

        $this->assertSame(3, $inj->thrown, 'exactly three attempts');
        $this->assertSame(0, DB::table('goods_receipts')->where('idempotency_key', $key)->count());
        $this->assertSame(0, (int) DB::table('product_stocks')->where('product_id', $p)->sum('qty'));
        $this->assertSame(0, DB::table('purchase_products')->where('product_id', $p)->count());
        $this->assertSame(0, DB::table('journal_entries')->where('idempotency_key', 'grn:' . $key)->count());
        $this->assertSame(0, DB::table('idempotency_keys')->where('key', $key)->count(), 'the key was rolled back too, so a later retry by the user can still run');
        $this->assertSame($before, ['balances' => DB::table('chart_of_accounts')->orderBy('id')->pluck('current_balance', 'id')->all(), 'due' => (float) DB::table('suppliers')->sum('due_amount')]);
    }
}
