<?php

namespace Tests\Concurrency;

use Illuminate\Support\Facades\DB;

/** T-5: real parallel legacy purchases, supplier payments and goods receipts. */
class PurchasingParallelTest extends HttpWorkflowCase
{
    private int $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->supplier = (int) DB::table('suppliers')->insertGetId(['slug' => 'pp-' . uniqid(), 'business_name' => 'PP', 'contact_person_name' => 'C', 'mobile' => '017' . random_int(10000000, 99999999), 'opening_balance' => '0', 'due_amount' => 0, 'paid_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function purchase(int $admin, int $product, int $qty, float $cost, string $key, array $extra = []): array
    {
        return $this->req($admin, '/purchase/store', array_merge(['op_key' => $key, 'supplier_id' => $this->supplier, 'purchase_date' => now()->toDateString(), 'product_id' => [$product], 'name' => ['x'], 'variant' => ['M'],
            'purchase_quantity' => [$qty], 'buying_unit_cost' => [$cost], 'total_cost' => [$qty * $cost]], $extra));
    }

    private function product(int $stock = 0): int
    {
        return $stock > 0 ? $this->seedProduct(['M' => $stock], 1000, 100) : tap($this->seedProduct(['M' => 0], 1000, 100), function ($p) {
            DB::table('purchase_products')->where('product_id', $p)->delete();
        });
    }

    private function due(): float
    {
        return (float) DB::table('suppliers')->where('id', $this->supplier)->value('due_amount');
    }

    public function test_the_same_purchase_submitted_four_times_at_once_exists_once_everywhere()
    {
        $this->flag(true);
        $p = $this->product();
        $cash = $this->cashAccount(1000);
        $admin = $this->admin();
        $key = 'par-' . uniqid();
        $job = $this->purchase($admin, $p, 5, 20, $key, ['pay_by' => 'cash', 'payment_account_id' => $cash, 'paid_amount' => 40]);

        $r = $this->race(array_fill(0, 4, $job), 120);

        $this->assertSame(1, DB::table('purchases')->where('supplier_id', $this->supplier)->count(), 'one purchase: ' . json_encode($r));
        $this->assertSame(1, DB::table('purchase_products')->where('product_id', $p)->count(), 'one FIFO layer');
        $this->assertSame(5, (int) DB::table('product_stocks')->where('product_id', $p)->sum('qty'), 'stock +5 once');
        $this->assertEquals(60.0, $this->due(), 'supplier due once (100 - 40)');
        $this->assertEquals(960.0, $this->bal($cash), 'cash paid once');
        $this->assertSame(1, DB::table('journal_entries')->where('source_type', 'legacy:purchase.store')->count(), 'one journal entry');
        $this->assertJournalSettles();
    }

    public function test_different_purchases_at_once_add_up_exactly_and_keep_one_layer_each()
    {
        $this->flag(true);
        $p = $this->product();
        $cash = $this->cashAccount(1000);
        $jobs = [];
        foreach ([[3, 10], [4, 20], [5, 30], [6, 40]] as $i => [$qty, $cost]) {
            $jobs[] = $this->purchase($this->admin(), $p, $qty, $cost, "par-d-{$i}-" . uniqid(), ['pay_by' => 'cash', 'payment_account_id' => $cash, 'paid_amount' => 10]);
        }

        $r = $this->race($jobs, 120);

        $this->assertSame(4, DB::table('purchases')->where('supplier_id', $this->supplier)->count(), json_encode($r));
        $this->assertSame(4, DB::table('purchase_products')->where('product_id', $p)->count(), 'one layer per purchase');
        $this->assertSame(18, (int) DB::table('product_stocks')->where('product_id', $p)->sum('qty'));
        $this->assertSame(18, (int) DB::table('purchase_products')->where('product_id', $p)->sum('remaining_qty'), 'shelf equals layers');
        $this->assertEquals(3 * 10 + 4 * 20 + 5 * 30 + 6 * 40 - 40, $this->due(), 'supplier due is the sum, nothing lost');
        $this->assertEquals(1000 - 40, $this->bal($cash), 'four payments of 10, none lost');
        $this->assertJournalSettles();
    }

    public function test_purchases_and_orders_racing_keep_fifo_cost_and_the_shelf_in_step()
    {
        $this->flag(false);
        $p = $this->product(4); // 4 units @ 100 already on the shelf
        $jobs = [];
        foreach ([0, 1, 2] as $i) {
            $jobs[] = $this->purchase($this->admin(), $p, 5, 200, "par-m-{$i}-" . uniqid());
        }
        foreach ([0, 1] as $i) {
            $admin = $this->admin();
            DB::table('carts')->insert(['user_id' => $admin, 'product_id' => $p, 'variation' => 'M', 'quantity' => 2, 'price' => 1000, 'tax' => 0, 'shipping_cost' => 0, 'discount' => 0, 'shipping_type' => 'home_delivery', 'created_at' => now(), 'updated_at' => now()]);
            $addr = (int) DB::table('addresses')->insertGetId(['user_id' => $admin, 'address' => 'H', 'phone' => '01700000001', 'postal_code' => '1200', 'country_id' => 18, 'state_id' => 0, 'city_id' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $jobs[] = $this->req($admin, '/admin/orders', ['address_id' => $addr, 'payment_option' => 'cash_on_delivery', 'shipping_cost_amount' => 0]);
        }

        $r = $this->race($jobs, 120);

        $this->assertSame(2, DB::table('order_details')->where('product_id', $p)->count(), json_encode($r));
        $this->assertSame(4 + 15 - 4, (int) DB::table('product_stocks')->where('product_id', $p)->sum('qty'));
        $this->assertSame(15, (int) DB::table('purchase_products')->where('product_id', $p)->sum('remaining_qty'), 'layers agree with the shelf');
        foreach (DB::table('purchase_products')->where('product_id', $p)->get() as $layer) {
            $this->assertGreaterThanOrEqual(0, (int) $layer->remaining_qty);
            $this->assertLessThanOrEqual((int) $layer->purchase_quantity, (int) $layer->remaining_qty);
        }
        // the two orders (4 units) may be costed from the old layer (100) or, if a purchase landed first and the layer ordering is by id, still the old one: oldest first
        $this->assertEquals(4 * 100, DB::table('order_details')->where('product_id', $p)->get()->sum(fn ($d) => $d->buying_price * $d->quantity), 'sold from the oldest layer first');
        $this->assertEquals(0, (int) DB::table('purchase_products')->where('product_id', $p)->orderBy('id')->value('remaining_qty'), 'the original layer is used up');
    }

    public function test_goods_receipts_in_parallel_keep_layers_stock_and_journal_in_step()
    {
        $this->flag(true);
        $p = $this->product();
        DB::table('products')->where('id', $p)->update(['stock_policy' => 'backorder']);
        $jobs = [];
        foreach ([[2, '50'], [3, '60'], [4, '70']] as $i => [$qty, $cost]) {
            $jobs[] = ['action' => 'v2_receive', 'key' => "par-grn-{$i}-" . uniqid(), 'data' => ['supplier_id' => $this->supplier, 'lines' => [['product_id' => $p, 'variant' => 'M', 'qty' => $qty, 'unit_cost' => $cost]]]];
        }
        // two of them are also repeated (a retried request)
        $jobs[] = $jobs[0];
        $jobs[] = $jobs[1];

        $r = $this->race($jobs, 120);

        $this->assertSame(5, count(array_filter($r, fn ($x) => !empty($x['ok']))), json_encode($r));
        $this->assertSame(3, DB::table('goods_receipts')->where('supplier_id', $this->supplier)->count(), 'a repeated key is not a second receipt');
        $this->assertSame(9, (int) DB::table('product_stocks')->where('product_id', $p)->sum('qty'));
        $this->assertSame(9, (int) DB::table('purchase_products')->where('product_id', $p)->sum('remaining_qty'));
        $this->assertSame(3, DB::table('purchase_products')->where('product_id', $p)->count(), 'one layer per receipt');
        $this->assertEquals(2 * 50 + 3 * 60 + 4 * 70, (float) DB::table('purchase_products')->where('product_id', $p)->get()->sum(fn ($l) => $l->purchase_quantity * $l->buying_unit_cost), 'layer value is exactly what was received');
        $this->assertSame(3, DB::table('journal_entries')->whereIn('idempotency_key', DB::table('goods_receipts')->where('supplier_id', $this->supplier)->pluck('idempotency_key')->map(fn ($k) => 'grn:' . $k))->count(), 'one journal entry per receipt');
        $this->assertTrue(\App\Services\Accounting\JournalService::trialBalance(now()->toDateString())['balanced']);
    }
}
