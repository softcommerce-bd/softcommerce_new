<?php

namespace App\Console\Commands;

use App\Models\TransactionMethod;
use App\Services\Accounting\AutoDepositReadiness;
use App\Models\ChartOfAccount;
use App\Models\BalanceTransfer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoDepositFunds extends Command
{
    use \App\Traits\StatementLogTrait;

    protected $signature = 'accounts:auto-deposit';
    protected $description = 'Nightly transfer of balances from mobile bank accounts to linked bank accounts';

    /** Stable text for log searches and monitoring. */
    public const PAUSED_TAG = 'AUTO_DEPOSIT_PAUSED_AD1';
    public const BLOCKED_TAG = 'AUTO_DEPOSIT_BLOCKED_NOT_READY';

    public function handle()
    {
        // GUARD G1 (owner decisions AD-1.1 option C, AD-1.2): the job is paused pending approval. It returns before ANY database access -
        // no query, no transaction, no lock, no number taken, nothing moved - and says so once, instead of failing once per method every night.
        // Status: SUCCESS (0). Paused-by-decision is the intended state, not a failure; a non-zero code would make the scheduler report a
        // failure every night and hide real ones. The log line is a warning with a fixed tag so it can be searched or alerted on.
        if (!config('accounts.auto_deposit_approved')) {
            $message = self::PAUSED_TAG . ': accounts:auto-deposit is PAUSED pending owner approval (decision AD-1, option C selected). '
                . 'No transaction was opened and no money was moved. To resume it the owner must approve an AD-1 option '
                . '(a: system user as creator, b: nullable created_by) and the change must be implemented and tested; see docs/T5_AD1_AUTO_DEPOSIT_OPTIONS.md. '
                . 'Until then make deposits on the Balance Transfers screen.';
            Log::warning($message);
            $this->warn($message);

            return self::SUCCESS;
        }

        // R-03/R-04: the switch alone is never enough. Without a creator, legal ENUM values, InnoDB tables and with no charged method,
        // the job refuses before opening any transaction; a non-zero exit makes the scheduler report it.
        $problems = AutoDepositReadiness::problems();
        if ($problems) {
            $message = self::BLOCKED_TAG . ': accounts:auto-deposit is switched on but NOT READY, so it did nothing. ' . implode(' | ', $problems);
            Log::critical($message);
            $this->error($message);

            return self::FAILURE;
        }

        $autoMethods = TransactionMethod::where('deposit_type', 'auto')
            ->where('status', 'active')
            ->with(['sourceAccount', 'bankAccount'])
            ->get();

        if ($autoMethods->isEmpty()) {
            $this->info('No active auto-deposit methods found.');
            return 0;
        }

        $successCount = 0;
        $failedCount = 0;

        foreach ($autoMethods as $method) {
            $source = $method->sourceAccount;
            $destination = $method->bankAccount;

            if (!$source || !$destination) {
                $this->warn("Skipping '{$method->name}': missing source or destination account.");
                $failedCount++;
                continue;
            }

            DB::beginTransaction();

            try {
                // one global lock order: number series first (as the transfer and expense screens do), then the accounts
                \App\Support\NumberSerializer::hold(\App\Models\BalanceTransfer::voucherSeries(AutoDepositReadiness::TYPE));
                if ((float) ($method->charge ?? 0) > 0) {
                    throw new \RuntimeException("Method '{$method->name}' charges a fee; charged auto-deposit methods are not supported (the charge cannot be booked yet).");
                }
                // The balance to move is read UNDER the source account's row lock, inside the transaction. It used to be read before it, so two runs at the
                // same moment (a manual run during the scheduled one, or two hosts) each moved the whole balance: the destination got it twice and the
                // source went negative. The second run now waits, finds the balance already moved and skips.
                $source = ChartOfAccount::lockForUpdate()->find($source->id);
                $balance = $source->current_balance;
                if ($balance <= 0) {
                    DB::rollBack();
                    $this->info("'{$method->name}' source balance is {$balance}, skipping.");
                    continue;
                }

                $chargePercent = $method->charge ?? 0;
                $chargeAmount = round($balance * ($chargePercent / 100), 2);
                $transferAmount = $balance - $chargeAmount;

                // 1. Create the balance transfer record
                $transfer = BalanceTransfer::create([
                    'voucher_no'       => BalanceTransfer::generateVoucherNo('auto_deposit'),
                    'date'             => now()->format('Y-m-d'),
                    'transaction_type' => AutoDepositReadiness::TYPE,
                    'from_account_id'  => $source->id,
                    'to_account_id'    => $destination->id,
                    'amount'           => $transferAmount,
                    'charge'           => $chargeAmount, // Store actual charge here
                    'reference_no'     => 'AUTO-' . now()->format('Ymd-His') . '-' . $method->id,
                    'note'             => "Auto deposit: {$source->name} → {$destination->name}",
                    'status'           => AutoDepositReadiness::STATUS,
                    'created_by'       => (int) config('accounts.auto_deposit_user_id'),
                ]);

                // 2. Update account balances
                $source->decrement('current_balance', $balance);
                $destination->increment('current_balance', $transferAmount);

                // statement lines exactly as the manual balance-transfer screen writes them (source credited with amount + charge, destination debited
                // with the amount): the old job moved balances without any trail in the account statements
                $label = 'Auto deposit: ' . $source->name . ' → ' . $destination->name . ' | ' . number_format($transferAmount, 2);
                $this->insertStatementLog($source->id, $transfer->date, $transfer->voucher_no, AutoDepositReadiness::TYPE, 0, $balance, $label, $transfer);
                $this->insertStatementLog($destination->id, $transfer->date, $transfer->voucher_no, AutoDepositReadiness::TYPE, $transferAmount, 0, $label, $transfer);

                // (3. The charge expense is NOT recorded here any more: the old code created an expense_payments row with keys that are not columns of that table
                // (description, expense_account_id, transaction_method_id, reference_no were silently dropped), so the charge was linked to no expense account.
                // Charged methods are refused by AutoDepositReadiness until the accountant rules how the charge is booked (ACC-03).)

                DB::commit();
                
                $this->info("✓ Transferred {$transferAmount} from {$source->name} to {$destination->name} (charge: {$chargeAmount})");
                $successCount++;
                
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("Failed to transfer for '{$method->name}': " . $e->getMessage());
                Log::error("Auto-deposit failed for {$method->name}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $failedCount++;
            }
        }

        $this->info("Auto-deposit completed. Success: {$successCount}, Failed: {$failedCount}");

        return $failedCount > 0 ? self::FAILURE : self::SUCCESS; // a failed method must not look like a clean run to the scheduler
    }
}
