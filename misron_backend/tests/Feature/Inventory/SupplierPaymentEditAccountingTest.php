<?php

namespace Tests\Feature\Inventory;

use App\Models\ChartOfAccount;
use App\Services\Accounting\JournalService;
use Illuminate\Support\Facades\DB;

/**
 * T-5: SupplierPaymentController::update must be "revert the old payment, apply the new one" ON THE CURRENT SUPPLIER ROW.
 * Before the correction each edit left suppliers.due_amount lower and paid_amount higher by the amount being replaced.
 * The specification used here is the sequence the store and delete screens already define (an edit is the same as deleting the payment and storing
 * the new one), so every expectation is checked against what a direct store of the final amount produces, not against hand-picked numbers alone.
 */
class SupplierPaymentEditAccountingTest extends InventoryV2TestCase
{
    private function supplier(): int
    {
        return (int) DB::table('suppliers')->insertGetId(['slug' => 'se-' . uniqid(), 'business_name' => 'SE', 'contact_person_name' => 'C', 'mobile' => '017' . random_int(10000000, 99999999), 'opening_balance' => '0', 'due_amount' => 0, 'paid_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function fixture(float $cash = 1000): array
    {
        $s = $this->supplier();
        $p = $this->productAtZero();
        $this->post(route('purchase.store'), ['op_key' => 'se-' . uniqid(), 'supplier_id' => $s, 'purchase_date' => now()->toDateString(), 'product_id' => [$p->id], 'name' => ['x'], 'variant' => [''],
            'purchase_quantity' => [5], 'buying_unit_cost' => [20], 'total_cost' => [100]], ['Accept' => 'application/json'])->assertOk();
        $account = ChartOfAccount::create(['code' => 'SE-' . uniqid(), 'name' => 'SE cash ' . uniqid(), 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => $cash, 'status' => 'active', 'is_deleted' => 0]);

        return ['s' => $s, 'purchase' => (int) DB::table('purchases')->where('supplier_id', $s)->value('id'), 'cash' => $account, 'ap' => ChartOfAccount::getAccountsPayable()];
    }

    private function body(array $c, float $amt, ?int $supplier = null, ?int $purchase = null): array
    {
        return ['supplier_id' => $supplier ?? $c['s'], 'payment_date' => now()->toDateString(), 'amount' => $amt, 'payment_method' => 'CASH', 'account_head_id' => $c['cash']->id, 'allocations' => json_encode([['purchase_id' => $purchase ?? $c['purchase'], 'paid_amount' => $amt]])];
    }

    private function store(array $c, float $amt): int
    {
        $this->post(route('accounts.supplier-payments.store'), $this->body($c, $amt), ['Accept' => 'application/json'])->assertOk();

        return (int) DB::table('supplier_payments')->where('supplier_id', $c['s'])->orderByDesc('id')->value('id');
    }

    private function edit(array $c, int $id, float $amt, ?int $supplier = null, ?int $purchase = null)
    {
        return $this->put(route('accounts.supplier-payments.update', $id), $this->body($c, $amt, $supplier, $purchase), ['Accept' => 'application/json']);
    }

    /** every figure the edit can touch */
    private function figures(array $c, ?int $payment = null): array
    {
        $sup = DB::table('suppliers')->where('id', $c['s'])->first();

        return [
            'due' => (float) $sup->due_amount, 'paid' => (float) $sup->paid_amount, 'purchase_due' => (float) DB::table('purchases')->where('id', $c['purchase'])->value('total_payment_due'),
            'cash' => (float) DB::table('chart_of_accounts')->where('id', $c['cash']->id)->value('current_balance'), 'ap' => (float) DB::table('chart_of_accounts')->where('id', $c['ap']->id)->value('current_balance'),
            'payments' => DB::table('supplier_payments')->where('supplier_id', $c['s'])->count(), 'payment_sum' => (float) DB::table('supplier_payments')->where('supplier_id', $c['s'])->sum('amount'),
            'allocated' => (float) DB::table('supplier_payment_purchases')->where('purchase_id', $c['purchase'])->sum('paid_amount'),
            'logs' => DB::table('account_statement_logs')->whereIn('account_id', [$c['cash']->id])->count(),
        ];
    }

    private function assertReconciled(array $c): void
    {
        $s = $this->figures($c);
        $this->assertEquals($s['purchase_due'], $s['due'], 'supplier due equals what the purchases still owe');
        $this->assertEquals($s['payment_sum'], $s['paid'], 'supplier paid equals the live payments');
        $this->assertEquals(100.0 - $s['allocated'], $s['purchase_due'], 'purchase open amount = invoice - allocations');
        $this->assertGreaterThanOrEqual(0.0, $s['due'], 'never negative');
    }

    public function test_repeated_identical_edits_do_not_move_the_supplier_again()
    {
        $this->actingAs($this->makeAdmin());
        $c = $this->fixture();
        $id = $this->store($c, 30);
        $this->edit($c, $id, 50)->assertOk();
        $afterFirst = $this->figures($c);
        $this->assertEquals(50.0, $afterFirst['due']);
        $this->assertEquals(50.0, $afterFirst['paid']);
        $this->assertEquals(950.0, $afterFirst['cash']);
        $this->edit($c, $id, 50)->assertOk();
        $this->edit($c, $id, 50)->assertOk();
        $afterThird = $this->figures($c);
        unset($afterFirst['logs'], $afterThird['logs']); // every edit adds its own reversal/repost lines by design
        $this->assertSame($afterFirst, $afterThird, 'three identical edits end exactly where one does');
        $this->assertEquals(50.0, (float) DB::table('supplier_payments')->where('id', $id)->value('due_amount'), 'the due stored on the payment is the supplier due after it');
        $this->assertReconciled($c);
    }

    public function test_an_increase_and_a_decrease_apply_only_the_difference()
    {
        $this->actingAs($this->makeAdmin());
        $c = $this->fixture();
        $id = $this->store($c, 30);
        $this->edit($c, $id, 80)->assertOk();
        $this->assertEquals(['due' => 20.0, 'paid' => 80.0, 'purchase_due' => 20.0, 'cash' => 920.0], array_intersect_key($this->figures($c), array_flip(['due', 'paid', 'purchase_due', 'cash'])));
        $this->assertReconciled($c);
        $this->edit($c, $id, 10)->assertOk();
        $this->assertEquals(['due' => 90.0, 'paid' => 10.0, 'purchase_due' => 90.0, 'cash' => 990.0], array_intersect_key($this->figures($c), array_flip(['due', 'paid', 'purchase_due', 'cash'])));
        $this->assertReconciled($c);
    }

    public function test_an_edit_ends_exactly_like_storing_the_final_amount_directly()
    {
        $this->actingAs($this->makeAdmin());
        $edited = $this->fixture();
        $direct = $this->fixture();
        $id = $this->store($edited, 30);
        $this->edit($edited, $id, 70)->assertOk();
        $this->edit($edited, $id, 45)->assertOk();
        $this->store($direct, 45);
        $a = $this->figures($edited);
        $b = $this->figures($direct);
        foreach (['due', 'paid', 'purchase_due', 'payments', 'payment_sum', 'allocated'] as $k) {
            $this->assertEquals($b[$k], $a[$k], "$k: edit(30 -> 70 -> 45) equals a direct payment of 45");
        }
        $this->assertEquals(1000.0 - 45.0, $a['cash']);
    }

    public function test_deleting_an_edited_payment_returns_everything_to_the_start()
    {
        $this->actingAs($this->makeAdmin());
        $c = $this->fixture();
        $start = $this->figures($c);
        $id = $this->store($c, 30);
        $this->edit($c, $id, 55)->assertOk();
        $this->edit($c, $id, 20)->assertOk();
        $this->delete(route('accounts.supplier-payments.delete', $id), [], ['Accept' => 'application/json'])->assertOk();
        $end = $this->figures($c);
        foreach (['due', 'paid', 'purchase_due', 'cash', 'ap', 'payments', 'payment_sum', 'allocated'] as $k) {
            $this->assertEquals($start[$k], $end[$k], "$k is back to the start");
        }
    }

    public function test_changing_the_supplier_moves_the_payment_between_the_two_suppliers_exactly()
    {
        $this->actingAs($this->makeAdmin());
        $a = $this->fixture();
        $b = $this->fixture();
        $id = $this->store($a, 30);
        $this->edit($a, $id, 40, $b['s'], $b['purchase'])->assertOk();
        $this->assertEquals(100.0, (float) DB::table('suppliers')->where('id', $a['s'])->value('due_amount'), 'the old supplier is back to owing everything');
        $this->assertEquals(0.0, (float) DB::table('suppliers')->where('id', $a['s'])->value('paid_amount'));
        $this->assertEquals(60.0, (float) DB::table('suppliers')->where('id', $b['s'])->value('due_amount'), 'the new supplier received the payment of 40');
        $this->assertEquals(40.0, (float) DB::table('suppliers')->where('id', $b['s'])->value('paid_amount'));
        $this->assertEquals(100.0, (float) DB::table('purchases')->where('id', $a['purchase'])->value('total_payment_due'));
        $this->assertEquals(60.0, (float) DB::table('purchases')->where('id', $b['purchase'])->value('total_payment_due'));
    }

    public function test_a_failed_edit_leaves_every_balance_unchanged()
    {
        $this->actingAs($this->makeAdmin());
        $c = $this->fixture(100);                  // only 100 in the account
        $id = $this->store($c, 30);                // account now 70
        $before = $this->figures($c);
        $payment = (array) DB::table('supplier_payments')->where('id', $id)->first();

        // 1. more than the account can pay (70 + the 30 given back = 100 available; 150 asked)
        $this->edit($c, $id, 150)->assertStatus(500);
        // 2. more than the invoice still owes
        $this->edit($c, $id, 101)->assertStatus(500);
        // 3. allocations that do not add up to the amount
        $this->put(route('accounts.supplier-payments.update', $id), ['supplier_id' => $c['s'], 'payment_date' => now()->toDateString(), 'amount' => 50, 'payment_method' => 'CASH', 'account_head_id' => $c['cash']->id,
            'allocations' => json_encode([['purchase_id' => $c['purchase'], 'paid_amount' => 10]])], ['Accept' => 'application/json'])->assertStatus(500);

        $this->assertSame($before, $this->figures($c), 'supplier, purchase, cash, payable, allocations and statement lines are all as before');
        $this->assertEquals($payment, (array) DB::table('supplier_payments')->where('id', $id)->first(), 'the payment row itself is untouched');
    }

    public function test_journal_stays_balanced_and_unduplicated_across_edits()
    {
        config(['inventory.confirmed_only' => true]);
        \App\Services\Inventory\InventoryMode::reset();
        \App\Services\Accounting\LegacyLedgerBridge::reset();
        $this->actingAs($this->makeAdmin());
        $c = $this->fixture();
        $id = $this->store($c, 30);
        $this->edit($c, $id, 70)->assertOk();
        $this->edit($c, $id, 70)->assertOk();
        $this->delete(route('accounts.supplier-payments.delete', $id), [], ['Accept' => 'application/json'])->assertOk();

        $this->assertTrue(JournalService::trialBalance(now()->toDateString())['balanced'], 'debits equal credits');
        $this->assertSame(0, DB::table('ledger_outbox')->whereNull('processed_at')->count(), 'every posting group was journalled');
        $dupes = DB::table('journal_entries')->groupBy('idempotency_key')->havingRaw('COUNT(*) > 1')->count();
        $this->assertSame(0, $dupes, 'no entry twice');
        // the cash account's journal movement equals its balance movement (+0 after store, edits and delete)
        $this->assertEquals(1000.0, (float) $c['cash']->fresh()->current_balance);
    }

    public function test_an_injected_deadlock_in_an_edit_is_retried_without_moving_the_supplier_twice()
    {
        $this->actingAs($this->makeAdmin());
        $clean = $this->fixture();
        $cid = $this->store($clean, 30);
        $this->edit($clean, $cid, 60)->assertOk();
        $twin = $this->fixture();
        $tid = $this->store($twin, 30);
        $inj = DeadlockInjector::once('/^insert into `account_statement_logs`/i');
        $res = $this->edit($twin, $tid, 60);
        $this->assertSame(1, $inj->thrown);
        $this->assertSame('1', $res->headers->get('X-Deadlock-Retries'));
        $a = $this->figures($clean);
        $b = $this->figures($twin);
        foreach (['due', 'paid', 'purchase_due', 'cash', 'payment_sum', 'allocated'] as $k) {
            $this->assertEquals($a[$k], $b[$k], $k);
        }
    }
}
