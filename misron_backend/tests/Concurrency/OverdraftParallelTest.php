<?php

namespace Tests\Concurrency;

use Illuminate\Support\Facades\DB;

/**
 * T-5 F-04: the EXISTING "insufficient balance" rules are unchanged; they are now evaluated under the account's row lock, so simultaneous
 * requests that each fit alone but not together cannot both succeed (the account cannot be driven below zero by a race).
 * Four requests of 60 against an account holding 100: exactly one succeeds.
 * NOT covered: BankTransactionController::withdraw/transfer. They call BankTransaction::generateTransactionNo(), which does not exist anywhere in the
 * repository (or its history), so those screens fail before any posting; the number format is a business decision (docs/T5_MASTER_EXECUTION_REPORT.md).
 */
class OverdraftParallelTest extends HttpWorkflowCase
{
    /** the pre-check reads the balance (100) and pauses; meanwhile another request takes 60 and commits; the paused one then wakes with a STALE pre-check */
    private const PRECHECK = '/^select \* from `chart_of_accounts` where `chart_of_accounts`\.`id` = \?/i';

    public function test_a_transfer_whose_pre_check_went_stale_is_refused_under_the_account_lock()
    {
        $this->flag(true);
        $from = $this->cashAccount(100);
        $to = $this->cashAccount(0);
        $body = fn () => $this->req($this->admin(), route('accounts.balance-transfers.store', [], false), ['transaction_type' => 'transfer', 'date' => now()->toDateString(), 'from_account_id' => $from, 'to_account_id' => $to, 'amount' => 60, 'charge' => 0]);
        $stale = $body();
        $stale['pause_after'] = ['regex' => self::PRECHECK, 'ms' => 2000];
        $res = $this->race([$stale, $body() + ['delay_ms' => 300]], 120);

        $this->assertSame(1, DB::table('balance_transfers')->where('from_account_id', $from)->count(), 'only one 60 fits in 100: ' . json_encode(array_column($res, 'status')));
        $this->assertEquals(40.0, $this->bal($from), 'never negative (before the lock the stale request left it at -20)');
        $this->assertEquals(60.0, $this->bal($to));
        $this->assertJournalSettles();
    }

    public function test_four_simultaneous_transfers_of_60_from_100_let_exactly_one_through()
    {
        $this->flag(true);
        $from = $this->cashAccount(100);
        $to = $this->cashAccount(0);
        $this->race(array_map(fn () => $this->req($this->admin(), route('accounts.balance-transfers.store', [], false), ['transaction_type' => 'transfer', 'date' => now()->toDateString(), 'from_account_id' => $from, 'to_account_id' => $to, 'amount' => 60, 'charge' => 0]), range(1, 4)), 120);
        $this->assertSame(1, DB::table('balance_transfers')->where('from_account_id', $from)->count());
        $this->assertEquals(40.0, $this->bal($from));
        $this->assertJournalSettles();
    }

    public function test_an_expense_whose_pre_check_went_stale_is_refused_under_the_account_lock()
    {
        $this->flag(true);
        $cash = $this->cashAccount(100);
        $expense = $this->account('expense', 'expense', 0);
        $body = fn () => $this->req($this->admin(), route('accounts.expense-payments.store', [], false), ['date' => now()->toDateString(), 'payment_account_id' => $cash, 'expense_account_ids' => [$expense], 'amounts' => [60]]);
        $stale = $body();
        $stale['pause_after'] = ['regex' => self::PRECHECK, 'ms' => 2000];
        $res = $this->race([$stale, $body() + ['delay_ms' => 300]], 120);

        $this->assertSame(1, DB::table('expense_payments')->where('payment_account_id', $cash)->count(), json_encode(array_column($res, 'status')));
        $this->assertEquals(40.0, $this->bal($cash));
        $this->assertEquals(60.0, $this->bal($expense));
        $this->assertJournalSettles();
    }
}
