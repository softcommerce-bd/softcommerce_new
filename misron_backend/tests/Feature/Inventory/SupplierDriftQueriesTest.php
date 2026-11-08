<?php

namespace Tests\Feature\Inventory;

use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

/**
 * N-1: proves that the read-only audit queries Q1-Q10 in docs/T5_SUPPLIER_PAYMENT_IMPACT_QUERIES.sql identify KNOWN, planted discrepancies
 * (and stay silent on a clean supplier). All data is synthetic and lives in the test's wrapping transaction. The SQL is read from the file
 * the accountant receives, so what is tested is what is delivered. Nothing is corrected by any query.
 */
class SupplierDriftQueriesTest extends InventoryV2TestCase
{
    private array $sup = [];
    private int $cash;
    private int $payable;
    private int $user;

    private function q(int $n): string
    {
        $sql = file_get_contents(base_path('docs/T5_SUPPLIER_PAYMENT_IMPACT_QUERIES.sql'));
        preg_match('/^-- Q' . $n . '\..*?(?=^-- Q\d+\.|^-- ====|\z)/ms', $sql, $m);
        $this->assertNotEmpty($m, "Q$n found in the file");
        $lines = array_filter(explode("\n", $m[0]), fn ($l) => !str_starts_with(ltrim($l), '--') && trim($l) !== '');

        $body = implode("\n", $lines);

        return trim(strstr($body, ';', true) ?: $body); // the first statement only (Q6 is followed by ROLLBACK in the file)
    }

    private function qrun(int $n): array
    {
        return array_map(fn ($r) => (array) $r, DB::select($this->q($n)));
    }

    private function mine(array $rows, string $key = 'supplier_id'): array
    {
        return array_values(array_filter($rows, fn ($r) => in_array((int) $r[$key], $this->sup, true)));
    }

    private function supplier(string $tag, float $due, float $paid, float $opening = 0): int
    {
        $id = DB::table('suppliers')->insertGetId(['business_name' => "N1 $tag", 'slug' => "n1-$tag-" . uniqid(), 'contact_id' => 'N1' . uniqid(), 'mobile' => '0', 'opening_balance' => $opening, 'due_amount' => $due, 'paid_amount' => $paid, 'created_at' => now(), 'updated_at' => now()]);
        $this->sup[$tag] = $id;

        return $id;
    }

    private function purchase(int $sup, float $net, float $paid, float $due): int
    {
        return DB::table('purchases')->insertGetId(['supplier_id' => $sup, 'reference_no' => 'N1-' . uniqid(), 'purchase_date' => now()->toDateString(), 'net_total_amount' => $net, 'total_paid' => $paid, 'total_payment_due' => (string) $due, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function payment(int $sup, float $amount, ?int $purchase = null, ?float $alloc = null, bool $deleted = false, ?int $account = null): int
    {
        $id = DB::table('supplier_payments')->insertGetId(['supplier_id' => $sup, 'payment_no' => 'N1P-' . uniqid(), 'amount' => $amount, 'paid_amount' => $amount, 'payment_account_id' => $account ?? $this->cash, 'transaction_method_id' => 1, 'payment_type' => 'partial', 'payment_date' => now()->toDateString(), 'created_by' => $this->user, 'status' => 'paid', 'deleted_at' => $deleted ? now() : null, 'created_at' => now(), 'updated_at' => now()]);
        if ($purchase) {
            DB::table('supplier_payment_purchases')->insert(['supplier_payment_id' => $id, 'purchase_id' => $purchase, 'paid_amount' => $alloc ?? $amount, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $id;
    }

    /** a statement line the way SupplierPaymentController::insertStatementLog writes it */
    private function line(int $payment, int $account, string $type, float $debit, float $credit): void
    {
        DB::table('account_statement_logs')->insert(['account_id' => $account, 'transaction_date' => now()->toDateString(), 'reference_no' => 'N1-' . $payment, 'transaction_type' => $type, 'source_id' => $payment, 'source_type' => 'App\\Models\\SupplierPayment', 'debit' => $debit, 'credit' => $credit, 'balance' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function posted(int $payment, float $amount): void
    {
        $this->line($payment, $this->payable, 'supplier_payment', $amount, 0);
        $this->line($payment, $this->cash, 'supplier_payment', 0, $amount);
    }

    public function test_the_queries_find_exactly_the_planted_discrepancies()
    {
        $this->user = DB::table('users')->insertGetId(['name' => 'N1', 'email' => uniqid() . '@n1.test', 'password' => 'x', 'user_type' => 'admin', 'created_at' => now(), 'updated_at' => now()]);
        $this->cash = ChartOfAccount::create(['code' => 'N1C' . uniqid(), 'name' => 'N1 Cash', 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0])->id;
        $this->payable = ChartOfAccount::create(['code' => 'N1P' . uniqid(), 'name' => 'N1 Payable', 'type' => 'liability', 'sub_type' => 'payable', 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0])->id;

        // CLEAN: purchase 1000, paid 400 by one payment of 400, fully allocated, fully posted. Nothing may be reported.
        $s = $this->supplier('clean', 600, 400);
        $p = $this->purchase($s, 1000, 400, 600);
        $pay = $this->payment($s, 400, $p);
        $this->posted($pay, 400);

        // OPENING: a due-type opening of 250, no purchases. Q7 shows it as "not explained by purchases" (it IS the opening, assumption A5), nothing else.
        $this->supplier('opening', 250, 0);

        // EDIT DRIFT (the defect): purchase 1000, paid 300 after one edit 200 -> 300. Correct supplier: due 700, paid 300.
        // Defective result: due 700 - 200 = 500, paid 300 + 200 = 500. The edit left a reversal pair of 200 and a new posting of 300.
        $s = $this->supplier('edited', 500, 500);
        $p = $this->purchase($s, 1000, 300, 700);
        $pay = $this->payment($s, 300, $p);
        $this->line($pay, $this->payable, 'supplier_payment', 200, 0);
        $this->line($pay, $this->cash, 'supplier_payment', 0, 200);
        $this->line($pay, $this->payable, 'supplier_payment_reversal', 0, 200);
        $this->line($pay, $this->cash, 'supplier_payment_reversal', 200, 0);
        $this->posted($pay, 300);

        // ORPHAN: payment 150 deleted, its allocation row left behind (old delete). Cash and payable were reversed properly.
        $s = $this->supplier('orphan', 150, 0);
        $p = $this->purchase($s, 150, 0, 150); // the delete correctly gave the purchase its 150 back
        $pay = $this->payment($s, 150, $p, 150, true);
        $this->posted($pay, 150);
        $this->line($pay, $this->payable, 'supplier_payment_deletion', 0, 150);
        $this->line($pay, $this->cash, 'supplier_payment_deletion', 150, 0);

        // MISSING CASH LINE: live payment 120 with no statement lines at all.
        $s = $this->supplier('nocash', 880, 120);
        $p = $this->purchase($s, 1000, 120, 880);
        $this->payment($s, 120, $p);

        // PURCHASE TOTALS: net 800, paid 100, due 500 (should add to 800); and a payment of 100 allocated to a purchase that says only 50 paid.
        $s = $this->supplier('totals', 600, 100);
        $this->purchase($s, 800, 100, 500);
        $p2 = $this->purchase($s, 500, 50, 450);
        $over = $this->payment($s, 100, $p2);
        $this->posted($over, 100);

        // ADVANCE: payment 90, no allocation (legitimate, Q8a lists it as unallocated for review) ; NEGATIVE: due -50
        $s = $this->supplier('advance', 0, 90);
        $adv = $this->payment($s, 90);
        $this->posted($adv, 90);
        $this->supplier('negative', -50, 0);

        // ---- Q2: edited supplier found, residual 0 = the defect alone explains the difference; clean supplier absent
        $q2 = $this->mine($this->qrun(2));
        $byId = array_column($q2, null, 'supplier_id');
        $this->assertArrayHasKey($this->sup['edited'], $byId, 'Q2 lists the edited supplier');
        $this->assertSame(1, (int) $byId[$this->sup['edited']]['edits_recorded']);
        $this->assertEquals(200.0, (float) $byId[$this->sup['edited']]['sum_reversed_upper_bound_of_drift']);
        $this->assertEquals(0.0, (float) $byId[$this->sup['edited']]['due_residual_if_drift_explains'], 'the 200 drift explains the whole difference');
        $this->assertEquals(0.0, (float) $byId[$this->sup['edited']]['paid_residual_if_drift_explains']);
        $this->assertArrayNotHasKey($this->sup['clean'], $byId, 'the clean supplier is not reported');
        $this->assertArrayHasKey($this->sup['negative'], $byId);

        // ---- Q6: negative balance
        $neg = array_column($this->mine($this->qrun(6)), 'supplier_id');
        $this->assertContains($this->sup['negative'], array_map('intval', $neg));
        $this->assertNotContains($this->sup['clean'], array_map('intval', $neg));

        // ---- Q7: opening is shown as unexplained 250; edited as -200 (due too low); clean 0
        $q7 = array_column($this->mine($this->qrun(7)), null, 'supplier_id');
        $this->assertEquals(250.0, (float) $q7[$this->sup['opening']]['due_amount_not_explained_by_purchases']);
        $this->assertEquals(-200.0, (float) $q7[$this->sup['edited']]['due_amount_not_explained_by_purchases']);
        $this->assertEquals(0.0, (float) $q7[$this->sup['clean']]['due_amount_not_explained_by_purchases']);

        // ---- Q8: a = unallocated advance 90 + the missing-allocation none; b = the orphan allocation of the deleted payment
        $q8 = $this->mine($this->qrun(8));
        $a = array_values(array_filter($q8, fn ($r) => $r['check_name'] === 'a_allocated_vs_amount'));
        $b = array_values(array_filter($q8, fn ($r) => $r['check_name'] === 'b_allocation_of_deleted_payment'));
        $this->assertSame([$this->sup['advance']], array_map(fn ($r) => (int) $r['supplier_id'], $a), 'only the advance is unallocated');
        $this->assertEquals(90.0, (float) $a[0]['unallocated']);
        $this->assertSame([$this->sup['orphan']], array_map(fn ($r) => (int) $r['supplier_id'], $b), 'only the orphan allocation');

        // ---- Q9: totals mismatch (200) and the over-allocated purchase; clean/edited/orphan silent
        $q9 = $this->mine($this->qrun(9));
        $ids = array_unique(array_map(fn ($r) => (int) $r['supplier_id'], $q9));
        $this->assertSame([$this->sup['totals']], array_values($ids), 'only the planted purchases are reported');
        $this->assertCount(2, $q9, 'the 800/100/500 purchase (200 apart) and the 100-allocated-to-50-paid purchase');
        $this->assertContains(200.0, array_map(fn ($r) => (float) $r['total_minus_paid_minus_due'], $q9));

        // ---- Q10: only the live payment with no cash line (expected 120 - 0)
        $q10 = $this->mine($this->qrun(10));
        $this->assertSame([$this->sup['nocash']], array_map(fn ($r) => (int) $r['supplier_id'], $q10), 'edited (net 300), deleted (net 0), clean and advance are consistent');
        $this->assertEquals(120.0, (float) $q10[0]['expected_minus_net']);
        $this->assertSame('live', $q10[0]['state']);

        // ---- read-only: none of the ten queries changed a row
        $this->assertEquals(500.0, (float) DB::table('suppliers')->where('id', $this->sup['edited'])->value('due_amount'));
    }
}
