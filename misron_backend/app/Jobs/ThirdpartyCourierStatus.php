<?php

namespace App\Jobs;

use App\Http\Controllers\OrderController;
use App\Models\Order;
use App\Services\Couriers\CourierManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ThirdpartyCourierStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public $tries = 3;
    public $timeout = 30;

    public function __construct(
        public int $orderId,
        public string $courier
    ) {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    
    public function handle()
    {
        $order = Order::find($this->orderId);
        if (!$order) return;

        // For old orders, courier_name may be null, use $this->courier from dispatch
        if (!$order->courier_name) {
            $order->courier_name = $this->courier;
        }

        // For old orders, set tracking_id from previous fields
        if (!$order->courier_tracking_id) {
            if ($this->courier === 'steadfast' && $order->steadfast_id) {
                $order->courier_tracking_id = $order->steadfast_id;
            }
            if ($this->courier === 'pathao' && $order->pathao_id) {
                $order->courier_tracking_id = $order->pathao_id;
            }
        }

        // Save any changes to new fields
        $order->save();

        // Skip syncing if tracking ID still missing
        if (!$order->courier_tracking_id) return;

        // Skip if already final status
        if (in_array($order->courier_status, ['delivered','cancelled','returned'])) {
            return;
        }

        try {
            $status = CourierManager::getStatus(
                $order->courier_name,
                $order->courier_tracking_id
            );

            if ($status === 'error') return;

            $order->update([
                'courier_status' => $status,
                'courier_status_updated_at' => now(),
            ]);

        } catch (\Throwable $e) {
            Log::warning('Courier sync failed', [
                'order_id' => $order->id,
                'courier' => $order->courier_name,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
