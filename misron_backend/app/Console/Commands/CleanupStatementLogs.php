<?php

namespace App\Console\Commands;

use App\Models\AccountStatementLog;
use App\Models\ChartOfAccount;
use App\Models\Voucher;
use App\Models\VoucherDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repair corrupted account_statement_logs caused by the pre-fix accounting bugs:
 *
 * 1. Voucher edits duplicated log sets (old logs were never removed on edit).
 * 2. Voucher deletion logs did not always reverse the original entry.
 * 3. Old code dropped transaction_type on voucher/purchase/expense logs.
 * 4. The stored `balance` column mixes pre/post-transaction semantics and is stale.
 * 5. chart_of_accounts.current_balance may not match the statement logs.
 *
 * Run WITHOUT arguments for a dry-run report; pass --apply to execute.
 */
class CleanupStatementLogs extends Command
{
    protected $signature = 'accounts:cleanup-statement-logs {--apply : Actually apply the fixes (default: dry-run)}';

    protected $description = 'Repair duplicated/mistyped voucher logs and rebuild statement balances';

    private array $report = [];

    private function isApply(): bool
    {
        return (bool) $this->option('apply');
    }

    private function log(string $msg): void
    {
        $this->report[] = $msg;
        if ($this->isApply()) {
            $this->line($msg);
        }
    }

    public function handle()
    {
        if (!$this->isApply()) {
            $this->info('=== DRY RUN - no changes will be made ===');
        }

        $this->fixVoucherLogs();
        $this->fixEmptyTransactionTypes();
        $this->rebuildBalanceColumn();
        $this->recomputeChartBalances();

        if (!$this->isApply()) {
            $this->newLine();
            $this->info('=== DRY RUN COMPLETE - run with --apply to execute ===');
            $this->newLine();
            $this->info('Planned actions:');
            foreach ($this->report as $msg) {
                $this->line('  - ' . $msg);
            }
        } else {
            $this->newLine();
            $this->info('=== CLEANUP COMPLETE ===');
        }

        return Command::SUCCESS;
    }

    /**
     * Step 1 - repair voucher statement logs.
     */
    private function fixVoucherLogs(): void
    {
        $vouchers = Voucher::withTrashed()->get();

        foreach ($vouchers as $voucher) {
            $logs = AccountStatementLog::where(function ($q) use ($voucher) {
                $q->where('source_type', Voucher::class)->where('source_id', $voucher->id);
            })->orWhere('voucher_id', $voucher->id)->get();

            if ($logs->isEmpty()) {
                continue;
            }

            $baseRef = $voucher->voucher_no;
            $delRef = $baseRef . '-DEL';

            $baseLogs = $logs->where('reference_no', $baseRef);
            $delLogs = $logs->where('reference_no', $delRef);

            if ($voucher->trashed()) {
                $this->fixTrashedVoucher($voucher, $baseLogs, $delLogs);
            } else {
                $this->rebuildActiveVoucher($voucher, $logs);
            }
        }
    }

    /**
     * Deleted (trashed) voucher: keep the FIRST original log set only,
     * drop duplicated edit sets, and rewrite the -DEL logs so they are the
     * exact inverse of the kept set (guarantees net-zero impact).
     */
    private function fixTrashedVoucher($voucher, $baseLogs, $delLogs): void
    {
        // Drop duplicate original sets - keep the earliest row per unique entry
        $keptIds = [];
        $keptRows = [];
        foreach ($baseLogs->sortBy('id') as $log) {
            $key = $log->account_id . '|' . $log->transaction_date . '|' . $log->debit . '|' . $log->credit;
            if (!isset($keptRows[$key])) {
                $keptRows[$key] = $log;
                $keptIds[] = $log->id;
            }
        }

        $duplicateIds = $baseLogs->pluck('id')->diff($keptIds)->values();
        if ($duplicateIds->isNotEmpty()) {
            $this->log(sprintf(
                'Voucher %s (id %d, deleted): removing %d duplicated log row(s)',
                $voucher->voucher_no,
                $voucher->id,
                $duplicateIds->count()
            ));
            if ($this->isApply()) {
                AccountStatementLog::whereIn('id', $duplicateIds)->delete();
            }
        }

        // Set voucher_id on the kept rows (old logs missed it)
        $rowsMissingVoucherId = $baseLogs->whereNull('voucher_id');
        if ($rowsMissingVoucherId->isNotEmpty() && $this->isApply()) {
            AccountStatementLog::whereIn('id', $keptIds)->update(['voucher_id' => $voucher->id]);
        }

        // Rewrite -DEL logs as the exact inverse of the kept set
        if ($delLogs->isNotEmpty() || $keptRows) {
            $this->log(sprintf(
                'Voucher %s (id %d, deleted): rewriting %d -DEL reversal log(s) as exact inverse of original',
                $voucher->voucher_no,
                $voucher->id,
                $delLogs->count()
            ));
        }

        if ($this->isApply() && $keptRows) {
            AccountStatementLog::where('reference_no', $this->delRef($voucher))->delete();
            foreach ($keptRows as $log) {
                AccountStatementLog::create([
                    'account_id'       => $log->account_id,
                    'transaction_date' => $log->transaction_date,
                    'reference_no'     => $delRef($voucher),
                    'transaction_type' => $voucher->type . '_deletion',
                    'debit'            => $log->credit,
                    'credit'           => $log->debit,
                    'balance'          => 0,
                    'description'      => 'Deletion of voucher ' . $voucher->voucher_no,
                    'source_id'        => $voucher->id,
                    'source_type'      => Voucher::class,
                    'voucher_id'       => $voucher->id,
                ]);
            }
        }
    }

    private function delRef($voucher): string
    {
        return $voucher->voucher_no . '-DEL';
    }

    /**
     * Active voucher: rebuild ALL logs from the current VoucherDetail rows,
     * exactly like VoucherController::update() now does.
     */
    private function rebuildActiveVoucher($voucher, $logs): void
    {
        $this->log(sprintf(
            'Voucher %s (id %d): rebuilding %d log row(s) from current %d detail line(s)',
            $voucher->voucher_no,
            $voucher->id,
            $logs->count(),
            $voucher->details()->count()
        ));

        if ($this->isApply()) {
            AccountStatementLog::where(function ($q) use ($voucher) {
                $q->where('source_type', Voucher::class)->where('source_id', $voucher->id);
            })->orWhere('voucher_id', $voucher->id)->delete();

            foreach ($voucher->details()->get() as $detail) {
                AccountStatementLog::create([
                    'account_id'       => $detail->account_id,
                    'transaction_date' => $voucher->date,
                    'reference_no'     => $voucher->voucher_no,
                    'transaction_type' => $voucher->type,
                    'debit'            => $detail->debit,
                    'credit'           => $detail->credit,
                    'balance'          => 0,
                    'description'      => $detail->narration ?? $voucher->description,
                    'source_id'        => $voucher->id,
                    'source_type'      => Voucher::class,
                    'voucher_id'       => $voucher->id,
                ]);
            }
        }
    }

    /**
     * Step 2 - fill in transaction_type for old logs that were stored without it.
     */
    private function fixEmptyTransactionTypes(): void
    {
        $emptyLogs = AccountStatementLog::where(function ($q) {
            $q->whereNull('transaction_type')->orWhere('transaction_type', '');
        })->get();

        $byRule = [];
        foreach ($emptyLogs as $log) {
            $type = $this->inferType($log);
            if ($type) {
                $byRule[$type][] = $log->id;
            } else {
                $byRule['__unresolved__'][] = $log->id;
            }
        }

        foreach ($byRule as $type => $ids) {
            if ($type === '__unresolved__') {
                $this->log(sprintf('WARNING: %d log(s) with empty type could not be resolved', count($ids)));
                continue;
            }
            $this->log(sprintf(
                'Setting transaction_type %s on %d log row(s)',
                $type,
                count($ids)
            ));
            if ($this->isApply()) {
                AccountStatementLog::whereIn('id', $ids)->update(['transaction_type' => $type]);
            }
        }
    }

    private function inferType($log): ?string
    {
        $ref = (string) $log->reference_no;
        $desc = (string) $log->description;

        if (preg_match('/^(CP|BP|CR|BR|JV|CV)-/', $ref, $m)) {
            $prefix = $m[1];
            if (str_ends_with($ref, '-DEL')) {
                return $prefix . '_deletion';
            }
            if (str_ends_with($ref, '-REV')) {
                return $prefix . '_reversal';
            }
            return $prefix;
        }

        if (str_starts_with($ref, 'Ref-')) {
            if (str_ends_with($ref, '-REV')) {
                return 'purchase_payment_reversal';
            }
            return str_starts_with($desc, 'Payment') ? 'purchase_payment' : 'purchase';
        }

        if (str_starts_with($ref, 'TR-')) {
            return 'transfer_reversal';
        }

        if (str_starts_with($ref, 'EXP-')) {
            if (str_ends_with($ref, '-REV')) {
                return 'expense_payment_reversal';
            }
            return str_starts_with($desc, 'Expense payment') ? 'expense_payment' : 'expense';
        }

        if (str_starts_with($ref, 'PR-')) {
            return 'purchase_return';
        }

        if ($ref === 'ADJUSTMENT') {
            return 'opening_balance_adjustment';
        }

        if (str_starts_with($ref, 'Order Cancelled')) {
            return 'order_cancelled';
        }

        if (str_starts_with($ref, 'REV-STL-') || str_starts_with($ref, 'REVERSAL-STL-')) {
            return 'courier_settlement_reversal';
        }

        return null;
    }

    /**
     * Step 3 - rebuild the stored balance column as a POST-transaction running
     * balance per account, replayed in (transaction_date, id) order.
     * This matches how the account statement view seeds its running balance.
     */
    private function rebuildBalanceColumn(): void
    {
        $accountIds = AccountStatementLog::distinct()->pluck('account_id');
        $types = ChartOfAccount::whereIn('id', $accountIds)->pluck('type', 'id')->map(fn($t) => (string) ($t ?? 'asset'));

        $updated = 0;
        foreach ($accountIds as $accountId) {
            $logs = AccountStatementLog::where('account_id', $accountId)
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get();

            $running = 0;
            foreach ($logs as $log) {
                $running = $this->applyToRunning($running, $types[$accountId] ?? 'asset', $log->debit, $log->credit);
                if (abs((float) $log->balance - $running) > 0.01) {
                    $updated++;
                    if ($this->isApply()) {
                        AccountStatementLog::where('id', $log->id)->update(['balance' => $running]);
                    }
                }
            }
        }

        $this->log(sprintf('Replaying balance column: %d log row(s) would be updated across %d account(s)', $updated, $accountIds->count()));
    }

    /**
     * Step 4 - recompute chart_of_accounts.current_balance from the logs so it
     * always matches the statements. asset/expense grow with debit,
     * liability/equity/income grow with credit.
     */
    private function recomputeChartBalances(): void
    {
        $accounts = ChartOfAccount::all();
        $diffCount = 0;

        foreach ($accounts as $account) {
            $net = $this->netFromLogs($account->id);
            if (abs($net - (float) $account->current_balance) > 0.01) {
                $diffCount++;
                if ($this->isApply()) {
                    $account->update(['current_balance' => $net]);
                }
                $this->line(sprintf(
                    '  Account %d (%s): current_balance %.2f -> %.2f %s',
                    $account->id,
                    $account->name,
                    (float) $account->current_balance,
                    $net,
                    $this->isApply() ? '[APPLIED]' : '[would change]'
                ));
            }
        }

        $this->log(sprintf('Chart balances: %d account(s) would change to match statement logs', $diffCount));
    }

    private function netFromLogs(int $accountId): float
    {
        $sum = DB::table('account_statement_logs')
            ->where('account_id', $accountId)
            ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
            ->first();

        return $this->applyToRunning(0, $this->accountType($accountId), $sum->d, $sum->c);
    }

    private function accountType(int $accountId): string
    {
        return (string) (ChartOfAccount::where('id', $accountId)->value('type') ?? 'asset');
    }

    private function applyToRunning(float $running, string $type, $debit, $credit): float
    {
        $debit = (float) $debit;
        $credit = (float) $credit;

        if (in_array($type, ['liability', 'equity', 'income'])) {
            return $running + $credit - $debit;
        }
        return $running + $debit - $credit;
    }
}
