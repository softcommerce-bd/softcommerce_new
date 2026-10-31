<?php

namespace Tests\Feature\Inventory;

use App\Models\ChartOfAccount;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\LegacyLedgerBridge;
use App\Services\Accounting\SystemAccounts;
use Illuminate\Support\Facades\DB;

/** Every balance change the legacy screens make becomes one balanced, traceable journal entry, without posting twice. */
class LegacyLedgerBridgeTest extends InventoryV2TestCase
{
    private function account(string $type, string $sub, float $bal = 1000): ChartOfAccount
    {
        return ChartOfAccount::create(['code' => 'T-' . uniqid(), 'name' => 'T ' . uniqid(), 'type' => $type, 'sub_type' => $sub, 'opening_balance' => 0, 'current_balance' => $bal, 'status' => 'active', 'is_deleted' => 0]);
    }

    public function test_a_saved_balance_change_is_journalled_once_balanced_without_touching_the_balance_again()
    {
        $cash = $this->account('asset', 'cash');
        $sales = $this->account('income', 'sales', 0);
        DB::transaction(function () use ($cash, $sales) {
            $c = ChartOfAccount::find($cash->id); $c->current_balance += 250; $c->save();
            $s = ChartOfAccount::find($sales->id); $s->current_balance += 250; $s->save();
        });
        $this->assertEquals(1250.0, (float) $cash->fresh()->current_balance, 'the legacy posting stands, not doubled');
        $this->assertEquals(250.0, (float) $sales->fresh()->current_balance);
        $e = DB::table('journal_entries')->latest('id')->first();
        $lines = DB::table('journal_lines')->where('journal_entry_id', $e->id)->get();
        $this->assertCount(2, $lines);
        $this->assertSame(2, (int) $lines->sum('mirrored'));
        $this->assertEquals($lines->sum('debit'), $lines->sum('credit'));
        $this->assertSame(0, DB::table('account_statement_logs')->where('transaction_type', 'journal')->whereIn('account_id', [$cash->id, $sales->id])->count(), 'no second statement row');
    }

    public function test_builder_increments_are_captured_and_a_one_sided_posting_goes_to_the_visible_suspense_account()
    {
        $cash = $this->account('asset', 'cash');
        $before = (float) DB::table('chart_of_accounts')->where('id', SystemAccounts::id('suspense'))->value('current_balance');
        DB::transaction(fn () => ChartOfAccount::where('id', $cash->id)->decrement('current_balance', 40));
        $this->assertEquals(960.0, (float) $cash->fresh()->current_balance);
        $e = DB::table('journal_entries')->latest('id')->first();
        $this->assertEquals(0.0, (float) DB::table('journal_lines')->where('journal_entry_id', $e->id)->sum('debit') - (float) DB::table('journal_lines')->where('journal_entry_id', $e->id)->sum('credit'));
        $this->assertEquals($before - 40, (float) DB::table('chart_of_accounts')->where('id', SystemAccounts::id('suspense'))->value('current_balance'), 'cash left with no counter-account: the credit-normal suspense account is debited 40, visibly');
        $this->assertTrue(JournalService::trialBalance(now()->toDateString())['balanced']);
    }

    public function test_a_rolled_back_legacy_operation_leaves_no_journal_entry()
    {
        $cash = $this->account('asset', 'cash');
        $n = DB::table('journal_entries')->count();
        try {
            DB::transaction(function () use ($cash) {
                $c = ChartOfAccount::find($cash->id); $c->current_balance += 5; $c->save();
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException $e) {
        }
        $this->assertSame($n, DB::table('journal_entries')->count());
        $this->assertEquals(1000.0, (float) $cash->fresh()->current_balance);
    }

    public function test_an_exact_negation_is_linked_as_a_reversal_of_the_earlier_document_entry()
    {
        $cash = $this->account('asset', 'cash');
        $exp = $this->account('expense', 'expense', 0);
        $move = function (int $sign) use ($cash, $exp) {
            DB::transaction(function () use ($cash, $exp, $sign) {
                ChartOfAccount::where('id', $cash->id)->decrement('current_balance', 30 * $sign);
                ChartOfAccount::where('id', $exp->id)->increment('current_balance', 30 * $sign);
            });
        };
        LegacyLedgerBridge::$sourceOverride = ['legacy:order.cancel', 77, 'Legacy posting: order.cancel #77'];
        $move(1);
        $first = DB::table('journal_entries')->latest('id')->first();
        $this->assertNull($first->reversal_of_id);
        $move(-1);
        $second = DB::table('journal_entries')->latest('id')->first();
        $this->assertSame((int) $first->id, (int) $second->reversal_of_id, 'the exact negation for the same document is linked to the entry it undoes');
        $move(1); // a new, different posting is not mistaken for a reversal
        $this->assertNull(DB::table('journal_entries')->latest('id')->first()->reversal_of_id);
    }

    public function test_nothing_happens_with_the_flag_off()
    {
        config(['inventory.confirmed_only' => false]);
        \App\Services\Inventory\InventoryMode::reset();
        $cash = $this->account('asset', 'cash');
        $n = DB::table('journal_entries')->count();
        DB::transaction(function () use ($cash) { $c = ChartOfAccount::find($cash->id); $c->current_balance += 9; $c->save(); });
        $this->assertSame($n, DB::table('journal_entries')->count());
    }
}
