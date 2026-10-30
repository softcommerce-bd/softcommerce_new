<?php

namespace Tests\Feature\Inventory;

use App\Models\Cart;
use App\Models\ChartOfAccount;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\SystemAccounts;
use App\Utility\PosUtility;
use Illuminate\Support\Facades\DB;

/** Legacy flows with the flag on: each posts once the old way and appears once in the journal, balanced. */
class LegacyFlowsJournalTest extends InventoryV2TestCase
{
    private function legacyEntries()
    {
        return DB::table('journal_entries')->where('source_type', 'like', 'legacy:%')->get();
    }

    private function assertJournalConsistent(): void
    {
        $this->assertTrue(JournalService::trialBalance(now()->toDateString())['balanced']);
        foreach ($this->legacyEntries() as $e) {
            $l = DB::table('journal_lines')->where('journal_entry_id', $e->id)->get();
            $this->assertEqualsWithDelta($l->sum('debit'), $l->sum('credit'), 0.001, "entry {$e->id} balances");
        }
    }

    public function test_pos_sale_takes_stock_immediately_and_posts_sales_cogs_inventory_once_in_the_journal()
    {
        $p = $this->productAtZero(['unit_price' => 500]);
        $this->receive($p, '', 5, '100');
        $cash = ChartOfAccount::create(['code' => 'P-CASH-' . uniqid(), 'name' => 'Pos cash', 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0]);
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $temp = (string) random_int(800000000, 899999999);
        Cart::create(['owner_id' => User::where('user_type', 'admin')->orderBy('id')->value('id'), 'user_id' => null, 'temp_user_id' => $temp, 'product_id' => $p->id, 'variation' => '', 'quantity' => 2, 'price' => 500, 'tax' => 0, 'shipping_cost' => 0, 'discount' => 0]);
        $inv = SystemAccounts::id('inventory');
        $invBefore = (float) DB::table('chart_of_accounts')->where('id', $inv)->value('current_balance');
        $salesBefore = (float) ChartOfAccount::where('sub_type', 'sales')->where('name', 'Sales')->value('current_balance');

        $res = PosUtility::orderStore(['user_id' => null, 'temp_usder_id' => $temp, 'payment_type' => 'cash', 'shippingInfo' => ['name' => 'W', 'phone' => '01700000000', 'address' => 'c'],
            'payment_methods' => [$cash->id], 'payment_amounts' => [1000], 'payment_method_types' => ['cash']]);
        $this->assertSame(3, $this->stockQty($p, ''), 'a counter sale takes the units immediately');
        $this->assertEquals(200.0, $invBefore - (float) DB::table('chart_of_accounts')->where('id', $inv)->value('current_balance'), 'inventory credited with the 2 units of FIFO cost');
        $entries = $this->legacyEntries();
        $this->assertGreaterThanOrEqual(1, $entries->count());
        $lines = DB::table('journal_lines')->whereIn('journal_entry_id', $entries->pluck('id'))->get();
        $this->assertEquals(200.0, (float) $lines->where('account_id', $inv)->sum('credit'));
        $this->assertSame(0, DB::table('account_statement_logs')->where('transaction_type', 'journal')->whereIn('source_id', $entries->pluck('id'))->count(), 'no second statement row');
        $this->assertJournalConsistent();
    }

    public function test_legacy_supplier_style_purchase_with_payment_is_journalled_once_with_traceable_source()
    {
        $p = $this->productAtZero();
        $cash = ChartOfAccount::create(['code' => 'L-CASH-' . uniqid(), 'name' => 'Leg cash', 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 1000, 'status' => 'active', 'is_deleted' => 0]);
        $this->actingAs($this->makeAdmin());
        $this->postJson(route('purchase.store'), ['op_key' => 'lp-' . uniqid(), 'supplier_id' => $this->supplierId, 'purchase_date' => now()->toDateString(), 'product_id' => [$p->id], 'name' => ['x'], 'variant' => [''],
            'purchase_quantity' => [5], 'buying_unit_cost' => [20], 'total_cost' => [100], 'pay_by' => 'cash', 'payment_account_id' => $cash->id, 'paid_amount' => 40])->assertOk();
        $this->assertEquals(960.0, (float) $cash->fresh()->current_balance, 'legacy posting unchanged: cash paid once');
        $e = $this->legacyEntries()->where('source_type', 'legacy:purchase.store')->first();
        $this->assertNotNull($e, 'the entry names the screen that made it');
        $l = DB::table('journal_lines')->where('journal_entry_id', $e->id)->get();
        $this->assertEquals(40.0, (float) $l->where('account_id', $cash->id)->sum('credit'));
        $this->assertEquals(100.0, (float) $l->where('account_id', SystemAccounts::id('inventory'))->sum('debit'));
        $this->assertEquals(60.0, (float) $l->where('account_id', SystemAccounts::id('payable'))->sum('credit'));
        $this->assertJournalConsistent();
    }

    public function test_legacy_supplier_payment_is_journalled_once()
    {
        $p = $this->productAtZero();
        $cash = ChartOfAccount::create(['code' => 'SP-CASH-' . uniqid(), 'name' => 'Sp cash', 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 1000, 'status' => 'active', 'is_deleted' => 0]);
        $this->actingAs($this->makeAdmin());
        $this->postJson(route('purchase.store'), ['op_key' => 'sp-' . uniqid(), 'supplier_id' => $this->supplierId, 'purchase_date' => now()->toDateString(), 'product_id' => [$p->id], 'name' => ['x'], 'variant' => [''],
            'purchase_quantity' => [5], 'buying_unit_cost' => [20], 'total_cost' => [100]])->assertOk();
        $purchaseId = (int) DB::table('purchases')->where('supplier_id', $this->supplierId)->value('id');
        $res = $this->postJson(route('accounts.supplier-payments.store'), ['supplier_id' => $this->supplierId, 'payment_date' => now()->toDateString(), 'amount' => 70, 'payment_method' => 'CASH', 'account_head_id' => $cash->id,
            'allocations' => json_encode([['purchase_id' => $purchaseId, 'paid_amount' => 70]])]);
        $this->assertTrue($res->json('success') ?? false, json_encode($res->json()));
        $this->assertEquals(930.0, (float) $cash->fresh()->current_balance);
        $e = $this->legacyEntries()->where('source_type', 'legacy:accounts.supplier-payments.store')->first();
        $this->assertNotNull($e);
        $l = DB::table('journal_lines')->where('journal_entry_id', $e->id)->get();
        $this->assertEquals(70.0, (float) $l->where('account_id', $cash->id)->sum('credit'));
        $this->assertEquals(70.0, (float) $l->where('account_id', SystemAccounts::id('payable'))->sum('debit'));
        $this->assertJournalConsistent();
    }
}
