<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixPartialDueAmounts extends Command
{
    protected $signature = 'courier:fix-due-amounts {--dry-run : Show what would be fixed without making changes}';

    protected $description = 'Fix due_amount for unsettled partial_delivered orders';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $updated = 0;

        $orders = Order::where('courier_status', 'partial_delivered')
            ->where(function ($q) {
                $q->whereNull('settlement_status')
                  ->orWhere('settlement_status', 'false')
                  ->orWhere('settlement_status', '0')
                  ->orWhere('settlement_status', '');
            })
            ->get();

        if ($orders->isEmpty()) {
            $this->info('No unsettled partial_delivered orders found.');
            return 0;
        }

        $this->info("Found {$orders->count()} unsettled partial_delivered order(s).");
        if ($isDryRun) {
            $this->warn('--- DRY RUN MODE --- No changes will be made.');
        }

        foreach ($orders as $order) {
            if ($order->order_from === 'pos') {
                $paymentAmounts = json_decode($order->payment_amounts, true) ?? [];
                $totalPaid = array_sum($paymentAmounts);
                $newDue = max(0, $order->grand_total - $totalPaid);
            } else {
                $newDue = $order->grand_total;
            }

            $this->line("Order #{$order->id} ({$order->code}): due_amount {$order->due_amount} -> {$newDue}" . ($isDryRun ? ' (dry-run)' : ''));

            if (!$isDryRun) {
                DB::table('orders')
                    ->where('id', $order->id)
                    ->update(['due_amount' => $newDue]);
            }
            $updated++;
        }

        $this->info("Done. {$updated} order(s) " . ($isDryRun ? 'would be updated.' : 'updated.'));
        return 0;
    }
}
