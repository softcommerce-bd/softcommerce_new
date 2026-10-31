<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

/**
 * Turns committed outbox rows (see LegacyLedgerBridge) into journal entries, exactly once.
 * One token = one outermost legacy transaction = one balanced journal entry. The journal insert and the "processed" marks
 * share one database transaction and the journal key is derived from the token, so a crash, a retry, two workers, or a
 * request racing the scheduler can never produce two entries or leave a committed business change without one.
 */
class LedgerOutboxProcessor
{
    /** Test hook: called with a boundary name at each step of processing (a test can kill the process there). */
    public static ?\Closure $boundaryHook = null;

    private static function at(string $name): void
    {
        if (self::$boundaryHook) {
            (self::$boundaryHook)($name);
        }
    }

    /** @return int|null journal entry id, null when the token had nothing to do */
    public function processToken(string $token): ?int
    {
        $id = $this->processTokenInTransaction($token);
        self::at('after_commit');

        return $id;
    }

    private function processTokenInTransaction(string $token): ?int
    {
        return DB::transaction(function () use ($token) {
            $rows = DB::table('ledger_outbox')->where('token', $token)->whereNull('processed_at')->orderBy('id')->lockForUpdate()->get();
            if ($rows->isEmpty()) {
                return null; // another worker already did it
            }
            self::at('after_lock');
            $net = [];
            foreach ($rows as $r) {
                $net[(int) $r->account_id] = ($net[(int) $r->account_id] ?? 0) + (int) round(((float) $r->delta) * 100);
            }
            ksort($net);
            $types = ChartOfAccount::withTrashed()->whereIn('id', array_keys($net))->pluck('type', 'id');
            $lines = [];
            $debit = $credit = 0;
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
            self::at('after_lines');
            $entryId = null;
            if ($lines) {
                $mirrored = array_column($lines, 'account');
                if ($debit !== $credit) {
                    $lines[] = ['account' => SystemAccounts::id('suspense'), $debit > $credit ? 'credit' : 'debit' => abs($debit - $credit), 'memo' => 'One-sided legacy posting (no counter-account in the old screen)'];
                }
                $first = $rows->first();
                $sourceType = $first->source_type ?: 'legacy:unknown';
                $sourceId = $first->source_id ? (int) $first->source_id : null;
                $reversalOf = $this->findReversed($lines, $sourceType, $sourceId);
                $entryId = app(JournalService::class)->postMirrored('legacy:' . $token, now()->toDateString(), $lines, $mirrored, $sourceType, $sourceId, $first->description, $first->created_by ? (int) $first->created_by : null, $reversalOf)['journal_entry_id'];
            }
            self::at('after_journal');
            DB::table('ledger_outbox')->whereIn('id', $rows->pluck('id'))->update(['processed_at' => now(), 'journal_entry_id' => $entryId, 'last_error' => null]);
            self::at('after_marks');

            return $entryId;
        });
    }

    /** Completes every committed token that is still waiting (scheduler / console / after a crash). @return array{processed:int,failed:int} */
    public function processPending(int $limit = 200): array
    {
        $tokens = DB::table('ledger_outbox')->whereNull('processed_at')->groupBy('token')->orderByRaw('MIN(id)')->limit($limit)->pluck('token');
        $ok = $failed = 0;
        foreach ($tokens as $token) {
            try {
                $this->processToken($token);
                $ok++;
            } catch (\Throwable $e) {
                $failed++;
                DB::table('ledger_outbox')->where('token', $token)->whereNull('processed_at')->update(['last_error' => mb_substr($e->getMessage(), 0, 250), 'attempts' => DB::raw('attempts + 1')]);
            }
        }

        return ['processed' => $ok, 'failed' => $failed];
    }

    /** An earlier, not yet reversed legacy entry of the same document whose lines this entry exactly negates. */
    private function findReversed(array $lines, string $sourceType, ?int $sourceId): ?int
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
