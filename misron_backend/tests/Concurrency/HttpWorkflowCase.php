<?php

namespace Tests\Concurrency;

use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-5: real HTTP requests through the application's kernel, one process per request, authenticated as committed staff users.
 * Everything committed is removed again in tearDown (rows by high-water id, balances restored, role links removed).
 */
abstract class HttpWorkflowCase extends VerificationCase
{
    private const MORE = ['balance_transfers', 'voucher_details', 'vouchers', 'purchase_return_products', 'purchase_returns', 'income_details', 'incomes', 'stock_adjustment_lines', 'expense_payment_details', 'expense_payments', 'purchase_order_details', 'purchase_orders', 'order_payment_mappings', 'customer_payments', 'transaction_methods', 'courier_settlements', 'courier_settlement_details', 'supplier_payments', 'supplier_payment_purchases', 'stock_adjustments', 'addresses', 'model_has_roles', 'purchases', 'purchase_products', 'order_details', 'orders', 'carts', 'users', 'chat_audit_logs', 'funnel_order_attributions'];
    private array $moreHigh = [];
    private array $roleLinks = [];
    private ?string $startedAt = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->startedAt = now()->subSecond()->toDateTimeString();
        foreach (self::MORE as $t) {
            if (Schema::hasTable($t)) {
                $this->moreHigh[$t] = (int) DB::table($t)->max($t === 'model_has_roles' ? 'role_id' : 'id');
            }
        }
        $this->roleLinks = DB::table('model_has_roles')->pluck('model_id')->all();
    }

    protected function tearDown(): void
    {
        foreach (['order_payment_mappings', 'customer_payments', 'transaction_methods', 'balance_transfers', 'voucher_details', 'vouchers', 'purchase_return_products', 'purchase_returns', 'income_details', 'incomes', 'stock_adjustment_lines', 'expense_payment_details', 'expense_payments', 'purchase_order_details', 'purchase_orders', 'courier_settlement_details', 'courier_settlements', 'supplier_payment_purchases', 'supplier_payments', 'stock_adjustments', 'addresses', 'carts', 'chat_audit_logs', 'funnel_order_attributions'] as $t) {
            if (Schema::hasTable($t) && isset($this->moreHigh[$t])) {
                DB::table($t)->where('id', '>', $this->moreHigh[$t])->delete();
            }
        }
        if ($this->startedAt && Schema::hasTable('notifications')) {
            DB::table('notifications')->where('created_at', '>=', $this->startedAt)->delete(); // uuid keys: by time, disposable database only
        }
        $created = DB::table('users')->where('id', '>', $this->moreHigh['users'] ?? PHP_INT_MAX)->pluck('id');
        DB::table('model_has_roles')->where('model_type', \App\Models\User::class)->whereIn('model_id', $created)->delete();
        parent::tearDown();
    }

    protected function flag(bool $on): void
    {
        $v = $on ? 'true' : 'false';
        putenv('INVENTORY_CONFIRMED_ONLY=' . $v);
        $_ENV['INVENTORY_CONFIRMED_ONLY'] = $_SERVER['INVENTORY_CONFIRMED_ONLY'] = $v;
        config(['inventory.confirmed_only' => $on]);
        \App\Services\Inventory\InventoryMode::reset();
        \App\Services\Accounting\LegacyLedgerBridge::reset();
    }

    protected function admin(): int
    {
        $id = (int) DB::table('users')->insertGetId(['name' => 'Admin ' . uniqid(), 'email' => uniqid() . '@par.test', 'password' => bcrypt('x'), 'user_type' => 'admin', 'banned' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $role = \Spatie\Permission\Models\Role::findByName('Super Admin', 'web');
        \App\Models\User::find($id)->assignRole($role);

        return $id;
    }

    protected function cashAccount(float $balance = 0): int
    {
        return $this->account('asset', 'cash', $balance);
    }

    /** a request as a given staff user; many of them at once with race() */
    protected function req(int $user, string $uri, array $body = [], string $method = 'POST'): array
    {
        return ['action' => 'http_as', 'user_id' => $user, 'uri' => $uri, 'method' => $method, 'body' => $body];
    }

    protected function systemAccounts(): void
    {
        foreach ([['Sales', 'income', 'sales'], ['Accounts Receivable', 'asset', 'receivable'], ['Courier Income', 'income', 'income'], ['Cost of Goods Sold', 'expense', 'cost_of_sales'], ['Inventory', 'asset', 'inventory']] as [$n, $t, $s]) {
            if (!ChartOfAccount::where('name', $n)->exists()) {
                ChartOfAccount::create(['code' => 'HW-' . strtoupper(substr($n, 0, 3)) . uniqid(), 'name' => $n, 'type' => $t, 'sub_type' => $s, 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0]);
            }
        }
    }

    protected function bal(int $accountId): float
    {
        return (float) DB::table('chart_of_accounts')->where('id', $accountId)->value('current_balance');
    }

    /**
     * After the requests: whatever the post-commit journalling could not finish at the time (lock contention on a shared account) is completed by
     * the scheduled `ledger:process-outbox`; run it as the scheduler would, then nothing may be left and the journal must balance.
     * @return int rows that were still waiting when the requests finished (informational: the recovery path was needed)
     */
    protected function assertJournalSettles(): int
    {
        $waiting = DB::table('ledger_outbox')->whereNull('processed_at')->count();
        app(\App\Services\Accounting\LedgerOutboxProcessor::class)->processPending(500);
        $this->assertSame(0, DB::table('ledger_outbox')->whereNull('processed_at')->count(), 'nothing left waiting after the scheduled processor ran');
        $this->assertTrue(\App\Services\Accounting\JournalService::trialBalance(now()->toDateString())['balanced'], 'the journal balances');
        $dupes = DB::table('journal_entries')->groupBy('idempotency_key')->havingRaw('COUNT(*) > 1')->count();
        $this->assertSame(0, $dupes, 'no entry was journalled twice');

        return $waiting;
    }
}
