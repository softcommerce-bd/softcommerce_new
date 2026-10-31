<?php

namespace App\Services\Accounting;

use App\Services\Inventory\InventoryMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * One accounting source of truth for the legacy screens (POS, courier settlement, legacy purchases and supplier payments,
 * customer payments, vouchers, expenses, transfers, purchase returns, order cancellations, ...), via a TRANSACTIONAL OUTBOX.
 *
 * Those screens keep posting exactly as before (they change chart_of_accounts.current_balance). With the confirmed-order flag ON,
 * every such change is also written to `ledger_outbox` INSIDE THE SAME DATABASE TRANSACTION. Therefore:
 *   - a rolled-back operation leaves no outbox row (the database discards it with the rest);
 *   - a committed operation always has its outbox rows, even if the process dies the next instant.
 * After the outermost transaction commits, the group is turned into ONE balanced journal entry (LedgerOutboxProcessor) in its own
 * transaction that also marks the rows processed. If that step never runs (crash, error), `ledger:process-outbox` (scheduled
 * every minute) completes it; a committed business change can no longer stay permanently without its journal entry, and
 * reconciliation reports any row waiting longer than a few minutes.
 * Flag OFF: this class does nothing.
 */
class LegacyLedgerBridge
{
    private static int $depth = 0;
    private static ?string $token = null;
    private static bool $processing = false;
    private static ?bool $outboxExists = null;
    /** Optional [type, id, description] for work done outside a routed request (jobs, console, tests). */
    public static ?array $sourceOverride = null;

    public static function active(): bool
    {
        if (self::$processing || !InventoryMode::confirmedOnly() || !InventoryMode::installed()) {
            return false;
        }

        return self::$outboxExists ??= Schema::hasTable('ledger_outbox');
    }

    /** Test hook: forget cached state between tests. */
    public static function reset(): void
    {
        self::$depth = 0;
        self::$token = null;
        self::$processing = false;
        self::$outboxExists = null;
        self::$sourceOverride = null;
    }

    public static function record(int $accountId, float $delta): void
    {
        if (!self::active() || abs($delta) < 0.005) {
            return;
        }
        try {
            [$type, $id, $desc] = self::source();
            DB::table('ledger_outbox')->insert(['token' => self::$token ??= (string) Str::uuid(), 'account_id' => $accountId, 'delta' => round($delta, 2), 'source_type' => $type, 'source_id' => $id,
                'description' => mb_substr((string) $desc, 0, 250), 'created_by' => auth()->id(), 'created_at' => now()]);
        } catch (\Throwable $e) {
            // never break the legacy operation; the missing row shows up as ledger drift in the reconciliation
            Log::error('Ledger outbox could not record a legacy posting: ' . $e->getMessage(), ['account' => $accountId, 'delta' => $delta]);
        }
    }

    public static function began(): void
    {
        if (self::$depth === 0) {
            self::$token = (string) Str::uuid(); // a new outermost business transaction
        }
        self::$depth++;
    }

    public static function rolledBack(): void
    {
        self::$depth = max(0, self::$depth - 1);
        if (self::$depth === 0) {
            self::$token = null; // the rows went with the rollback
        }
    }

    public static function committed(): void
    {
        self::$depth = max(0, self::$depth - 1);
        if (self::$depth === 0) {
            self::flush();
        }
    }

    /** Processes this request's committed group now. Never throws: anything left is completed by ledger:process-outbox. */
    public static function flush(): void
    {
        if (self::$depth > 0 || self::$processing || self::$token === null) {
            return;
        }
        $token = self::$token;
        self::$token = null;
        self::$processing = true;
        try {
            app(LedgerOutboxProcessor::class)->processToken($token);
        } catch (\Throwable $e) {
            Log::error('Ledger outbox could not journal a committed legacy posting yet (it stays queued): ' . $e->getMessage(), ['token' => $token]);
            try {
                DB::table('ledger_outbox')->where('token', $token)->whereNull('processed_at')->update(['last_error' => mb_substr($e->getMessage(), 0, 250), 'attempts' => DB::raw('attempts + 1')]);
            } catch (\Throwable $ignored) {
            }
        } finally {
            self::$processing = false;
        }
    }

    private static function source(): array
    {
        if (self::$sourceOverride) {
            return self::$sourceOverride;
        }
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            $cmd = $_SERVER['argv'][1] ?? 'console';

            return ['legacy:console:' . $cmd, null, 'Legacy posting by console command ' . $cmd];
        }
        $route = request()->route();
        $name = $route ? ($route->getName() ?: $route->uri()) : 'request';
        $id = null;
        foreach ($route ? $route->parameters() : [] as $p) {
            $v = is_object($p) ? ($p->id ?? null) : $p;
            if (is_numeric($v)) {
                $id = (int) $v;
                break;
            }
        }

        return ['legacy:' . mb_substr($name, 0, 33), $id, 'Legacy posting: ' . $name . ($id ? " #{$id}" : '')];
    }
}
