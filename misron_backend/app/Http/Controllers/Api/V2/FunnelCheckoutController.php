<?php

namespace App\Http\Controllers\Api\V2;

use App\Models\City;
use App\Models\CombinedOrder;
use App\Models\Funnel;
use App\Models\Order;
use App\Services\Funnel\Checkout\CheckoutConfig;
use App\Services\Funnel\Checkout\CheckoutException;
use App\Services\Funnel\Checkout\CheckoutInput;
use App\Services\Funnel\Checkout\FunnelOrderService;
use App\Services\Funnel\Checkout\PaymentOptions;
use App\Services\Funnel\Checkout\Receipt;
use App\Services\Funnel\Offers\Money;
use App\Services\Funnel\Offers\PublishedOffers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * Customer-facing checkout of a published funnel.
 *
 *   GET  {slug}/checkout/config        what the order form asks for, and what can be paid with
 *   POST {slug}/checkout               place the order (idempotent)
 *   GET  {slug}/orders/{receipt}       the order summary for the confirmation area
 *   POST {slug}/orders/{receipt}/pay   start the online payment of an unpaid order
 *
 * Everything is read from the published version of the page. The request carries
 * choices (offer key, variation, quantity) and customer details, never an amount.
 */
class FunnelCheckoutController extends Controller
{
    private PublishedOffers $published;
    private FunnelOrderService $orders;
    private PaymentOptions $payments;

    public function __construct(PublishedOffers $published, FunnelOrderService $orders, PaymentOptions $payments)
    {
        $this->published = $published;
        $this->orders = $orders;
        $this->payments = $payments;
    }

    public function config($slug)
    {
        $funnel = $this->published->liveFunnel((string) $slug);
        if (!$funnel) {
            return $this->notFound();
        }

        $config = CheckoutConfig::fromDocument($funnel->publishedVersion->content);
        $refusal = $this->orders->notReady();

        return response()->json([
            'result' => true,
            // Whether the form can take orders right now, and if not, why.
            'enabled' => $refusal === null && $config->present(),
            'reason' => $refusal ? $refusal->errorCode() : ($config->present() ? null : 'no_checkout'),
            'checkout' => $config->toArray(),
            'payment_methods' => $this->payments->forPage($config),
            // The delivery areas the store prices (its "cities"), with today's charge for each.
            'districts' => City::where('status', 1)->orderBy('name')->get(['id', 'name', 'cost'])->map(fn ($city) => [
                'id' => (int) $city->id, 'name' => $city->name, 'charge' => $this->orders->money(Money::cents($city->cost)),
            ])->all(),
            'otp_required' => (int) get_setting('checkout_otp_activation') === 1,
        ])->header('Cache-Control', 'no-store');
    }

    public function place(Request $request, $slug)
    {
        $funnel = $this->published->liveFunnel((string) $slug);
        if (!$funnel) {
            return $this->notFound();
        }

        try {
            // Raw body: the global middleware would turn "" into null and trim text.
            $raw = $request->getContent();
            if (strlen($raw) > 65536) {
                throw new CheckoutException('too_large', 'The request is too large.', 413);
            }
            $body = json_decode($raw, true, 32);

            $config = CheckoutConfig::fromDocument($funnel->publishedVersion->content);
            $allowed = array_column($this->payments->forPage($config), 'key');
            $input = CheckoutInput::fromBody($body, $config, $allowed);
            $input->userAgent = $request->userAgent();

            $result = $this->orders->place($funnel, $config, $input, (string) $request->ip());
        } catch (CheckoutException $e) {
            return $this->refuse($e);
        }

        return response()->json(['result' => true, 'replayed' => $result['replayed'], 'order' => $result['order']], $result['replayed'] ? 200 : 201)
            ->header('Cache-Control', 'no-store');
    }

    public function order($slug, $receipt)
    {
        [$funnel, $combined] = $this->find((string) $slug, (string) $receipt);
        if (!$funnel) {
            return $this->notFound();
        }

        return response()->json(['result' => true, 'order' => $this->orders->summary($combined, $funnel)])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Starts the hosted payment for an order that is still unpaid and not cancelled, through the
     * payment integration the website already uses. Asking again after a failed attempt is safe:
     * a paid or cancelled order never gets a payment link.
     */
    public function pay($slug, $receipt)
    {
        [$funnel, $combined] = $this->find((string) $slug, (string) $receipt);
        if (!$funnel) {
            return $this->notFound();
        }

        $orders = Order::where('combined_order_id', $combined->id)->get();
        $first = $orders->first();

        if (!$first || !$this->payments->isOnline((string) $first->payment_type)) {
            return $this->refuse(new CheckoutException('not_online', 'This order is not paid online.', 409));
        }
        if ($orders->contains(fn ($order) => $order->payment_status === 'paid')) {
            return $this->refuse(new CheckoutException('already_paid', 'This order is already paid.', 409));
        }
        if ($orders->contains(fn ($order) => $order->delivery_status === 'cancelled')) {
            return $this->refuse(new CheckoutException('order_cancelled', 'This order was cancelled. Please place it again.', 409));
        }

        $begin = Request::create('/', 'POST', ['payment_type' => 'cart_payment', 'combined_order_id' => Crypt::encrypt($combined->id)]);

        return app(PaystationController::class)->begin($begin)->header('Cache-Control', 'no-store');
    }

    /** @return array{0: ?Funnel, 1: ?CombinedOrder} */
    private function find(string $slug, string $receipt): array
    {
        $funnel = $this->published->liveFunnel($slug);
        $data = $funnel ? Receipt::read($receipt, $funnel->id) : null;
        if (!$data) {
            return [null, null];
        }

        $combined = CombinedOrder::find($data['combined_order_id']);
        // The receipt must belong to an order of this funnel.
        if (!$combined || !Order::where('combined_order_id', $combined->id)->where('funnel_id', $funnel->id)->exists()) {
            return [null, null];
        }

        return [$funnel, $combined];
    }

    private function refuse(CheckoutException $e)
    {
        $response = response()->json($e->toArray(), $e->status())->header('Cache-Control', 'no-store');
        $retry = $e->context()['retry_after'] ?? ($e->errorCode() === 'inventory_busy' ? 3 : null);
        if ($retry) {
            $response->header('Retry-After', (string) $retry);
        }

        return $response;
    }

    private function notFound()
    {
        return response()->json(['result' => false, 'code' => 'not_found', 'message' => 'This page is not available.'], 404);
    }
}
