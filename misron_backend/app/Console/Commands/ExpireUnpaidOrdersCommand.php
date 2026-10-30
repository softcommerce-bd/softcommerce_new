<?php

namespace App\Console\Commands;

use App\Models\CombinedOrder;
use App\Models\Order;
use App\Models\PaymentGatewayAttempt;
use App\Services\Payments\PaystationGateway;
use Illuminate\Console\Command;

/**
 * Gives back the stock held by online-payment orders that were never paid.
 *
 * Stock comes off the shelf when an order is stored, before the customer reaches
 * the gateway. A customer who closes the payment page leaves those units held by
 * an order nobody will pay for. This command looks at orders that are still
 * unpaid after funnel.unpaid_online_orders.expire_after_minutes and:
 *
 *  - asks the gateway about every payment session opened for the order; if one
 *    was paid, the order is marked paid (a late or missed notification);
 *  - cancels the order and returns its stock only when the gateway confirms that
 *    every session failed, or when no session was ever opened;
 *  - leaves the order alone whenever the gateway gives no clear answer, because
 *    the money may have been taken. After give_up_after_hours such an order gets
 *    a note asking staff to check the gateway's dashboard, and is not retried.
 *
 * Website and funnel orders are treated alike. Runs every five minutes from the
 * scheduler once ONLINE_ORDER_EXPIRY_ENABLED=true.
 */
class ExpireUnpaidOrdersCommand extends Command
{
    protected $signature = 'orders:expire-unpaid {--dry-run : Report what would happen without changing anything} {--force : Run even when expiry is switched off}';

    protected $description = 'Cancel unpaid online-payment orders whose payment never went through, returning their stock';

    private const REVIEW_NOTE = 'ATTENTION: the payment gateway did not confirm whether this order was paid. Check the gateway dashboard before cancelling or shipping.';

    public function handle(PaystationGateway $gateway): int
    {
        $config = config('funnel.unpaid_online_orders');
        if (!$config['expire'] && !$this->option('force')) {
            $this->info('Expiry of unpaid online orders is switched off (ONLINE_ORDER_EXPIRY_ENABLED).');

            return self::SUCCESS;
        }
        if (!PaymentGatewayAttempt::ready()) {
            $this->warn('The payment_gateway_attempts table is missing (sqlupdates/sales_funnel_v5_analytics.sql); nothing is expired without it.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $cutoff = now()->subMinutes(max(15, (int) $config['expire_after_minutes']));
        $giveUp = now()->subHours(max(1, (int) $config['give_up_after_hours']));

        $ids = Order::query()
            ->where('payment_status', 'unpaid')
            ->where(fn ($q) => $q->whereNull('delivery_status')->orWhere('delivery_status', '!=', 'cancelled'))
            ->whereIn('payment_type', $config['gateways'])
            ->where('created_at', '<', $cutoff)
            ->where(fn ($q) => $q->whereNull('additional_info')->orWhere('additional_info', 'not like', '%' . self::REVIEW_NOTE . '%'))
            ->orderBy('combined_order_id')
            ->limit(200)
            ->distinct()
            ->pluck('combined_order_id');

        $counts = ['paid' => 0, 'cancelled' => 0, 'waiting' => 0, 'review' => 0];

        foreach ($ids as $id) {
            $combined = CombinedOrder::find($id);
            if ($combined === null) {
                continue;
            }
            $outcome = $this->settle($gateway, $combined, $giveUp, $dry);
            $counts[$outcome]++;
            $this->line(($dry ? '[dry run] ' : '') . "combined order {$id}: {$outcome}");
        }

        $this->info(sprintf('%d paid, %d cancelled, %d still waiting for the gateway, %d left for staff.', $counts['paid'], $counts['cancelled'], $counts['waiting'], $counts['review']));

        return self::SUCCESS;
    }

    private function settle(PaystationGateway $gateway, CombinedOrder $combined, $giveUp, bool $dry): string
    {
        $attempts = PaymentGatewayAttempt::where('combined_order_id', $combined->id)->orderBy('id')->get();

        if ($attempts->isEmpty()) {
            if (!$dry) {
                $gateway->cancelUnpaid($combined, 'no online payment was started in time.');
            }

            return 'cancelled';
        }

        $allFailed = true;
        foreach ($attempts as $attempt) {
            if ($attempt->status === PaymentGatewayAttempt::FAILED) {
                continue;
            }
            $state = $dry ? $gateway->status($attempt->invoice_number, $combined)['state'] : $gateway->confirm($attempt->invoice_number, false);
            if ($state === PaystationGateway::PAID) {
                return 'paid';
            }
            if ($state !== PaystationGateway::FAILED) {
                $allFailed = false;
            }
        }

        if ($allFailed) {
            if (!$dry) {
                $gateway->cancelUnpaid($combined, 'the online payment failed or was cancelled at the gateway.');
            }

            return 'cancelled';
        }

        if ($combined->created_at < $giveUp) {
            if (!$dry) {
                foreach (Order::where('combined_order_id', $combined->id)->get() as $order) {
                    $order->additional_info = trim($order->additional_info . "\n" . self::REVIEW_NOTE);
                    $order->save();
                }
            }

            return 'review';
        }

        return 'waiting';
    }
}
