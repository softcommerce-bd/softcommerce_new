<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Services\Inventory\InventoryMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * One accounting source of truth for the legacy screens (POS, courier settlement, legacy purchases and supplier payments,
 * customer payments/refunds, vouchers, expenses, transfers, order cancellations, ...).
 *
 * Those screens keep posting the way they always did (they change chart_of_accounts.current_balance and write their own statement
 * rows). With the confirmed-order flag ON, every balance change they make is captured and, when the outermost database transaction
 * commits, written as ONE balanced journal entry whose lines are marked "mirrored": the journal records what the legacy code
 * already did, and neither the balance nor the statement log is touched a second time. A rolled-back legacy operation leaves nothing.
 *
 * Legacy posting is not always double-entry (for example the old purchase screen debits Inventory and the supplier-advance
 * account but credits only the payable and cash). Any one-sided remainder is journalled against the visible
 * "Legacy Posting Suspense" account instead of being hidden or blocking the sale; reconciliation reports its balance.
 * Reversals (cancel, delete, refund) are new entries linked with reversal_of_id when they exactly negate an earlier entry for the same document.
 * Flag OFF: this class does nothing.
 */
class LegacyLedgerBridge
{
    /** @var array<int, array{0:int,1:float}> account id and delta (in the account's own balance units) */
    private static array $pending = [];
    /** @var int[] pending length when each open transaction began */
    private static array $marks = [];
    private static bool $flushing = false;
    /** Optional [type, id, description] for work done outside a routed request (jobs, console, tests). */
    public static ?array $sourceOverride = null;

    public static function active(): bool
    {
        return !self::$flushing && (InventoryMode::confirmedOnly() && InventoryMode::installed());
    }

    /** Test hook: forget cached state between tests. */
    public static function reset(): void
    {
        self::$pending = [];
        self::$marks = [];
        self::$flushing = false;
        self::$sourceOverride = null;
    }

    public static function record(int $accountId, float $delta): void
    {
        if (self::active() && abs($delta) >= 0.005) {
            self::$pending[] = [$accountId, $delta];
        }
    }

    public static function began(): void
    {
        self::$marks[] = count(self::$pending);
    }

    public static function rolledBack(): void
    {
        $mark = array_pop(self::$marks);
        if ($mark !== null) {
            self::$pending = array_slice(self::$pending, 0, $mark);
        }
    }

    public static function committed(): void
    {
        array_pop(self::$marks);
        if (!self::$marks) {
            self::flush();
        }
    }

    /** Writes what is pending as one entry. Never throws into the legacy operation: a failure is logged and shows up as ledger drift. */
    public static function flush(): void
    {
        if (!self::$pending || self::$flushing) {
            return;
        }
        $pending = self::$pending;
        self::$pending = [];
        self::$flushing = true;
        try {
            self::post($pending);
        } catch (\Throwable $e) {
            Log::error('Legacy ledger bridge could not journal a legacy posting: ' . $e->getMessage(), ['deltas' => $pending]);
        } finally {
            self::$flushing = false;
        }
    }

    private static function post(array $pending): void
    {
        $net = [];
        foreach ($pending as [$id, $delta]) {
            $net[$id] = ($net[$id] ?? 0) + (int) round($delta * 100);
        }
        $types = ChartOfAccount::withTrashed()->whereIn('id', array_keys($net))->pluck('type', 'id');
        $lines = [];
        $debit = $credit = 0;
        ksort($net);
        foreach ($net as $id => $cents) {
            if ($cents === 0 || !isset($types[$id])) {
                continue;
            }
            $debitNormal = in_array($types[$id], ['asset', 'expense'], true);
            $isDebit = $debitNormal ? $cents > 0 : $cents < 0;
            $amount = abs($cents);
            $lines[] = ['account' => $id, $isDebit ? 'debit' : 'credit' => $amount, 'memo' => 'Legacy posting'];
            $isDebit ? $debit += $amount : $credit += $amount;
        }
        if (!$lines) {
            return;
        }
        $mirrored = array_column($lines, 'account');
        if ($debit !== $credit) {
            $lines[] = ['account' => SystemAccounts::id('suspense'), $debit > $credit ? 'credit' : 'debit' => abs($debit - $credit), 'memo' => 'One-sided legacy posting (no counter-account in the old screen)'];
        }

        [$sourceType, $sourceId, $description] = self::source();
        DB::table('journal_entries'); // table presence is part of active()
        $reversalOf = self::findReversed($lines, $sourceType, $sourceId);
        app(JournalService::class)->postMirrored('legacy:' . Str::uuid(), now()->toDateString(), $lines, $mirrored, $sourceType, $sourceId, $description, auth()->id(), $reversalOf);
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

    /** An earlier, not yet reversed legacy entry of the same document whose lines this entry exactly negates. */
    private static function findReversed(array $lines, string $sourceType, ?int $sourceId): ?int
    {
        if (!$sourceId) {
            return null;
        }
        $sig = fn (array $rows) => collect($rows)->map(fn ($l) => $l['account'] . ':' . (($l['debit'] ?? 0) - ($l['credit'] ?? 0)))->sort()->values()->all();
        $negated = $sig(array_map(fn ($l) => ['account' => $l['account'], 'debit' => $l['credit'] ?? 0, 'credit' => $l['debit'] ?? 0], $lines));
        $candidates = DB::table('journal_entries as e')->where('e.source_id', $sourceId)->where('e.source_type', 'like', 'legacy:%')->whereNull('e.reversal_of_id')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('journal_entries as r')->whereColumn('r.reversal_of_id', 'e.id'))->orderByDesc('e.id')->limit(20)->pluck('e.id');
        foreach ($candidates as $id) {
            $rows = DB::table('journal_lines')->where('journal_entry_id', $id)->get()->map(fn ($l) => ['account' => (int) $l->account_id, 'debit' => (int) round($l->debit * 100), 'credit' => (int) round($l->credit * 100)])->all();
            if ($sig($rows) === $negated) {
                return (int) $id;
            }
        }

        return null;
    }
}
