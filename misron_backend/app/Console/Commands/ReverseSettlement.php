<?php

namespace App\Console\Commands;

use App\Models\AccountStatementLog;
use App\Models\ChartOfAccount;
use App\Models\CourierSettlement;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReverseSettlement extends Command
{
    protected $signature = 'courier:reverse-settlement {settlement_no} {--dry-run : Show without making changes}';

    protected $description = 'Reverse a courier settlement that was applied to a cancelled order';

    public function handle()
    {
        $settlementNo = $this->argument('settlement_no');
        $isDryRun = $this->option('dry-run');

        $settlement = CourierSettlement::where('settlement_no', $settlementNo)->first();
        if (!$settlement) {
            $this->error("Settlement {$settlementNo} not found.");
            return 1;
        }

        $order = Order::find($settlement->order_id);
        if (!$order) {
            $this->error("Order #{$settlement->order_id} not found.");
            return 1;
        }

        $this->line("Settlement: {$settlement->settlement_no}");
        $this->line("Order:      #{$order->id} ({$order->code})");
        $this->line("Status:     courier={$order->courier_status}, payment={$order->payment_status}, settled={$order->settlement_status}");

        if ($isDryRun) {
            $this->warn('--- DRY RUN MODE --- No changes will be made.');
        }

        $logs = AccountStatementLog::where('source_type', CourierSettlement::class)
            ->where('source_id', $settlement->id)
            ->orderBy('id')
            ->get();

        if ($logs->isEmpty()) {
            $this->warn('No account statement logs found for this settlement.');
        }

        $this->line("\n=== Entries to reverse ===");
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($logs as $l) {
            $acc = ChartOfAccount::find($l->account_id);
            $accName = $acc ? $acc->name : "acc#{$l->account_id}";
            $this->line("  #{$l->id} {$accName}: debit={$l->debit} credit={$l->credit}");
            $totalDebit += $l->debit;
            $totalCredit += $l->credit;
        }
        $this->line("  Total to reverse: debit {$totalDebit}, credit {$totalCredit}");

        if ($isDryRun) {
            $this->info("\nDry run complete. Run without --dry-run to apply.");
            return 0;
        }

        try {
            DB::beginTransaction();

            foreach ($logs as $l) {
                $account = ChartOfAccount::find($l->account_id);
                if (!$account) {
                    $this->warn("  Account #{$l->account_id} not found, skipping.");
                    continue;
                }

                // Reverse: subtract what was added, add back what was subtracted
                $account->current_balance -= $l->debit;
                $account->current_balance += $l->credit;
                $account->save();

                // Log the reversal
                AccountStatementLog::create([
                    'account_id'       => $l->account_id,
                    'transaction_date' => now()->toDateString(),
                    'reference_no'     => 'REV-' . $settlement->settlement_no,
                    'transaction_type' => 'courier_settlement_reversal',
                    'debit'            => $l->credit,
                    'credit'           => $l->debit,
                    'balance'          => $account->current_balance,
                    'description'      => 'Reversal of settlement ' . $settlement->settlement_no . ' for cancelled order #' . $order->code,
                    'source_id'        => $settlement->id,
                    'source_type'      => CourierSettlement::class,
                    'voucher_id'       => null,
                    'order_id'         => $order->id,
                ]);

                $this->line("  Reversed acc#{$l->account_id} ({$account->name}): balance now {$account->current_balance}");
            }

            // Mark settlement as reversed
            $settlement->status = 'reversed';
            $settlement->notes = ($settlement->notes ? $settlement->notes . "\n" : '')
                . '[REVERSED ' . now() . '] Order was cancelled, settlement reversed.';
            $settlement->save();

            // Mark order settlement_status as false
            $order->settlement_status = 'false';
            $order->save();

            DB::commit();
            $this->info("\nSettlement {$settlementNo} reversed successfully.");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("FAILED: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }
}
