<?php

namespace Tests\Feature\Inventory;

use App\Http\Controllers\BankTransactionController;
use App\Models\BankTransaction;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Decision B-1.1 (APPROVED): the four duplicate bank actions (plus cash-out) are RETIRED. They must not post anything through any route or
 * direct request, history must stay readable and intact, and the balance-transfer workflow (the supported mechanism) must keep working
 * (its own behaviour is exercised by BankReferenceExampleTest and the existing transfer tests).
 */
class BankActionsRetiredTest extends InventoryV2TestCase
{
    private function acct(float $bal): ChartOfAccount
    {
        return ChartOfAccount::create(['code' => 'BK-' . uniqid(), 'name' => 'BK ' . uniqid(), 'type' => 'asset', 'sub_type' => 'bank', 'opening_balance' => 0, 'current_balance' => $bal, 'status' => 'active', 'is_deleted' => 0]);
    }

    private function history(ChartOfAccount $a, ChartOfAccount $b, string $no = 'OLD-BANK-1'): int
    {
        return DB::table('bank_transactions')->insertGetId(['transaction_no' => $no, 'type' => 'transfer', 'from_account_id' => $a->id, 'to_account_id' => $b->id, 'amount' => 75, 'transaction_date' => '2025-01-15', 'status' => 'completed', 'description' => 'historical', 'created_by' => $this->makeAdmin()->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function writes(callable $do): array
    {
        $seen = [];
        DB::listen(function ($q) use (&$seen) {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|create|drop|truncate|savepoint|rollback|commit|begin|start)/i', $q->sql)) {
                $seen[] = $q->sql;
            }
        });
        $do();

        return $seen;
    }

    public function test_every_retired_action_is_refused_without_any_write_whatever_the_input()
    {
        $this->actingAs($this->makeAdmin());
        $a = $this->acct(500);
        $b = $this->acct(0);
        $date = now()->toDateString();
        $valid = ['account_id' => $a->id, 'from_account_id' => $a->id, 'to_account_id' => $b->id, 'amount' => 50, 'transaction_date' => $date, 'description' => 't', 'cheque_no' => '1', 'cheque_date' => $date, 'clearing_date' => $date];
        $logs = DB::table('account_statement_logs')->count();
        $journal = DB::table('journal_entries')->count();
        $transfers = DB::table('balance_transfers')->count();
        $hist = $this->history($a, $b);

        foreach (['deposit', 'withdraw', 'transfer', 'cash-out', 'cheque-clearing'] as $action) {
            foreach ([$valid, [], ['amount' => -5], ['account_id' => 999999999]] as $i => $body) {
                $seen = $this->writes(function () use ($action, $body, &$res) {
                    $res = $this->postJson(route('accounts.bank-transactions.' . $action), $body);
                });
                $this->assertSame(410, $res->getStatusCode(), "$action body#$i is refused as retired, before validation: " . substr($res->getContent(), 0, 160));
                $this->assertTrue($res->json('retired'));
                $this->assertFalse($res->json('success'));
                $this->assertStringContainsString('balance-transfers', (string) $res->json('use'), 'the refusal points to the supported workflow');
                $this->assertSame([], $seen, "$action body#$i ran no write, no transaction, no savepoint");
            }
        }
        $this->assertEquals(500.0, (float) $a->fresh()->current_balance);
        $this->assertEquals(0.0, (float) $b->fresh()->current_balance);
        $this->assertSame($logs, DB::table('account_statement_logs')->count());
        $this->assertSame($journal, DB::table('journal_entries')->count());
        $this->assertSame($transfers, DB::table('balance_transfers')->count());
        $this->assertSame(1, DB::table('bank_transactions')->count(), 'only the historical row exists; nothing was added');
        $this->assertSame('OLD-BANK-1', DB::table('bank_transactions')->where('id', $hist)->value('transaction_no'));
    }

    public function test_no_other_route_or_code_path_can_write_a_bank_transaction()
    {
        // 1. every non-read route that reaches the controller is one of the five retired methods
        $retired = ['deposit', 'withdraw', 'transfer', 'cashOut', 'chequeClearing'];
        $seen = [];
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if (!str_starts_with($action, BankTransactionController::class . '@')) {
                continue;
            }
            $method = substr($action, strlen(BankTransactionController::class) + 1);
            $writes = array_diff($route->methods(), ['GET', 'HEAD']);
            if ($writes) {
                $this->assertContains($method, $retired, "$action is the only kind of write route allowed: a retired one");
                $seen[] = $method;
            } else {
                $this->assertContains($method, ['index', 'getAccounts', 'show', 'export'], "$action is a read route");
            }
        }
        sort($seen);
        $this->assertSame(['cashOut', 'chequeClearing', 'deposit', 'transfer', 'withdraw'], $seen);

        // 2. no source file creates, updates or deletes a BankTransaction
        $hits = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app'), \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->getExtension() !== 'php' || str_ends_with($f->getPathname(), 'Models/BankTransaction.php')) {
                continue;
            }
            if (preg_match('/BankTransaction::(create|insert|updateOrCreate|firstOrCreate|query\(\)->(update|delete|insert)|where[^;]*->(update|delete))|new BankTransaction|table\([\'"]bank_transactions[\'"]\)->(insert|update|delete)/', file_get_contents($f->getPathname()))) {
                $hits[] = $f->getPathname();
            }
        }
        $this->assertSame([], $hits, 'nothing in app/ writes bank_transactions');

        // 3. no screen still posts to a retired action
        $views = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('resources/views'), \FilesystemIterator::SKIP_DOTS)) as $f) {
            if (preg_match('/bank-transactions\.(deposit|withdraw|transfer|cash-out|cheque-clearing)/', file_get_contents($f->getPathname()))) {
                $views[] = $f->getPathname();
            }
        }
        $this->assertSame([], $views, 'no view calls a retired action');
    }

    public function test_history_is_readable_and_the_model_refuses_to_change_or_remove_it()
    {
        $this->actingAs($this->makeAdmin());
        $a = $this->acct(10);
        $b = $this->acct(20);
        $id = $this->history($a, $b, 'OLD-BANK-2');

        $list = $this->get(route('accounts.bank-transactions.index'));
        $this->assertSame(200, $list->getStatusCode());
        $list->assertSee('OLD-BANK-2');
        $list->assertSee(route('accounts.balance-transfers.create'), false);
        $list->assertDontSee('openBankModal');
        $show = $this->getJson(route('accounts.bank-transactions.show', $id));
        $this->assertSame(200, $show->getStatusCode());
        $this->assertSame('OLD-BANK-2', $show->json('data.transaction_no'));
        $this->assertSame($a->id, $show->json('data.from_account.id'), 'relations work for history');

        $model = BankTransaction::findOrFail($id);
        foreach ([fn () => BankTransaction::create(['transaction_no' => 'X', 'type' => 'deposit', 'amount' => 1]), fn () => $model->update(['amount' => 1]), fn () => $model->delete(), fn () => (new BankTransaction())->forceFill(['transaction_no' => 'Y'])->save()] as $i => $try) {
            try {
                $try();
                $this->fail("model operation #$i must be refused");
            } catch (\LogicException $e) {
                $this->assertStringContainsString('retired', $e->getMessage());
            } catch (\Illuminate\Database\Eloquent\MassAssignmentException $e) {
                $this->assertContains($i, [0, 1], 'mass-assigned create() and update() are stopped earlier, by the closed $guarded');
            }
        }
        $row = DB::table('bank_transactions')->where('id', $id)->first();
        $this->assertSame('OLD-BANK-2', $row->transaction_no);
        $this->assertEquals(75.0, (float) $row->amount);
        $this->assertNull($row->deleted_at);
        $this->assertSame(1, DB::table('bank_transactions')->count());
    }

    public function test_the_supported_balance_transfer_workflow_still_posts_and_reverses()
    {
        $this->actingAs($this->makeAdmin());
        $a = $this->acct(500);
        $b = $this->acct(0);
        $r = $this->post(route('accounts.balance-transfers.store'), ['date' => now()->toDateString(), 'from_account_id' => $a->id, 'to_account_id' => $b->id, 'amount' => 120, 'charge' => 0, 'transaction_type' => 'deposit', 'note' => 'supported path']);
        $this->assertSame(302, $r->getStatusCode());
        $this->assertEquals([380.0, 120.0], [(float) $a->fresh()->current_balance, (float) $b->fresh()->current_balance]);
        $id = DB::table('balance_transfers')->where('from_account_id', $a->id)->value('id');
        $this->assertTrue((bool) $this->deleteJson(route('accounts.balance-transfers.delete', $id))->json('success'));
        $this->assertEquals([500.0, 0.0], [(float) $a->fresh()->current_balance, (float) $b->fresh()->current_balance]);
    }
}
