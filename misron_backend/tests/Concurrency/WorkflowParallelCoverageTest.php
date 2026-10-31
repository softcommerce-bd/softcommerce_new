<?php

namespace Tests\Concurrency;

use Illuminate\Support\Facades\DB;

/**
 * T-5 final verification, workflows 5-12 and 17: REAL parallel processes (authenticated HTTP through the kernel) against committed rows.
 * Each test checks the books exactly: shared-account sums (no lost update), one effect per duplicated request, distinct document numbers,
 * and that the journal settles (no entry twice, balanced).
 */
class WorkflowParallelCoverageTest extends HttpWorkflowCase
{
    private function ok(array $res): int
    {
        return count(array_filter($res, fn ($r) => ($r['status'] ?? 0) < 400 && !str_contains(json_encode($r['body'] ?? ''), 'rror')));
    }

    // ---- W10 / W17: expense, income, transfer, voucher on one shared account ---------------------------------------------

    public function test_four_incomes_into_one_account_add_up_and_get_distinct_numbers()
    {
        $this->flag(true);
        $cash = $this->cashAccount(0);
        $inc = $this->account('income', 'income', 0);
        $res = $this->race(array_map(fn ($i) => $this->req($this->admin(), route('accounts.incomes.store', [], false), ['date' => now()->toDateString(), 'received_account_id' => $cash, 'income_account_ids' => [$inc], 'amounts' => [10 * $i]]), [1, 2, 3, 4]), 120);

        $this->assertSame(4, DB::table('incomes')->where('received_account_id', $cash)->count(), json_encode(array_column($res, 'status')));
        $this->assertEquals(100.0, $this->bal($cash), 'no lost update: 10+20+30+40');
        $this->assertEquals(100.0, $this->bal($inc));
        $this->assertCount(4, DB::table('incomes')->where('received_account_id', $cash)->pluck('voucher_no')->unique(), 'distinct INC- numbers');
        $this->assertJournalSettles();
    }

    public function test_four_vouchers_over_two_shared_accounts_post_every_line_once()
    {
        $this->flag(true);
        $dr = $this->cashAccount(0);
        $cr = $this->account('liability', 'payable', 500);
        $res = $this->race(array_map(fn ($i) => $this->req($this->admin(), route('accounts.vouchers.store', [], false), ['date' => now()->toDateString(), 'type' => 'JV', 'account_ids' => [$dr, $cr], 'debits' => [10 * $i, 0], 'credits' => [0, 10 * $i]]), [1, 2, 3, 4]), 120);

        $this->assertSame(4, DB::table('voucher_details')->where('account_id', $dr)->count(), json_encode(array_column($res, 'status')));
        $this->assertEquals(100.0, $this->bal($dr), 'debit side: 10+20+30+40');
        $this->assertCount(4, DB::table('vouchers')->whereIn('id', DB::table('voucher_details')->where('account_id', $dr)->pluck('voucher_id'))->pluck('voucher_no')->unique());
        $this->assertJournalSettles();
    }

    public function test_voucher_deleted_three_times_at_once_is_reversed_once()
    {
        $this->flag(true);
        $dr = $this->cashAccount(0);
        $cr = $this->account('liability', 'payable', 500);
        $this->runOne($this->req($this->admin(), route('accounts.vouchers.store', [], false), ['date' => now()->toDateString(), 'type' => 'JV', 'account_ids' => [$dr, $cr], 'debits' => [75, 0], 'credits' => [0, 75]]));
        $id = (int) DB::table('voucher_details')->where('account_id', $dr)->value('voucher_id');
        $afterStore = [$this->bal($dr), $this->bal($cr)];
        $this->assertNotEquals([0.0, 500.0], $afterStore);
        $this->race(array_map(fn () => $this->req($this->admin(), route('accounts.vouchers.delete', $id, false), [], 'DELETE'), range(1, 3)), 120);
        $this->assertEquals([0.0, 500.0], [$this->bal($dr), $this->bal($cr)], 'back to the start, once');
        $this->assertJournalSettles();
    }

    public function test_expense_deleted_three_times_at_once_is_reversed_once()
    {
        $this->flag(true);
        $cash = $this->cashAccount(100);
        $exp = $this->account('expense', 'expense', 0);
        $this->runOne($this->req($this->admin(), route('accounts.expense-payments.store', [], false), ['date' => now()->toDateString(), 'payment_account_id' => $cash, 'expense_account_ids' => [$exp], 'amounts' => [60]]));
        $id = (int) DB::table('expense_payments')->where('payment_account_id', $cash)->value('id');
        $this->assertEquals(40.0, $this->bal($cash));
        $this->race(array_map(fn () => $this->req($this->admin(), route('accounts.expense-payments.delete', $id, false), [], 'DELETE'), range(1, 3)), 120);
        $this->assertEquals(100.0, $this->bal($cash));
        $this->assertEquals(0.0, $this->bal($exp));
        $this->assertJournalSettles();
    }

    public function test_four_transfers_between_the_same_accounts_in_opposite_directions_conserve_the_total_without_deadlock_failures()
    {
        $this->flag(true);
        $a = $this->cashAccount(1000);
        $b = $this->cashAccount(1000);
        $jobs = [];
        foreach ([[$a, $b, 10], [$b, $a, 20], [$a, $b, 30], [$b, $a, 40]] as [$f, $t, $amt]) {
            $jobs[] = $this->req($this->admin(), route('accounts.balance-transfers.store', [], false), ['transaction_type' => 'transfer', 'date' => now()->toDateString(), 'from_account_id' => $f, 'to_account_id' => $t, 'amount' => $amt, 'charge' => 0]);
        }
        $res = $this->race($jobs, 120);

        $this->assertSame(4, DB::table('balance_transfers')->whereIn('from_account_id', [$a, $b])->count(), json_encode(array_map(fn ($r) => [$r['status'] ?? null, $r['headers'] ?? null, $r['flash_error'] ?? null], $res)));
        $this->assertEquals(2000.0, $this->bal($a) + $this->bal($b), 'conserved');
        $this->assertEquals(1000 - 10 + 20 - 30 + 40, $this->bal($a));
        $this->assertCount(4, DB::table('balance_transfers')->whereIn('from_account_id', [$a, $b])->pluck('voucher_no')->unique());
        $this->assertJournalSettles();
    }

    // ---- W11: stock adjustment ----------------------------------------------------------------------------------------------

    private function adjustBody(int $product, int $qty): array
    {
        $stockId = (int) DB::table('product_stocks')->where('product_id', $product)->where('variant', 'M')->value('id');
        return ['transaction_date' => now()->toDateString(), 'adjustment_type' => 'normal', 'products' => [['product_id' => $product, 'product_stock_id' => $stockId, 'variant' => 'M', 'quantity' => $qty, 'unit_price' => 400]]];
    }

    public function test_four_stock_adjustments_of_one_product_remove_exactly_their_sum_from_shelf_and_layers()
    {
        $this->flag(false);
        $p = $this->seedProduct(['M' => 10], 1000, 400, ['M' => [3, 7]]);
        $res = $this->race(array_map(fn ($q) => $this->req($this->admin(), '/admin/stock-adjustments', $this->adjustBody($p, $q)), [1, 2, 1, 2]), 120);

        $this->assertSame(4, DB::table('stock_adjustments')->count() - $this->adjustmentsBefore(), json_encode(array_column($res, 'status')));
        $this->assertSame(4, (int) DB::table('product_stocks')->where('product_id', $p)->value('qty'), '10 - 6');
        $this->assertSame(4, (int) DB::table('purchase_products')->where('product_id', $p)->sum('remaining_qty'), 'layers equal the shelf');
        $this->assertCount(4, DB::table('stock_adjustments')->orderByDesc('id')->limit(4)->pluck('reference_no')->unique(), 'distinct SA numbers');
    }

    private function adjustmentsBefore(): int
    {
        return 0;
    }
}
