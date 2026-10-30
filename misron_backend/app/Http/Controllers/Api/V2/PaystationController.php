<?php


namespace App\Http\Controllers\Api\V2;


use App\Models\CombinedOrder;
use App\Models\Order;
use App\Services\Funnel\Checkout\PaymentReturn;
use App\Services\Payments\PaystationGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Paystation hosted checkout for website and funnel orders.
 *
 * The gateway rules (what counts as paid, when an order may be cancelled) live in
 * App\Services\Payments\PaystationGateway, shared with orders:expire-unpaid.
 */
class PaystationController extends Controller
{
    private PaystationGateway $gateway;

    public function __construct(PaystationGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Creates the hosted checkout link for an already stored order and hands
     * the url back to the client, which redirects the browser to it.
     */
    public function begin(Request $request)
    {
        $payment_type = $request->payment_type ?? 'cart_payment';

        if ($payment_type != 'cart_payment') {
            return $this->failed(translate('This payment type is not supported yet'));
        }

        // The storefront only ever holds the encrypted id, the same one the
        // order details endpoint is given.
        try {
            $combined_order_id = Crypt::decrypt($request->combined_order_id);
        } catch (\Exception $e) {
            return $this->failed(translate('Order not found'));
        }

        $combined_order = CombinedOrder::find($combined_order_id);

        if ($combined_order == null) {
            return $this->failed(translate('Order not found'));
        }

        // A paid order would be charged twice, and a cancelled one has already given
        // its stock back: neither may be sent to the gateway.
        if ($refusal = $this->gateway->refusal($combined_order)) {
            return $this->failed(translate($refusal));
        }

        $invoice_number = $this->gateway->invoiceNumber($combined_order->id);

        // The first order carries the address the shopper filled in at checkout.
        $shipping_address = json_decode(optional($combined_order->orders->first())->shipping_address, true) ?? [];

        $post_data = array();
        $post_data['invoice_number'] = $invoice_number;
        $post_data['currency'] = 'BDT';
        $post_data['payment_amount'] = $combined_order->grand_total;
        // 0 keeps the gateway charge on the merchant, so the shopper pays the
        // total that was shown to them on the checkout page.
        $post_data['pay_with_charge'] = 0;
        $post_data['reference'] = $combined_order->id;

        # CUSTOMER INFORMATION
        $post_data['cust_name'] = $shipping_address['name'] ?? 'Customer';
        $post_data['cust_phone'] = $shipping_address['phone'] ?? '';
        $post_data['cust_email'] = $shipping_address['email'] ?? '';
        $post_data['cust_address'] = $shipping_address['address'] ?? '';

        // Falls back to APP_URL, which is enough in production. Locally APP_URL
        // carries no port, so the gateway needs to be told where to come back to.
        $post_data['callback_url'] = env('PAYSTATION_CALLBACK_URL', url("api/v2/paystation/callback"));

        $response = $this->gateway->initiate($post_data);

        if ($response == false) {
            return $this->failed(translate('Connectivity Issue. Please contact your paystation manager'));
        }

        if (isset($response['status_code']) && $response['status_code'] == 200 && isset($response['payment_url'])) {
            // Kept so the outcome can be asked for later, even if the customer never returns.
            $this->gateway->recordAttempt($combined_order, $invoice_number);

            return response()->json([
                'result' => true,
                'url' => $response['payment_url'],
                'message' => translate('Redirect Url is found')
            ]);
        }

        return response()->json([
            'result' => false,
            'url' => '',
            'message' => $response['message'] ?? translate('Payment Failed')
        ]);
    }

    /**
     * Where the gateway sends the shopper's browser back to. The query string
     * it carries is only a hint, so the transaction is confirmed against the
     * status api before anything is marked paid, and the shopper is then
     * handed back to the storefront.
     *
     * Only a confirmed failure cancels the order and gives its stock back. When the
     * gateway cannot be reached or gives no clear answer, the order is left as it is:
     * the money may have been taken, and the notification or the expiry job settles
     * it once the gateway answers.
     */
    public function callback(Request $request)
    {
        Log::info('Paystation callback:', $request->all());

        $invoice_number = (string) $request->invoice_number;
        $combined_order_id = PaystationGateway::combinedOrderId($invoice_number);

        $state = $this->gateway->confirm($invoice_number);
        $order = Order::where('combined_order_id', $combined_order_id)->first();

        // An order placed through a sales funnel goes back to that funnel's page.
        if ($funnelUrl = PaymentReturn::urlFor($order, $state)) {
            return redirect($funnelUrl);
        }

        if ($state === PaystationGateway::PAID) {
            // A shopper coming back from the gateway holds no login token, so an
            // order placed as a guest has to say so, or the confirmation page
            // asks for the order as a logged in customer and is turned away.
            $guest = optional($order)->guest_id ? '?guest=true' : '';

            return redirect(env('FRONTEND_URL') . '/confirm-order/' . urlencode(Crypt::encrypt($combined_order_id)) . $guest);
        }

        return redirect(env('FRONTEND_URL') . '/payment-failed?order=' . $combined_order_id);
    }

    /**
     * Server to server notification. Fired only for successful transactions and
     * retried until it is answered with a 2xx, so it has to stay idempotent.
     * It never cancels anything: a notification is about a payment that went through.
     */
    public function ipn(Request $request)
    {
        Log::info('Paystation ipn:', $request->all());

        $this->gateway->confirm((string) $request->invoice_number, false);

        // Acknowledged either way, otherwise the gateway keeps retrying a
        // notification that will never apply to this store.
        return response()->json(['status' => 'success']);
    }
}
