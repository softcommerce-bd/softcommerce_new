<?php

namespace App\Console\Commands;

use App\Models\AccountStatementLog;
use App\Models\ChartOfAccount;
use App\Models\CourierSettlement;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\PurchaseProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixPartialSettlements extends Command
{
    protected $signature = 'courier:fix-partial-settlements {--dry-run : Show what would be fixed without making changes}';

    protected $description = 'Fix settlement values and accounting for partial_delivered orders settled with inflated due_amount/COGS/shipping';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $fixed = 0;
        $skipped = 0;
        $errors = 0;

        $orders = Order::where('courier_status', 'partial_delivered')
            ->where(function ($q) {
                $q->where('settlement_status', 'true')
                  ->orWhere('settlement_status', '1');
            })
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No settled partial_delivered orders found.');
            return 0;
        }

        $this->info("Found {$orders->count()} settled partial_delivered order(s).");
        if ($isDryRun) {
            $this->warn('--- DRY RUN MODE --- No changes will be made.');
        }

        foreach ($orders as $order) {
            $this->line("----------------------------------------");
            $this->line("Order #{$order->id} ({$order->code})");

            $settlement = CourierSettlement::where('order_id', $order->id)->first();
            if (!$settlement) {
                $this->warn("  No settlement record found. Skipping.");
                $skipped++;
                continue;
            }
            $this->line("  Settlement: {$settlement->settlement_no}");

            $orderDetails = OrderDetail::where('order_id', $order->id)->get();
            $existingLogs = AccountStatementLog::where('source_type', CourierSettlement::class)
                ->where('source_id', $settlement->id)
                ->get();

            $isPosOrder = ($order->order_from == 'pos');
            $courierFee = $settlement->courier_fee ?? 0;
            $partialPaymentChoice = $settlement->partial_payment_choice;

            // --- CORRECT values (delivered-only) ---
            $correctTotalAmount = $order->grand_total;

            $correctShippingCost = $orderDetails
                ->where('delivery_status', 'delivered')
                ->sum('shipping_cost');

            $correctCOGS = 0;
            foreach ($orderDetails as $detail) {
                if ($detail->delivery_status !== 'delivered') continue;
                if (!$detail->purchase_id || $detail->quantity <= 0) continue;

                $purchaseProduct = PurchaseProduct::where('purchase_id', $detail->purchase_id)
                    ->where('product_id', $detail->product_id)
                    ->where('variant', $detail->variation)
                    ->first();

                if ($purchaseProduct) {
                    $correctCOGS += $purchaseProduct->buying_unit_cost * $detail->quantity;
                } else {
                    $correctCOGS += $detail->buying_price * $detail->quantity;
                }
            }

            $correctSettlementFee = 0;
            $correctNetPayable = $correctTotalAmount - $courierFee;
            $correctCourierIncomeAmount = $correctShippingCost - $courierFee;
            $correctBaseSalesAmount = $correctTotalAmount - $correctShippingCost;

            $isPaidAndDelivered = ($order->payment_status == 'paid');
            if ($isPaidAndDelivered && $correctCourierIncomeAmount > 0) {
                $correctSalesAmount = $correctBaseSalesAmount + $correctCourierIncomeAmount;
            } else {
                $correctSalesAmount = $correctBaseSalesAmount;
            }

            // --- OLD values from settlement record ---
            $oldTotalAmount = $settlement->total_amount;
            $oldNetPayable = $settlement->net_payable;
            $oldSettlementFee = $settlement->settlement_fee;

            // Derive oldShippingCost from logs: sum of account credits for courier_settlement_cogs (inventory)
            // OR just calculate from settlement_details
            $oldShippingCost = $settlement->settlementDetails()->sum('shipping_cost');
            // Derive oldCOGS from logs if available
            $oldCOGS = 0;
            foreach ($existingLogs as $log) {
                if ($log->transaction_type === 'courier_settlement_cogs' && $log->debit > 0) {
                    $oldCOGS += $log->debit;
                }
            }
            // Also try from settlement_details buying_price
            if ($oldCOGS <= 0) {
                foreach ($settlement->settlementDetails as $sd) {
                    $oldCOGS += ($sd->buying_price ?? 0) * $sd->quantity;
                }
            }

            // Old sales amount from logs
            $oldSalesAmount = 0;
            foreach ($existingLogs as $log) {
                if ($log->transaction_type === 'courier_settlement'
                    && $log->credit > 0
                    && str_contains($log->description ?? '', 'Sales revenue')) {
                    $oldSalesAmount += $log->credit;
                }
            }

            $this->line("  --- Old vs Correct ---");
            $this->line("  total_amount:     {$oldTotalAmount} -> {$correctTotalAmount}");
            $this->line("  shipping_cost:    {$oldShippingCost} -> {$correctShippingCost}");
            $this->line("  COGS:             {$oldCOGS} -> {$correctCOGS}");
            $this->line("  settlement_fee:   {$oldSettlementFee} -> {$correctSettlementFee}");
            $this->line("  net_payable:      {$oldNetPayable} -> {$correctNetPayable}");
            $this->line("  sales_amount:     {$oldSalesAmount} -> {$correctSalesAmount}");

            if (abs($oldTotalAmount - $correctTotalAmount) < 0.01
                && abs($oldCOGS - $correctCOGS) < 0.01
                && abs($oldShippingCost - $correctShippingCost) < 0.01
                && abs($oldSettlementFee - $correctSettlementFee) < 0.01) {
                $this->info("  Already correct. Skipping.");
                $skipped++;
                continue;
            }

            if ($isDryRun) {
                $adjustments = [];
                if (abs($oldTotalAmount - $correctTotalAmount) >= 0.01) {
                    $adjustments[] = "AR: adjust by " . ($oldTotalAmount - $correctTotalAmount);
                }
                if (abs($oldCOGS - $correctCOGS) >= 0.01) {
                    $adjustments[] = "COGS/Inventory: adjust by " . ($oldCOGS - $correctCOGS);
                }
                $this->info("  WOULD FIX: " . implode(', ', $adjustments));
                $fixed++;
                continue;
            }

            try {
                DB::beginTransaction();

                $transactionDate = now()->toDateString();
                $referenceNo = $settlement->settlement_no . '-ADJ';
                $diffAmount = $oldTotalAmount - $correctTotalAmount;
                $diffCOGS = $oldCOGS - $correctCOGS;

                // --- ADJUSTMENT ENTRIES ---

                // 1. AR: old was deducted by oldTotalAmount, should be deducted by correctTotalAmount
                //    Need to ADD BACK the difference: balance += diffAmount
                if (abs($diffAmount) >= 0.01) {
                    $arAccount = ChartOfAccount::getAccountsReceivable();
                    if ($arAccount) {
                        $arAccount->current_balance += $diffAmount;
                        $arAccount->save();

                        $this->createAdjLog($arAccount->id, $transactionDate, $referenceNo,
                            $diffAmount > 0 ? $diffAmount : 0,
                            $diffAmount < 0 ? abs($diffAmount) : 0,
                            'AR correction for settlement ' . $settlement->settlement_no . ' (Order #' . $order->code . '): ' . ($diffAmount > 0 ? 'added back' : 'reduced') . ' ' . number_format(abs($diffAmount), 2),
                            $settlement, $order);
                    }
                }

                // 2. Non-POS: Inventory / COGS / Sales adjustments
                if (!$isPosOrder) {
                    // Inventory: old was reduced by oldCOGS, should be reduced by correctCOGS
                    // Need to ADD BACK the difference: balance += diffCOGS
                    if (abs($diffCOGS) >= 0.01) {
                        $invAccount = ChartOfAccount::getInventoryAccount();
                        if ($invAccount) {
                            $invAccount->current_balance += $diffCOGS;
                            $invAccount->save();

                            $this->createAdjLog($invAccount->id, $transactionDate, $referenceNo,
                                $diffCOGS > 0 ? $diffCOGS : 0,
                                $diffCOGS < 0 ? abs($diffCOGS) : 0,
                                'Inventory correction for settlement ' . $settlement->settlement_no . ' (Order #' . $order->code . '): ' . ($diffCOGS > 0 ? 'added back' : 'reduced') . ' ' . number_format(abs($diffCOGS), 2),
                                $settlement, $order);
                        }

                        // COGS: old was increased by oldCOGS, should be increased by correctCOGS
                        // Need to SUBTRACT the difference: balance -= diffCOGS
                        $cogsAccount = ChartOfAccount::where('type', 'expense')
                            ->where('sub_type', 'cost_of_sales')
                            ->where('name', 'Cost of Goods Sold')
                            ->first();
                        if (!$cogsAccount) {
                            $cogsAccount = ChartOfAccount::where('sub_type', 'cost_of_sales')
                                ->where('status', 'active')->first();
                        }
                        if ($cogsAccount) {
                            $cogsAccount->current_balance -= $diffCOGS;
                            $cogsAccount->save();

                            $this->createAdjLog($cogsAccount->id, $transactionDate, $referenceNo,
                                $diffCOGS < 0 ? abs($diffCOGS) : 0,
                                $diffCOGS > 0 ? $diffCOGS : 0,
                                'COGS correction for settlement ' . $settlement->settlement_no . ' (Order #' . $order->code . '): ' . ($diffCOGS > 0 ? 'reduced' : 'increased') . ' ' . number_format(abs($diffCOGS), 2),
                                $settlement, $order);
                        }
                    }

                    // Sales: old vs correct
                    $diffSales = $oldSalesAmount - $correctSalesAmount;
                    if (abs($diffSales) >= 0.01) {
                        $salesAccount = ChartOfAccount::getSalesAccount();
                        if (!$salesAccount) {
                            $salesAccount = ChartOfAccount::where('code', '4-4100')
                                ->where('sub_type', 'sales')
                                ->where('status', 'active')->first();
                        }
                        if ($salesAccount) {
                            $salesAccount->current_balance -= $diffSales;
                            $salesAccount->save();

                            $this->createAdjLog($salesAccount->id, $transactionDate, $referenceNo,
                                $diffSales < 0 ? abs($diffSales) : 0,
                                $diffSales > 0 ? $diffSales : 0,
                                'Sales correction for settlement ' . $settlement->settlement_no . ' (Order #' . $order->code . '): ' . ($diffSales > 0 ? 'reduced' : 'increased') . ' ' . number_format(abs($diffSales), 2),
                                $settlement, $order);
                        }
                    }
                }

                // 3. Settlement fee: old may have been > 0, should be 0 for partial
                //    If oldSettlementFee > 0, need to reverse it
                if ($oldSettlementFee > 0) {
                    $settlementExpenseAccount = ChartOfAccount::getCourierSettlementExpenseAccount();
                    if ($settlementExpenseAccount) {
                        $settlementExpenseAccount->current_balance -= $oldSettlementFee;
                        $settlementExpenseAccount->save();

                        $this->createAdjLog($settlementExpenseAccount->id, $transactionDate, $referenceNo,
                            0, $oldSettlementFee,
                            'Settlement fee reversal (partial delivery - should be 0) for ' . $settlement->settlement_no . ' (Order #' . $order->code . ')',
                            $settlement, $order);
                    }
                }

                // 4. Settlement account (cash/bank) net adjustment
                $diffNetPayable = $oldNetPayable - $correctNetPayable;
                if (abs($diffNetPayable) >= 0.01) {
                    $account = $settlement->account;
                    if ($account) {
                        $account->current_balance -= $diffNetPayable;
                        $account->save();

                        $this->createAdjLog($account->id, $transactionDate, $referenceNo,
                            $diffNetPayable < 0 ? abs($diffNetPayable) : 0,
                            $diffNetPayable > 0 ? $diffNetPayable : 0,
                            'Net payable correction for settlement ' . $settlement->settlement_no . ' (Order #' . $order->code . '): ' . ($diffNetPayable > 0 ? 'reduced' : 'increased') . ' ' . number_format(abs($diffNetPayable), 2),
                            $settlement, $order);
                    }
                }

                // 5. Courier income / bad debt adjustment based on shipping cost change
                if ($partialPaymentChoice === 'customer') {
                    $oldCourierDiff = $oldShippingCost - $courierFee;
                    $correctCourierDiff = $correctShippingCost - $courierFee;

                    if ($oldCourierDiff > 0 && $correctCourierDiff > 0) {
                        // Both positive: adjust Courier Income by the difference
                        $diffCI = $oldCourierDiff - $correctCourierDiff;
                        if (abs($diffCI) >= 0.01) {
                            $ciAccount = ChartOfAccount::getCourierIncomeAccount();
                            if ($ciAccount) {
                                $ciAccount->current_balance -= $diffCI;
                                $ciAccount->save();

                                $this->createAdjLog($ciAccount->id, $transactionDate, $referenceNo,
                                    $diffCI < 0 ? abs($diffCI) : 0,
                                    $diffCI > 0 ? $diffCI : 0,
                                    'Courier income correction for settlement ' . $settlement->settlement_no . ' (Order #' . $order->code . ')',
                                    $settlement, $order);
                            }
                            // Settlement account also gets this amount
                            if ($account) {
                                $account->current_balance -= $diffCI;
                                $account->save();
                            }
                        }
                    } elseif ($oldCourierDiff <= 0 && $correctCourierDiff > 0) {
                        // Was loss, now profit: reverse bad debt, add courier income
                        // ... complex, handle manually
                        $this->warn("  Complex courier income/bad debt transition. Manual review needed.");
                    } elseif ($oldCourierDiff > 0 && $correctCourierDiff <= 0) {
                        // Was profit, now loss: reverse courier income, add bad debt
                        // ... complex
                        $this->warn("  Complex courier income/bad debt transition. Manual review needed.");
                    } elseif ($oldCourierDiff < 0 && $correctCourierDiff < 0) {
                        // Both negative: adjust Bad Debt
                        $diffBD = abs($oldCourierDiff) - abs($correctCourierDiff);
                        if (abs($diffBD) >= 0.01) {
                            $bdAccount = ChartOfAccount::getBadDebtExpenseAccount();
                            if ($bdAccount) {
                                $bdAccount->current_balance -= $diffBD;
                                $bdAccount->save();

                                $this->createAdjLog($bdAccount->id, $transactionDate, $referenceNo,
                                    $diffBD < 0 ? abs($diffBD) : 0,
                                    $diffBD > 0 ? $diffBD : 0,
                                    'Bad debt correction for settlement ' . $settlement->settlement_no . ' (Order #' . $order->code . ')',
                                    $settlement, $order);
                            }
                        }
                    }
                } elseif ($partialPaymentChoice === 'admin') {
                    // Admin pays courier fee - should be same regardless
                    // unless shipping cost changed the total... but admin always pays courierFee
                }

                // 6. Update settlement record
                $settlement->total_amount = $correctTotalAmount;
                $settlement->settlement_fee = $correctSettlementFee;
                $settlement->net_payable = $correctNetPayable;
                $settlement->notes = ($settlement->notes ? $settlement->notes . "\n" : '')
                    . '[FIXED ' . now() . '] Adjusted amounts: totalAmount ' . $oldTotalAmount . '->' . $correctTotalAmount
                    . ', COGS ' . $oldCOGS . '->' . $correctCOGS
                    . ', shipping ' . $oldShippingCost . '->' . $correctShippingCost
                    . ', fee ' . $oldSettlementFee . '->' . $correctSettlementFee
                    . ', net ' . $oldNetPayable . '->' . $correctNetPayable;
                $settlement->save();

                // 7. Update order due_amount
                $order->due_amount = $correctTotalAmount;
                $order->save();

                DB::commit();
                $fixed++;
                $this->info("  FIXED: Settlement #{$settlement->settlement_no} adjusted.");
            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("  FAILED: {$e->getMessage()}");
                Log::error('Fix partial settlement error: ' . $e->getMessage(), [
                    'settlement_id' => $settlement->id,
                    'order_id' => $order->id,
                ]);
                $errors++;
            }
        }

        $this->line("----------------------------------------");
        if ($isDryRun) {
            $this->info("Dry run complete. {$fixed} would be fixed, {$skipped} skipped, {$errors} errors.");
        } else {
            $this->info("Complete. {$fixed} fixed, {$skipped} skipped, {$errors} errors.");
        }

        return 0;
    }

    private function createAdjLog($accountId, $date, $refNo, $debit, $credit, $desc, $settlement, $order)
    {
        $account = ChartOfAccount::find($accountId);
        $balance = $account ? $account->current_balance : 0;

        return AccountStatementLog::create([
            'account_id'       => $accountId,
            'transaction_date' => $date,
            'reference_no'     => $refNo,
            'transaction_type' => 'courier_settlement_adjustment',
            'debit'            => $debit,
            'credit'           => $credit,
            'balance'          => $balance,
            'description'      => $desc,
            'source_id'        => $settlement->id,
            'source_type'      => CourierSettlement::class,
            'voucher_id'       => null,
            'order_id'         => $order->id,
        ]);
    }
}
