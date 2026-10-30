<?php

namespace App\Console\Commands;

use App\Models\AccountStatementLog;
use App\Models\ChartOfAccount;
use App\Models\CourierSettlement;
use App\Models\CourierSettlementDetail;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixPosCourierIncome extends Command
{
    protected $signature = 'courier:fix-pos-income {--dry-run : Show what would be fixed without making changes}';

    protected $description = 'Fix missing Courier Income entries for historical POS courier settlements';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $fixed = 0;
        $skipped = 0;

        // Find all courier settlements for POS orders
        $settlements = CourierSettlement::whereHas('order', function ($q) {
            $q->where('order_from', 'pos');
        })->get();

        if ($settlements->isEmpty()) {
            $this->info('No POS courier settlements found.');
            return 0;
        }

        $this->info("Found {$settlements->count()} POS courier settlement(s).");
        if ($isDryRun) {
            $this->warn('--- DRY RUN MODE --- No changes will be made.');
        }

        $courierIncomeAccount = ChartOfAccount::getCourierIncomeAccount();
        if (!$courierIncomeAccount) {
            $this->error('Courier Income account not found. Please create it first.');
            return 1;
        }

        foreach ($settlements as $settlement) {
            $this->line("----------------------------------------");
            $this->line("Settlement #{$settlement->settlement_no} (Order #{$settlement->order_id})");

            // Check if Courier Income was already logged for this settlement
            $alreadyLogged = AccountStatementLog::where('account_id', $courierIncomeAccount->id)
                ->where('reference_no', $settlement->settlement_no)
                ->where('source_type', CourierSettlement::class)
                ->where('source_id', $settlement->id)
                ->exists();

            if ($alreadyLogged) {
                $this->warn("  Courier Income already recorded. Skipping.");
                $skipped++;
                continue;
            }

            // Get total shipping cost from settlement details
            $totalShippingCost = CourierSettlementDetail::where('courier_settlement_id', $settlement->id)
                ->sum('shipping_cost');

            if ($totalShippingCost <= 0) {
                $this->warn("  No shipping cost found in settlement details. Skipping.");
                $skipped++;
                continue;
            }

            $courierFee = $settlement->courier_fee ?? 0;
            $settlementFee = $settlement->settlement_fee ?? 0;

            // Same formula as CourierSettlementController
            $courierIncomeAmount = $totalShippingCost - ($courierFee + $settlementFee);

            $this->line("  Shipping Cost: {$totalShippingCost}");
            $this->line("  Courier Fee: {$courierFee}");
            $this->line("  Settlement Fee: {$settlementFee}");
            $this->line("  Calculated Courier Income Amount: {$courierIncomeAmount}");

            if ($courierIncomeAmount <= 0) {
                // This is a loss scenario, check if Bad Debt was already handled
                $badDebtAccount = ChartOfAccount::getBadDebtExpenseAccount();
                if ($badDebtAccount) {
                    $badDebtLogged = AccountStatementLog::where('account_id', $badDebtAccount->id)
                        ->where('reference_no', $settlement->settlement_no)
                        ->where('source_type', CourierSettlement::class)
                        ->exists();
                    if (!$badDebtLogged) {
                        $this->warn("  Courier income is negative (loss). Bad Debt may need manual fix.");
                    }
                }
                $this->warn("  Skipping (no positive courier income).");
                $skipped++;
                continue;
            }

            if ($isDryRun) {
                $fixed++;
                $this->info("  WOULD FIX: Add {$courierIncomeAmount} to Courier Income account.");
                continue;
            }

            try {
                DB::beginTransaction();

                // Update Courier Income account balance
                $oldBalance = $courierIncomeAccount->current_balance;
                $courierIncomeAccount->current_balance += $courierIncomeAmount;
                $courierIncomeAccount->save();

                // Create statement log
                AccountStatementLog::create([
                    'account_id'       => $courierIncomeAccount->id,
                    'transaction_date' => $settlement->settlement_date ? $settlement->settlement_date->toDateString() : now()->toDateString(),
                    'reference_no'     => $settlement->settlement_no,
                    'transaction_type' => 'courier_settlement',
                    'debit'            => 0,
                    'credit'           => $courierIncomeAmount,
                    'balance'          => $courierIncomeAccount->current_balance,
                    'description'      => 'Courier income from settlement (FIX): ' . $settlement->settlement_no . ' - Order #' . ($settlement->order->code ?? $settlement->order_id),
                    'source_id'        => $settlement->id,
                    'source_type'      => CourierSettlement::class,
                    'voucher_id'       => null,
                    'order_id'         => $settlement->order_id,
                ]);

                DB::commit();
                $fixed++;
                $this->info("  FIXED: Courier Income updated (Balance: {$oldBalance} -> {$courierIncomeAccount->current_balance}).");
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("  FAILED: {$e->getMessage()}");
                Log::error('Courier fix income error: ' . $e->getMessage());
            }
        }

        $this->line("----------------------------------------");
        if ($isDryRun) {
            $this->info("Dry run complete. {$fixed} settlement(s) would be fixed, {$skipped} skipped.");
        } else {
            $this->info("Complete. {$fixed} settlement(s) fixed, {$skipped} skipped.");
        }

        return 0;
    }
}
