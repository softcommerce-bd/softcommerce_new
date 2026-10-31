<?php

namespace Tests\Feature\Inventory;

use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

/**
 * Decision B-1 is PENDING: the four bank actions (deposit, withdraw, transfer, cheque clearing, plus cash-out which calls withdraw) must not post
 * anything until the numbering specification is approved and implemented. Today they fail before any write because
 * BankTransaction::generateTransactionNo() is defined nowhere. This test keeps that true: no bank transaction row, no balance movement, no statement line.
 * When the numbering is approved and implemented, this test is to be replaced by the real tests of the four actions.
 */
class BankActionsStayDisabledTest extends InventoryV2TestCase
{
    private function acct(float $bal): ChartOfAccount
    {
        return ChartOfAccount::create(['code' => 'BK-' . uniqid(), 'name' => 'BK ' . uniqid(), 'type' => 'asset', 'sub_type' => 'bank', 'opening_balance' => 0, 'current_balance' => $bal, 'status' => 'active', 'is_deleted' => 0]);
    }

    public function test_no_bank_action_posts_anything()
    {
        $this->actingAs($this->makeAdmin());
        $a = $this->acct(500);
        $b = $this->acct(0);
        $date = now()->toDateString();
        $calls = [
            'deposit' => ['accounts.bank-transactions.deposit', ['account_id' => $a->id, 'amount' => 50, 'transaction_date' => $date, 'description' => 't']],
            'withdraw' => ['accounts.bank-transactions.withdraw', ['account_id' => $a->id, 'amount' => 50, 'transaction_date' => $date, 'description' => 't']],
            'cash-out' => ['accounts.bank-transactions.cash-out', ['account_id' => $a->id, 'amount' => 50, 'transaction_date' => $date, 'description' => 't']],
            'transfer' => ['accounts.bank-transactions.transfer', ['from_account_id' => $a->id, 'to_account_id' => $b->id, 'amount' => 50, 'transaction_date' => $date, 'description' => 't']],
            'cheque-clearing' => ['accounts.bank-transactions.cheque-clearing', ['account_id' => $a->id, 'amount' => 50, 'transaction_date' => $date, 'description' => 't', 'cheque_no' => '1', 'cheque_date' => $date, 'clearing_date' => $date]],
        ];
        $logsBefore = DB::table('account_statement_logs')->count();
        foreach ($calls as $name => [$route, $body]) {
            $res = $this->postJson(route($route), $body);
            $this->assertSame(500, $res->getStatusCode(), "$name passes validation and then fails on the missing number generator, not on its input: " . substr($res->getContent(), 0, 200));
            $this->assertStringContainsString('generateTransactionNo', (string) $res->getContent(), "$name fails for the documented reason");
            $this->assertNotTrue($res->json('success'), "$name must not report success");
        }
        $this->assertSame(0, DB::table('bank_transactions')->count(), 'no bank transaction row');
        $this->assertEquals(500.0, (float) $a->fresh()->current_balance);
        $this->assertEquals(0.0, (float) $b->fresh()->current_balance);
        $this->assertSame($logsBefore, DB::table('account_statement_logs')->count(), 'no statement line');
        $this->assertFalse(method_exists(\App\Models\BankTransaction::class, 'generateTransactionNo'), 'the numbering is not implemented: B-1 is pending and no format may be invented');
    }
}
