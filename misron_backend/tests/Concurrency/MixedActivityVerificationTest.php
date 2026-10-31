<?php

namespace Tests\Concurrency;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Services\Accounting\JournalService;
use App\Services\Inventory\InventoryReconciliation;
use App\Services\Inventory\StockBalance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** D. Realistic simultaneous activity with the flag on: storefront orders, confirmations, purchases received, old-screen postings, outbox workers. */
class MixedActivityVerificationTest extends VerificationCase
{
    private function pendingOrder(int $productId, int $qty): int
    {
        $u = DB::table('users')->insertGetId(['name' => 'c', 'email' => Str::random(8) . '@c.test', 'password' => 'x', 'user_type' => 'customer', 'created_at' => now(), 'updated_at' => now()]);
        $o = new Order();
        $o->forceFill(['user_id' => $u, 'seller_id' => 1, 'code' => Order::generateUniqueCode(), 'delivery_status' => 'pending', 'payment_status' => 'unpaid', 'payment_type' => 'cash_on_delivery', 'shipping_address' => json_encode(['name' => 'x']), 'grand_total' => 0, 'date' => time(), 'order_from' => 'app'])->save();
        (new OrderDetail())->forceFill(['order_id' => $o->id, 'seller_id' => 1, 'product_id' => $productId, 'variation' => 'M', 'quantity' => $qty, 'price' => 1000 * $qty, 'selling_price' => 1000, 'tax' => 0, 'shipping_cost' => 0, 'buying_price' => 0, 'profit' => 0, 'delivery_status' => 'pending'])->save();

        return $o->id;
    }

    public function test_orders_confirmations_receipts_old_screen_postings_and_outbox_workers_at_once_keep_every_number_consistent()
    {
        $supplier = DB::table('suppliers')->insertGetId(['slug' => 'mx-' . Str::random(5), 'business_name' => 'Mixed', 'contact_person_name' => 'M', 'mobile' => '01711111111', 'opening_balance' => '0', 'due_amount' => 0, 'paid_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $backorder = $this->seedProduct(['M' => 0], 1000, 400, []);
        DB::table('products')->where('id', $backorder)->update(['stock_policy' => 'backorder']);
        DB::table('purchase_products')->where('product_id', $backorder)->delete();
        $orders = array_map(fn () => $this->pendingOrder($backorder, 2), range(1, 4));      // 8 units of confirmed demand
        $stocked = $this->seedProduct(['M' => 20], 1000, 300, ['M' => [20]]);               // sold through the storefront (pending: nothing is taken)
        foreach (range(1, 6) as $n) {
            DB::table('carts')->insert(['temp_user_id' => 'mx' . $n, 'product_id' => $stocked, 'variation' => 'M', 'quantity' => 1, 'price' => 1000, 'tax' => 0, 'shipping_cost' => 0, 'discount' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
        $from = $this->account('asset', 'cash', 5000);
        $to = $this->account('expense', 'expense', 0);

        $jobs = [];
        foreach ($orders as $oid) {
            $jobs[] = ['action' => 'v2_status', 'order_id' => $oid, 'status' => 'confirmed'];
        }
        foreach (range(1, 3) as $i) {
            $jobs[] = ['action' => 'v2_receive', 'key' => "mx-grn-{$backorder}-{$i}", 'data' => ['supplier_id' => $supplier, 'lines' => [['product_id' => $backorder, 'variant' => 'M', 'qty' => 3, 'unit_cost' => '90']]]];
        }
        foreach (range(1, 6) as $n) {
            $jobs[] = ['action' => 'http', 'uri' => '/api/v2/guest/order/store', 'body' => ['temp_user_id' => 'mx' . $n, 'payment_type' => 'cash_on_delivery', 'delivery_type' => 'home_delivery',
                'guest_info' => ['name' => 'Mx ' . $n, 'phone' => '0171' . random_int(1000000, 9999999), 'address' => 'House 1, Road 2', 'city_name' => 'Dhaka']]];
        }
        foreach (range(1, 5) as $n) {
            $jobs[] = ['action' => 'legacy_post', 'from' => $from, 'to' => $to, 'amount' => 10];
        }
        $jobs[] = ['action' => 'outbox_process'];
        $jobs[] = ['action' => 'outbox_process'];
        shuffle($jobs);
        $results = $this->race($jobs, 150);

        foreach ($results as $i => $r) {
            $job = $jobs[$i];
            if ($job['action'] === 'http') {
                $this->assertTrue(!empty($r['body']['result']), 'storefront order lost: ' . json_encode($r));
            } else {
                $this->assertTrue(!empty($r['ok']), "{$job['action']} failed: " . json_encode($r));
            }
        }
        $final = $this->runOne(['action' => 'outbox_process']); // anything still queued is completed
        $this->assertTrue(!empty($final['ok']));

        $s = StockBalance::snapshot($backorder, 'M');
        $this->assertSame([9, 8, 8, 1], [$s['on_hand'], $s['demand'], $s['allocated'], $s['available']], 'physical 9 received, 8 demanded, all 8 allocated exactly once');
        $this->assertSame(3, DB::table('goods_receipts')->where('idempotency_key', 'like', "mx-grn-{$backorder}-%")->count());
        $sold = StockBalance::snapshot($stocked, 'M');
        $this->assertSame([20, 0], [$sold['on_hand'], $sold['demand']], 'pending storefront orders took nothing');
        $this->assertSame(6, DB::table('order_details')->where('product_id', $stocked)->count(), 'all six storefront orders exist');
        $this->assertEquals(4950.0, (float) DB::table('chart_of_accounts')->where('id', $from)->value('current_balance'), 'five postings of 10 left the cash account once each');
        $this->assertSame(5, DB::table('ledger_outbox')->where('account_id', $from)->count());
        $this->assertSame(5, DB::table('journal_lines')->where('account_id', $from)->count(), 'each old-screen posting has its journal line, exactly once');
        $this->assertSame(0, DB::table('ledger_outbox')->whereNull('processed_at')->count(), 'nothing is left waiting');
        $this->assertTrue(JournalService::trialBalance(now()->toDateString())['balanced']);
        $this->assertSame([], app(InventoryReconciliation::class)->stockInvariantViolations());
        $critical = collect(app(InventoryReconciliation::class)->report())->filter(fn ($r) => $r['severity'] === 'critical' && !in_array($r['label'], [], true));
        $this->assertCount(0, $critical, 'no critical reconciliation finding: ' . $critical->pluck('label')->implode('; '));
    }
}
