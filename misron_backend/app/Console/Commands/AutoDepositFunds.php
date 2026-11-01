<?php

namespace App\Console\Commands;

use App\Models\TransactionMethod;
use App\Models\ChartOfAccount;
use App\Models\BalanceTransfer;
use App\Models\ExpensePayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoDepositFunds extends Command
{
    protected $signature = 'accounts:auto-deposit';
    protected $description = 'Nightly transfer of balances from mobile bank accounts to linked bank accounts';

    /** Stable text for log searches and monitoring. */
    public const PAUSED_TAG = 'AUTO_DEPOSIT_PAUSED_AD1';

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
                    'transaction_type' => 'auto_deposit',
                    'from_account_id'  => $source->id,
                    'to_account_id'    => $destination->id,
                    'amount'           => $transferAmount,
                    'charge'           => $chargeAmount, // Store actual charge here
                    'reference_no'     => 'AUTO-' . now()->format('Ymd-His') . '-' . $method->id,
                    'note'             => "Auto deposit: {$source->name} → {$destination->name}",
                    'status'           => 'completed',
                    'created_by'       => null,
                ]);

                // 2. Update account balances
                $source->decrement('current_balance', $balance);
                $destination->increment('current_balance', $transferAmount);

                // 3. Record charge expense if any
                if ($chargeAmount > 0) {
                    $this->recordChargeExpense($method, $chargeAmount, $source, $transfer);
                }

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
        return 0;
    }

    private function recordChargeExpense($method, $chargeAmount, $sourceAccount, $transferRecord)
    {
        // Find the charge expense account
        $expenseAccount = ChartOfAccount::where('type', 'expense')
            ->where('sub_type', 'expense')
            ->where('status', 'active')
            ->where(function($query) {
                $query->where('name', 'like', '%Card/Mobile Banking Charge%')
                      ->orWhere('code', '5-51002');
            })
            ->where('is_deleted', 0)
            ->first();

        if (!$expenseAccount) {
            // Fallback to ID 67
            $expenseAccount = ChartOfAccount::find(67);
        }

        if (!$expenseAccount) {
            $this->warn('No charge expense account found. Skipping expense entry.');
            return;
        }

        // Generate voucher number for expense
        $voucherNo = 'EXP-' . now()->format('Ymd') . '-' . uniqid();
        
        // Check if ExpensePayment model has method to generate voucher
        if (method_exists(ExpensePayment::class, 'generateVoucherNo')) {
            $voucherNo = ExpensePayment::generateVoucherNo('expense');
        }

        ExpensePayment::create([
            'voucher_no'           => $voucherNo,
            'date'                 => now()->format('Y-m-d'),
            'expense_account_id'   => $expenseAccount->id, // IMPORTANT: Link expense account
            'payment_account_id'   => $sourceAccount->id,
            'total_amount'         => $chargeAmount,
            'description'          => "Auto-deposit charge for {$method->name} (Transfer: {$transferRecord->voucher_no})",
            'status'               => 'completed',
            'transaction_method_id'=> $method->id,
            'reference_no'         => $transferRecord->reference_no,
            'created_by'           => null,
        ]);
        
        $this->info("  → Recorded charge expense: {$chargeAmount} to account {$expenseAccount->name}");
    }
}