<?php

namespace App\Console\Commands;

use App\Jobs\ThirdpartyCourierStatus;
use App\Models\Order;
use Illuminate\Console\Command;

class SyncCourierStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'courier:sync-status {--force}';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all 3rd party status';

    /**
     * Execute the console command.
     *
     * @return int
     */

    public function handle()
    {
        // Base query
        $query = Order::query();

        // Force mode: pick all orders sent to 3rd party in the past.
        // Match on courier_name, since delivery_status is 'courier_assigned'
        // once a courier is set, not the courier's own name.
        if ($this->option('force')) {
            $query->whereIn('courier_name', ['steadfast', 'pathao'])
                ->whereNotNull('courier_tracking_id');
        } else {
            // Normal mode: only active orders with tracking
            $query->whereNotNull('courier_name')
                ->whereNotNull('courier_tracking_id')
                ->whereNotIn('courier_status', ['delivered','cancelled','returned'])
                ->where(function ($q) {
                    $q->whereNull('courier_status_updated_at')
                        ->orWhere('courier_status_updated_at', '<', now()->subMinutes(15));
                });
        }

        // Chunk in batches of 100 for safety
        $query->chunkById(100, function ($orders) {
            foreach ($orders as $order) {
                // Dispatch with the courier name, not delivery_status.
                ThirdpartyCourierStatus::dispatch($order->id, $order->courier_name);
            }

            $this->info('Dispatched ' . $orders->count() . ' courier jobs.');
        });
    }
}
