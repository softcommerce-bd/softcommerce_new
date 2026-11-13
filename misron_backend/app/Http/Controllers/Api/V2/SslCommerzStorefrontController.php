<?php

namespace App\Http\Controllers\Api\V2;

use App\Models\CombinedOrder;
use App\Models\Order;
use App\Services\Funnel\Checkout\PaymentReturn;
use App\Services\Payments\SslCommerzGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * SSLCommerz hosted checkout for orders placed on the storefront. The older SslCommerzController
 * (app flow) answers with JSON pages; this one sends the shopper back to the storefront.
 */
class SslCommerzStorefrontController extends Controller
{
    public function __construct(private SslCommerzGateway $gateway)
    {
    }

    public function begin(Request $request)
    {
        if (!$this->gateway->configured()) {
            return $this->failed(translate('SSLCommerz is not set up. Please contact support.'));
        }

        try {
            $combinedOrderId = Crypt::decrypt($request->combined_order_id);
        } catch (\Exception $e) {
            return $this->failed(translate('Order not found'));
        }

        $combined = CombinedOrder::find($combinedOrderId);
        if ($combined === null) {
            return $this->failed(translate('Order not found'));
        }
        if ($refusal = $this->gateway->refusal($combined)) {
            return $this->failed(translate($refusal));
        }

        $tranId = $this->gateway->transactionId($combined->id);
        $shipping = json_decode(optional($combined->orders->first())->shipping_address, true) ?? [];

        $post = [
            'total_amount' => $combined->grand_total,
            'currency' => 'BDT',
            'tran_id' => $tranId,
            'value_a' => $combined->id,
            'success_url' => url('api/v2/sslcommerz/storefront/return/success'),
            'fail_url' => url('api/v2/sslcommerz/storefront/return/fail'),
            'cancel_url' => url('api/v2/sslcommerz/storefront/return/cancel'),
            'ipn_url' => url('api/v2/sslcommerz/storefront/ipn'),
            'cus_name' => $shipping['name'] ?? 'Customer',
            'cus_email' => $shipping['email'] ?? 'customer@example.com',
            'cus_add1' => $shipping['address'] ?? 'Dhaka',
            'cus_city' => $shipping['city'] ?? 'Dhaka',
            'cus_postcode' => '1000',
            'cus_country' => 'Bangladesh',
            'cus_phone' => $shipping['phone'] ?? '01700000000',
            'shipping_method' => 'NO',
            'product_name' => 'Order #' . $combined->id,
            'product_category' => 'General',
            'product_profile' => 'general',
            'num_of_item' => max(1, (int) $combined->orders->sum(fn ($o) => $o->orderDetails->sum('quantity'))),
        ];

        $answer = $this->gateway->initiate($post);
        if ($answer === false) {
            return $this->failed(translate('Connectivity Issue. Please contact your SSLCommerz manager'));
        }

        if (strtoupper((string) ($answer['status'] ?? '')) === 'SUCCESS' && !empty($answer['GatewayPageURL'])) {
            $this->gateway->recordAttempt($combined, $tranId);

            return response()->json(['result' => true, 'url' => $answer['GatewayPageURL'], 'message' => translate('Redirect Url is found')]);
        }

        return response()->json(['result' => false, 'url' => '', 'message' => $answer['failedreason'] ?? translate('Payment Failed')]);
    }

    /** The shopper's browser is sent back here by SSLCommerz (a form POST). */
    public function back(Request $request, string $result)
    {
        Log::info('SSLCommerz return:', ['result' => $result, 'tran_id' => $request->tran_id]);

        $tranId = (string) $request->tran_id;
        $combinedOrderId = SslCommerzGateway::combinedOrderId($tranId);

        if ($result === 'success') {
            $state = $this->gateway->confirm((string) $request->val_id, $tranId);
        } else {
            $state = $this->gateway->failedReturn($tranId, $result === 'cancel' ? 'was cancelled' : 'failed');
        }

        $order = Order::where('combined_order_id', $combinedOrderId)->first();

        if ($funnelUrl = PaymentReturn::urlFor($order, $state)) {
            return redirect($funnelUrl);
        }

        if ($state === SslCommerzGateway::PAID) {
            $guest = optional($order)->guest_id ? '?guest=true' : '';

            return redirect(env('FRONTEND_URL') . '/confirm-order/' . urlencode(Crypt::encrypt($combinedOrderId)) . $guest);
        }

        return redirect(env('FRONTEND_URL') . '/payment-failed?order=' . $combinedOrderId);
    }

    /** Server to server notification; never cancels anything. */
    public function ipn(Request $request)
    {
        Log::info('SSLCommerz ipn:', ['tran_id' => $request->tran_id, 'status' => $request->status]);
        $this->gateway->confirm((string) $request->val_id, (string) $request->tran_id, false);

        return response()->json(['status' => 'received']);
    }
}
