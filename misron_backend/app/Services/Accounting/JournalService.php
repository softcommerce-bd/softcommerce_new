<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Services\Inventory\Idempotency;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The accounting posting layer. A journal entry is posted whole or not at all:
 *  - at least two lines, each with a debit OR a credit, never both, never negative;
 *  - total debits = total credits (exact, in cents);
 *  - an idempotency key: posting the same key again returns the first entry and changes nothing;
 *  - posted lines are never edited; a mistake is undone with reverse(), once.
 * chart_of_accounts.current_balance is kept as a cache, updated in the same transaction (accounts locked in id order),
 * and account_statement_logs receives a row per line so every existing report and statement keeps working.
 * The journal tables are the source of truth for everything posted through this service.
 */
class JournalService
{
    private const DEBIT_NORMAL = ['asset', 'expense'];

    /**
     * @param array<int, array{account:int, debit?:int, credit?:int, supplier_id?:int, memo?:string}> $lines amounts in CENTS
     * @return array{journal_entry_id:int, entry_no:string, replayed:bool}
     */
    public function post(string $key, string $date, array $lines, ?string $sourceType = null, ?int $sourceId = null, ?string $description = null, ?int $by = null): array
    {
        $this->assertValid($lines);

        $result = Idempotency::once('journal', $key, function () use ($key, $date, $lines, $sourceType, $sourceId, $description, $by) {
            return $this->write($key, $date, $lines, $sourceType, $sourceId, $description, $by, null);
        });

        return ['journal_entry_id' => $result['journal_entry_id'], 'entry_no' => $result['entry_no'], 'replayed' => $result['replayed']];
    }

    /**
     * Records an entry for balance changes that the legacy screens ALREADY applied: the lines of the accounts in $mirrored are journalled
     * (flagged mirrored) but neither current_balance nor the statement log is touched again. Other lines (the suspense remainder) post normally.
     */
    public function postMirrored(string $key, string $date, array $lines, array $mirrored, ?string $sourceType, ?int $sourceId, ?string $description, ?int $by, ?int $reversalOf = null): array
    {
        $this->assertValid($lines);
        $result = Idempotency::once('journal', $key, fn () => $this->write($key, $date, $lines, $sourceType, $sourceId, $description, $by, $reversalOf, $mirrored));

        return ['journal_entry_id' => $result['journal_entry_id'], 'entry_no' => $result['entry_no'], 'replayed' => $result['replayed']];
    }

    /** Posts the mirror image of an entry, once. */
    public function reverse(int $entryId, string $key, string $reason, ?int $by = null): array
    {
        $result = Idempotency::once('journal', $key, function () use ($entryId, $key, $reason, $by) {
            $entry = DB::table('journal_entries')->where('id', $entryId)->lockForUpdate()->first();
            if (!$entry) {
                throw new InvalidArgumentException('Journal entry not found.');
            }
            if (DB::table('journal_entries')->where('reversal_of_id', $entryId)->exists()) {
                throw new InvalidArgumentException('This entry has already been reversed.');
            }
            $lines = DB::table('journal_lines')->where('journal_entry_id', $entryId)->get()->map(fn ($l) => [
                'account' => (int) $l->account_id, 'debit' => (int) round($l->credit * 100), 'credit' => (int) round($l->debit * 100), 'supplier_id' => $l->supplier_id, 'memo' => 'Reversal: ' . $l->memo,
            ])->all();

            return $this->write($key, now()->toDateString(), $lines, 'journal_reversal', $entryId, 'Reversal of ' . $entry->entry_no . ': ' . $reason, $by, $entryId);
        });

        return ['journal_entry_id' => $result['journal_entry_id'], 'entry_no' => $result['entry_no'], 'replayed' => $result['replayed']];
    }

    private function assertValid(array $lines): void
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A journal entry needs at least two lines.');
        }
        $debit = $credit = 0;
        foreach ($lines as $l) {
            $d = (int) ($l['debit'] ?? 0);
            $c = (int) ($l['credit'] ?? 0);
            if ($d < 0 || $c < 0 || ($d > 0 && $c > 0) || ($d === 0 && $c === 0) || empty($l['account'])) {
                throw new InvalidArgumentException('Each journal line needs an account and exactly one positive amount, debit or credit.');
            }
            $debit += $d;
            $credit += $c;
        }
        if ($debit !== $credit) {
            throw new InvalidArgumentException("Unbalanced journal entry: debits {$debit} <> credits {$credit} (cents).");
        }
    }

    private function write(string $key, string $date, array $lines, ?string $sourceType, ?int $sourceId, ?string $description, ?int $by, ?int $reversalOf, array $mirrored = []): array
    {
        $entryNo = 'JE-' . now()->format('ymd') . '-' . strtoupper(substr(sha1($key . microtime(true)), 0, 8));
        $entryId = DB::table('journal_entries')->insertGetId(['entry_no' => $entryNo, 'entry_date' => $date, 'source_type' => $sourceType, 'source_id' => $sourceId, 'description' => $description ? mb_substr($description, 0, 255) : null,
            'reversal_of_id' => $reversalOf, 'idempotency_key' => $key, 'created_by' => $by, 'posted_at' => now()]);

        // Lock every account once, in id order: two postings touching the same accounts can never deadlock.
        $ids = collect($lines)->pluck('account')->unique()->sort()->values()->all();
        $accounts = ChartOfAccount::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($ids as $id) {
            if (!isset($accounts[$id])) {
                throw new InvalidArgumentException("Account {$id} does not exist.");
            }
        }

        foreach ($lines as $l) {
            $d = (int) ($l['debit'] ?? 0);
            $c = (int) ($l['credit'] ?? 0);
            $isMirror = in_array($l['account'], $mirrored, true);
            DB::table('journal_lines')->insert(['journal_entry_id' => $entryId, 'account_id' => $l['account'], 'debit' => $d / 100, 'credit' => $c / 100, 'supplier_id' => $l['supplier_id'] ?? null, 'memo' => isset($l['memo']) ? mb_substr($l['memo'], 0, 255) : null, 'mirrored' => $isMirror ? 1 : 0]);
            if ($isMirror) {
                continue; // the legacy code already moved this balance and wrote its own statement row
            }

            $acc = $accounts[$l['account']];
            $delta = in_array($acc->type, self::DEBIT_NORMAL, true) ? ($d - $c) : ($c - $d);
            DB::update('UPDATE chart_of_accounts SET current_balance = current_balance + ?, updated_at = NOW() WHERE id = ?', [$delta / 100, $acc->id]);
            $balance = (float) DB::table('chart_of_accounts')->where('id', $acc->id)->value('current_balance');
            DB::table('account_statement_logs')->insert(['account_id' => $acc->id, 'transaction_date' => $date, 'reference_no' => $entryNo, 'transaction_type' => 'journal',
                'source_id' => $entryId, 'source_type' => 'journal_entry', 'debit' => $d / 100, 'credit' => $c / 100, 'balance' => $balance, 'description' => $description, 'created_at' => now(), 'updated_at' => now()]);
        }

        return ['journal_entry_id' => $entryId, 'entry_no' => $entryNo];
    }

    /** Balance of one account from the journal only, as of a date (inclusive). Debit-normal accounts: debits - credits. */
    public static function balanceAsOf(int $accountId, string $date): string
    {
        $acc = DB::table('chart_of_accounts')->where('id', $accountId)->first(['type']);
        $row = DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->where('l.account_id', $accountId)->where('e.entry_date', '<=', $date)
            ->selectRaw('COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c')->first();
        $net = in_array($acc->type, self::DEBIT_NORMAL, true) ? $row->d - $row->c : $row->c - $row->d;

        return number_format((float) $net, 2, '.', '');
    }

    /** Trial balance from the journal: every account with activity, total debits must equal total credits. */
    public static function trialBalance(string $asOf): array
    {
        $rows = DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->join('chart_of_accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.entry_date', '<=', $asOf)->groupBy('a.id', 'a.name', 'a.type')->orderBy('a.id')
            ->selectRaw('a.id, a.name, a.type, SUM(l.debit) debit, SUM(l.credit) credit')->get();

        return ['rows' => $rows, 'debit' => number_format((float) $rows->sum('debit'), 2, '.', ''), 'credit' => number_format((float) $rows->sum('credit'), 2, '.', ''),
            'balanced' => round((float) $rows->sum('debit') - (float) $rows->sum('credit'), 2) === 0.0];
    }
}
