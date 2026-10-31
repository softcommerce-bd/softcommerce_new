<?php

namespace Tests\Feature\Inventory;

use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

/**
 * T-5 Phase B: controllers that CATCH the exception and answer with a redirect + flashed message used to defeat the retry (the redirect has no
 * body text). The middleware now reads the flash. Each test injects a real-shaped deadlock into the controller's transaction:
 *   once   -> retried (X-Deadlock-Retries: 1), document exists exactly once, no stale "Deadlock" message flashed;
 *   always -> exactly three attempts, the user still gets today's flashed error, nothing remains.
 */
class DeadlockRetryFlashCoverageTest extends InventoryV2TestCase
{
    private function account(string $name, float $balance, string $type = 'asset', string $sub = 'cash'): ChartOfAccount
    {
        return ChartOfAccount::create(['code' => 'FC-' . $name . uniqid(), 'name' => 'FC ' . $name . uniqid(), 'type' => $type, 'sub_type' => $sub, 'opening_balance' => 0, 'current_balance' => $balance, 'status' => 'active', 'is_deleted' => 0]);
    }

    private function transferBody(ChartOfAccount $from, ChartOfAccount $to): array
    {
        return ['transaction_type' => 'transfer', 'date' => now()->toDateString(), 'from_account_id' => $from->id, 'to_account_id' => $to->id, 'amount' => 100, 'charge' => 0];
    }

    public function test_balance_transfer_store_is_retried_after_one_deadlock_and_moves_the_money_once()
    {
        $this->actingAs($this->makeAdmin());
        $from = $this->account('a', 500);
        $to = $this->account('b', 0);
        $inj = DeadlockInjector::once('/^insert into `account_statement_logs`/i');
        $res = $this->post(route('accounts.balance-transfers.store'), $this->transferBody($from, $to));
        $this->assertSame(1, $inj->thrown);
        $this->assertSame('1', $res->headers->get('X-Deadlock-Retries'), 'the redirect answer was recognised as a deadlock and retried');
        $this->assertSame(1, DB::table('balance_transfers')->where('from_account_id', $from->id)->count(), 'one transfer');
        $this->assertEquals(400.0, (float) $from->fresh()->current_balance);
        $this->assertEquals(100.0, (float) $to->fresh()->current_balance);
        $this->assertNull(session('error'), 'the failed attempt\'s flashed error was removed');
        $this->assertNotNull(session('success'), 'the success message of the retried attempt is shown');
    }

    public function test_balance_transfer_store_gives_up_after_three_attempts_and_still_shows_todays_error()
    {
        $this->actingAs($this->makeAdmin());
        $from = $this->account('a', 500);
        $to = $this->account('b', 0);
        $inj = DeadlockInjector::always('/^insert into `account_statement_logs`/i');
        $res = $this->post(route('accounts.balance-transfers.store'), $this->transferBody($from, $to));
        $this->assertSame(3, $inj->thrown, 'exactly three attempts');
        $this->assertNull($res->headers->get('X-Deadlock-Retries'));
        $this->assertStringContainsString('Deadlock', (string) session('error'), 'today\'s behaviour on a final failure: the flashed error');
        $this->assertSame(0, DB::table('balance_transfers')->where('from_account_id', $from->id)->count());
        $this->assertEquals(500.0, (float) $from->fresh()->current_balance);
        $this->assertEquals(0.0, (float) $to->fresh()->current_balance);
    }

    public function test_income_store_is_retried_after_one_deadlock_and_books_the_income_once()
    {
        $this->actingAs($this->makeAdmin());
        $cash = $this->account('cash', 0);
        $income = $this->account('inc', 0, 'income', 'income');
        $inj = DeadlockInjector::once('/^insert into `account_statement_logs`/i');
        $res = $this->post(route('accounts.incomes.store'), ['date' => now()->toDateString(), 'received_account_id' => $cash->id, 'income_account_ids' => [$income->id], 'amounts' => [250]]);
        $this->assertSame(1, $inj->thrown);
        $this->assertSame('1', $res->headers->get('X-Deadlock-Retries'));
        $this->assertSame(1, DB::table('incomes')->where('received_account_id', $cash->id)->count());
        $this->assertEquals(250.0, (float) $cash->fresh()->current_balance, 'received once');
        $this->assertEquals(250.0, (float) $income->fresh()->current_balance, 'income recognised once');
    }

    public function test_income_store_gives_up_after_three_attempts_with_nothing_booked()
    {
        $this->actingAs($this->makeAdmin());
        $cash = $this->account('cash', 0);
        $income = $this->account('inc', 0, 'income', 'income');
        $inj = DeadlockInjector::always('/^insert into `account_statement_logs`/i');
        $this->post(route('accounts.incomes.store'), ['date' => now()->toDateString(), 'received_account_id' => $cash->id, 'income_account_ids' => [$income->id], 'amounts' => [250]]);
        $this->assertSame(3, $inj->thrown);
        $this->assertSame(0, DB::table('incomes')->where('received_account_id', $cash->id)->count());
        $this->assertEquals(0.0, (float) $cash->fresh()->current_balance);
        $this->assertEquals(0.0, (float) $income->fresh()->current_balance);
    }

    private function journalBody(ChartOfAccount $dr, ChartOfAccount $cr): array
    {
        return ['date' => now()->toDateString(), 'type' => 'JV', 'account_ids' => [$dr->id, $cr->id], 'debits' => [75, 0], 'credits' => [0, 75]];
    }

    public function test_voucher_store_is_retried_after_one_deadlock_and_posts_the_lines_once()
    {
        $this->actingAs($this->makeAdmin());
        $dr = $this->account('dr', 0);
        $cr = $this->account('cr', 200, 'liability', 'payable');
        $inj = DeadlockInjector::once('/^insert into `account_statement_logs`/i');
        $res = $this->post(route('accounts.vouchers.store'), $this->journalBody($dr, $cr));
        $this->assertSame(1, $inj->thrown);
        $this->assertSame('1', $res->headers->get('X-Deadlock-Retries'));
        $this->assertSame(1, DB::table('voucher_details')->where('account_id', $dr->id)->count(), 'one debit line');
        $this->assertSame(1, DB::table('vouchers')->where('type', 'JV')->count());
        $this->assertSame(0, DB::table('vouchers')->whereNotNull('deleted_at')->count());
    }

    public function test_voucher_store_gives_up_after_three_attempts_with_nothing_posted()
    {
        $this->actingAs($this->makeAdmin());
        $dr = $this->account('dr', 0);
        $cr = $this->account('cr', 200, 'liability', 'payable');
        $before = [DB::table('vouchers')->count(), (float) $dr->fresh()->current_balance, (float) $cr->fresh()->current_balance];
        $inj = DeadlockInjector::always('/^insert into `account_statement_logs`/i');
        $this->post(route('accounts.vouchers.store'), $this->journalBody($dr, $cr));
        $this->assertSame(3, $inj->thrown);
        $this->assertSame($before, [DB::table('vouchers')->count(), (float) $dr->fresh()->current_balance, (float) $cr->fresh()->current_balance]);
    }
}
