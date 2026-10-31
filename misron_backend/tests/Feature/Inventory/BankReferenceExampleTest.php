<?php

namespace Tests\Feature\Inventory;

use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

/**
 * B-1 decision support. The four bank actions are DISABLED; this runs the SAME kinds of movement through the live, working
 * balance-transfer screen (deposit / withdraw / transfer, then delete = reversal) on synthetic accounts and records what the system
 * actually does today: numbers, balances, statement lines, reversal lines. docs/T5_B1_ACCOUNTING_EXAMPLE.md quotes this output.
 * Set B1_EVIDENCE=<file> to write the recorded run to a file. It changes nothing but the disposable database (rolled back).
 */
class BankReferenceExampleTest extends InventoryV2TestCase
{
    private function acct(string $name, string $sub, float $bal): ChartOfAccount
    {
        return ChartOfAccount::create(['code' => 'B1-' . uniqid(), 'name' => $name, 'type' => 'asset', 'sub_type' => $sub, 'opening_balance' => 0, 'current_balance' => $bal, 'status' => 'active', 'is_deleted' => 0]);
    }

    private function lines(array $ids): array
    {
        return DB::table('account_statement_logs')->whereIn('account_id', $ids)->orderBy('id')->get(['account_id', 'reference_no', 'transaction_type', 'debit', 'credit'])->map(fn ($r) => (array) $r)->all();
    }

    public function test_reference_run_of_deposit_withdraw_transfer_and_their_reversal()
    {
        $this->actingAs($this->makeAdmin());
        $cash = $this->acct('Cash in Hand', 'cash', 1000);
        $bankA = $this->acct('Bank A', 'bank', 5000);
        $bankB = $this->acct('Bank B', 'bank', 200);
        $ids = [$cash->id, $bankA->id, $bankB->id];
        $date = now()->toDateString();
        $bal = fn () => [(float) $cash->fresh()->current_balance, (float) $bankA->fresh()->current_balance, (float) $bankB->fresh()->current_balance];
        $out = ['start (cash, bankA, bankB)' => $bal()];

        $post = function (string $type, int $from, int $to, float $amount, float $charge = 0) use ($date) {
            $r = $this->post(route('accounts.balance-transfers.store'), ['date' => $date, 'from_account_id' => $from, 'to_account_id' => $to, 'amount' => $amount, 'charge' => $charge, 'transaction_type' => $type, 'note' => 'b1 example']);
            $this->assertContains($r->getStatusCode(), [302], 'redirect after store: ' . substr($r->getContent(), 0, 200));
        };

        $post('deposit', $cash->id, $bankA->id, 300);          // cash -> bank
        $out['after deposit 300 cash->bankA'] = $bal();
        $post('withdraw', $bankA->id, $cash->id, 100, 5);        // bank -> cash, bank charge 5
        $out['after withdraw 100 (+5 charge) bankA->cash'] = $bal();
        $post('transfer', $bankA->id, $bankB->id, 400, 10);      // bank -> bank, charge 10
        $out['after transfer 400 (+10 charge) bankA->bankB'] = $bal();

        $rows = DB::table('balance_transfers')->whereIn('from_account_id', $ids)->orderBy('id')->get();
        $out['numbers'] = $rows->pluck('voucher_no')->all();
        $out['lines after the three postings'] = $this->lines($ids);
        $before = DB::table('account_statement_logs')->whereIn('account_id', $ids)->count();

        foreach ($rows->reverse() as $row) {
            $d = $this->deleteJson(route('accounts.balance-transfers.delete', $row->id));
            $this->assertTrue((bool) $d->json('success'), $d->getContent());
        }
        $out['after deleting all three (reversal)'] = $bal();
        $out['reversal lines added'] = array_slice($this->lines($ids), $before);
        $out['rows still in table (soft-deleted kept)'] = DB::table('balance_transfers')->whereIn('from_account_id', $ids)->count();
        $out['rows visible'] = DB::table('balance_transfers')->whereIn('from_account_id', $ids)->whereNull('deleted_at')->count();

        $this->assertSame([1000.0, 5000.0, 200.0], $bal(), 'everything back to the start');
        $this->assertSame(3, $out['rows still in table (soft-deleted kept)']);
        $this->assertSame(0, $out['rows visible']);
        if ($f = getenv('B1_EVIDENCE')) {
            file_put_contents($f, json_encode($out, JSON_PRETTY_PRINT));
        }
    }
}
