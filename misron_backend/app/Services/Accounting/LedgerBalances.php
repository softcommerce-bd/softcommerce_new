<?php

namespace App\Services\Accounting;

use App\Services\Inventory\InventoryMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dated balances for the financial reports, from ONE consistent source.
 *
 *   movement of an account in a period =
 *       statement-log rows dated in the period that were written BEFORE journalling began (the legacy history, untouched)
 *     + journal lines of entries dated in the period that were posted AFTER the baseline (the bridge journals every legacy
 *       posting and the new flows post natively, so this is complete from the baseline on)
 *
 * Statement rows written after the baseline are skipped: the journal already holds the same movement (native journal rows
 * and the legacy rows the bridge mirrored), so nothing is counted twice.
 * Without a baseline, or with the flag off, it is the legacy statement-log movement only: identical to the old reports.
 * Balance as of a date = current_balance minus every movement dated after it.
 * Debit-normal accounts (asset, expense) are debit - credit; the rest credit - debit.
 */
class LedgerBalances
{
    private const DEBIT_NORMAL = ['asset', 'expense'];

    /** The moment journalling began, or null when reports should use the statement logs only. */
    public static function baselineAt(): ?string
    {
        if (!InventoryMode::confirmedOnly() || !Schema::hasTable('ledger_baselines')) {
            return null;
        }

        return DB::table('ledger_baselines')->min('taken_at');
    }

    /** @return array<int,float> account id => (debit - credit) dated within [from, to] (null = open ended) */
    public static function netDebitByAccount(?string $from, ?string $to, ?array $accountIds = null): array
    {
        $baseline = self::baselineAt();
        $logs = DB::table('account_statement_logs')->when($accountIds, fn ($q) => $q->whereIn('account_id', $accountIds))
            ->when($from, fn ($q) => $q->whereDate('transaction_date', '>=', $from))->when($to, fn ($q) => $q->whereDate('transaction_date', '<=', $to))
            ->when($baseline, fn ($q) => $q->where('created_at', '<', $baseline))
            ->groupBy('account_id')->selectRaw('account_id, SUM(debit) - SUM(credit) AS net')->pluck('net', 'account_id')->all();
        $out = array_map('floatval', $logs);
        if ($baseline && Schema::hasTable('journal_lines')) {
            $j = DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->where('e.posted_at', '>=', $baseline)
                ->when($accountIds, fn ($q) => $q->whereIn('l.account_id', $accountIds))
                ->when($from, fn ($q) => $q->whereDate('e.entry_date', '>=', $from))->when($to, fn ($q) => $q->whereDate('e.entry_date', '<=', $to))
                ->groupBy('l.account_id')->selectRaw('l.account_id, SUM(l.debit) - SUM(l.credit) AS net')->pluck('net', 'l.account_id')->all();
            foreach ($j as $id => $net) {
                $out[$id] = ($out[$id] ?? 0) + (float) $net;
            }
        }

        return $out;
    }

    private static function signed(string $type, float $netDebit): float
    {
        return in_array($type, self::DEBIT_NORMAL, true) ? $netDebit : -$netDebit;
    }

    /** Movement of one account in the period, in the account's own sign (increase = positive). */
    public static function periodMovement(int $accountId, string $type, ?string $from, ?string $to): float
    {
        return self::signed($type, self::netDebitByAccount($from, $to, [$accountId])[$accountId] ?? 0.0);
    }

    /**
     * Balances of many accounts as of a date, own sign. @param iterable<object{id:int,type:string,current_balance:mixed}> $accounts
     * @return array<int,float>
     */
    public static function balancesAsOf(iterable $accounts, string $date): array
    {
        $accounts = collect($accounts);
        $after = self::netDebitByAccount(date('Y-m-d', strtotime($date . ' +1 day')), null, $accounts->pluck('id')->all());
        $out = [];
        foreach ($accounts as $a) {
            $out[$a->id] = round((float) $a->current_balance - self::signed($a->type, $after[$a->id] ?? 0.0), 2);
        }

        return $out;
    }
}
