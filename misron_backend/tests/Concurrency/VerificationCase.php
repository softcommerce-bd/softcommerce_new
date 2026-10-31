<?php

namespace Tests\Concurrency;

use App\Models\ChartOfAccount;
use App\Services\Inventory\InventoryMode;
use Illuminate\Support\Facades\DB;

/** Real parallel processes against committed rows, with the confirmed-order flag on inside this process and its workers only. Everything committed is removed again. */
abstract class VerificationCase extends ConcurrencyTestCase
{
    private const EXTRA = ['ledger_outbox', 'journal_entries', 'journal_lines', 'idempotency_keys', 'refund_postings', 'wallets', 'refund_requests', 'account_statement_logs', 'suppliers', 'goods_receipts', 'goods_receipt_lines',
        'order_demands', 'stock_movements', 'stock_allocations', 'purchase_requirement_links', 'supplier_invoices', 'supplier_invoice_payments', 'chart_of_accounts'];
    private array $high = [];
    private array $balances = [];
    private array $userBalances = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (!\Illuminate\Support\Facades\Schema::hasTable('ledger_outbox')) {
            $this->markTestSkipped('inventory_v2 migrations are not applied.');
        }
        putenv('INVENTORY_CONFIRMED_ONLY=true');
        $_ENV['INVENTORY_CONFIRMED_ONLY'] = $_SERVER['INVENTORY_CONFIRMED_ONLY'] = 'true';
        config(['inventory.confirmed_only' => true]);
        InventoryMode::reset();
        \App\Services\Accounting\LegacyLedgerBridge::reset();
        foreach (self::EXTRA as $t) {
            $this->high[$t] = (int) DB::table($t)->max('id');
        }
        $this->balances = DB::table('chart_of_accounts')->pluck('current_balance', 'id')->all();
        $this->userBalances = DB::table('users')->pluck('balance', 'id')->all();
    }

    protected function tearDown(): void
    {
        putenv('INVENTORY_CONFIRMED_ONLY');
        unset($_ENV['INVENTORY_CONFIRMED_ONLY'], $_SERVER['INVENTORY_CONFIRMED_ONLY']);
        config(['inventory.confirmed_only' => false]);
        InventoryMode::reset();
        \App\Services\Accounting\LegacyLedgerBridge::reset();
        \App\Services\Accounting\LedgerOutboxProcessor::$boundaryHook = null;
        foreach (array_reverse(self::EXTRA) as $t) {
            DB::table($t)->where('id', '>', $this->high[$t] ?? PHP_INT_MAX)->delete();
        }
        foreach ($this->balances as $id => $bal) {
            DB::table('chart_of_accounts')->where('id', $id)->update(['current_balance' => $bal]);
        }
        foreach ($this->userBalances as $id => $bal) {
            DB::table('users')->where('id', $id)->update(['balance' => $bal]);
        }
        parent::tearDown();
    }

    protected function account(string $type = 'asset', string $sub = 'cash', float $bal = 1000): int
    {
        return ChartOfAccount::create(['code' => 'VC-' . uniqid(), 'name' => 'VC ' . uniqid(), 'type' => $type, 'sub_type' => $sub, 'opening_balance' => 0, 'current_balance' => $bal, 'status' => 'active', 'is_deleted' => 0])->id;
    }

    /** Commits n legacy-style postings whose journal step fails, i.e. n committed groups waiting in the outbox. @return string[] tokens */
    protected function backlog(int $n, int $from, int $to): array
    {
        $tokens = [];
        for ($i = 0; $i < $n; $i++) {
            $token = (string) \Illuminate\Support\Str::uuid();
            DB::table('ledger_outbox')->insert([
                ['token' => $token, 'account_id' => $from, 'delta' => -10, 'source_type' => 'legacy:test.post', 'source_id' => 5000 + $i, 'description' => 'backlog ' . $i, 'created_at' => now()],
                ['token' => $token, 'account_id' => $to, 'delta' => 10, 'source_type' => 'legacy:test.post', 'source_id' => 5000 + $i, 'description' => 'backlog ' . $i, 'created_at' => now()],
            ]);
            $tokens[] = $token;
        }

        return $tokens;
    }

    /** Starts one process with no barrier and waits; returns its result or null when it died without writing one. */
    protected function runOne(array $job, int $timeout = 60): ?array
    {
        $dir = sys_get_temp_dir() . '/vc-' . uniqid();
        mkdir($dir);
        $job += ['id' => 0, 'go' => "$dir/go", 'ready' => "$dir/ready", 'out' => "$dir/out.json"];
        touch($job['go']);
        file_put_contents("$dir/job.json", json_encode($job));
        $env = array_merge(getenv(), ['DB_DATABASE' => DB::connection()->getDatabaseName(), 'APP_ENV' => 'testing']);
        $p = proc_open([PHP_BINARY, __DIR__ . '/worker.php', "$dir/job.json"], [0 => ['pipe', 'r'], 1 => ['file', "$dir/o.txt", 'w'], 2 => ['file', "$dir/e.txt", 'w']], $pipes, null, $env);
        fclose($pipes[0]);
        $deadline = microtime(true) + $timeout;
        while (proc_get_status($p)['running'] && microtime(true) < $deadline) {
            usleep(5000);
        }
        $status = proc_get_status($p);
        if ($status['running']) {
            proc_terminate($p, 9);
        }
        proc_close($p);
        $res = file_exists($job['out']) ? json_decode(file_get_contents($job['out']), true) : null;
        array_map('unlink', glob("$dir/*"));
        rmdir($dir);

        return $res;
    }
}
