<?php

namespace App\Services\Funnel\Checkout;

use App\Models\Funnel;
use App\Models\Order;
use App\Services\Payments\PaystationGateway;

/**
 * Where the payment gateway sends a customer back to. An order placed through a
 * funnel returns to that funnel's page, with the receipt in the address so the
 * confirmation area can show the order; every other order keeps going to the
 * website's own pages (the caller decides, this only answers for funnel orders).
 *
 * A funnel that has gone offline since the order was placed cannot show the
 * confirmation any more (its address answers 404), so such an order also goes
 * to the website's pages.
 */
class PaymentReturn
{
    /**
     * Address to send the browser to, or null when the order did not come from a live funnel.
     *
     * @param  string|bool $state PaystationGateway::PAID / FAILED / UNKNOWN (true/false accepted for paid/failed)
     */
    public static function urlFor(?Order $order, $state): ?string
    {
        if ($order === null || empty($order->funnel_id)) {
            return null;
        }

        $funnel = Funnel::find($order->funnel_id);
        if ($funnel === null || !$funnel->isLive()) {
            return null;
        }

        if (is_bool($state)) {
            $state = $state ? PaystationGateway::PAID : PaystationGateway::FAILED;
        }
        $payment = [PaystationGateway::PAID => 'success', PaystationGateway::FAILED => 'failed'][$state] ?? 'pending';

        return rtrim((string) env('FRONTEND_URL'), '/') . '/funnel/' . $funnel->slug
            . '?receipt=' . urlencode(Receipt::make((int) $order->combined_order_id, (int) $funnel->id))
            . '&payment=' . $payment;
    }
}
