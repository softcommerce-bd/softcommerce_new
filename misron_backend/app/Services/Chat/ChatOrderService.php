<?php

namespace App\Services\Chat;

use App\Models\BlockedIp;
use App\Models\ChatConversation;
use App\Models\ChatConversationOrder;
use App\Models\CombinedOrder;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\User;
use App\Services\Funnel\Checkout\DeliveryCharge;
use App\Services\Funnel\Offers\Money;
use App\Services\Inventory\Exceptions\InventoryException;
use App\Services\Inventory\StockLedger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Creates a store order from a conversation. Not a second order engine: it writes
 * the same CombinedOrder / Order / OrderDetail rows the website and funnel
 * checkouts write, with stock and FIFO cost batches taken through StockLedger,
 * prices from the store's own price helpers (variation, dated discount, tax) and
 * the delivery charge from the store's shipping rule (DeliveryCharge).
 *
 * Payment: placed as cash on delivery / unpaid, exactly like website orders; the
 * existing fulfilment and accounting steps take it from there.
 *
 * Idempotent: the same idempotency key returns the order already created.
 */
class ChatOrderService
{
    public function __construct(private StockLedger $ledger, private DeliveryCharge $delivery)
    {
    }

    /**
     * @param array $input items[{product_id, variant, quantity}], customer{name, phone, email, address, city_id},
     *                     discount (taka), note, idempotency_key
     * @throws InvalidArgumentException with a message that is safe to show staff
     */
    public function place(ChatConversation $conversation, User $staff, array $input, string $ip): CombinedOrder
    {
        $key = (string) ($input['idempotency_key'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $key)) {
            throw new InvalidArgumentException('A valid idempotency key is required.');
        }
        if ($existing = ChatConversationOrder::where('idempotency_key', $key)->first()) {
            return CombinedOrder::findOrFail($existing->combined_order_id);
        }
        if (!$this->delivery->supported()) {
            throw new InvalidArgumentException('Carrier-wise shipping needs the customer to pick a carrier, so orders cannot be created from chat. Switch the shipping setting or create the order from the admin order screen.');
        }

        $c = $input['customer'] ?? [];
        $name = trim((string) ($c['name'] ?? ''));
        $phone = preg_replace('/[^0-9+]/', '', (string) ($c['phone'] ?? ''));
        $address = trim((string) ($c['address'] ?? ''));
        if ($name === '' || strlen($phone) < 10 || $address === '') {
            throw new InvalidArgumentException('Customer name, a valid phone number and a delivery address are required.');
        }
        $city = $this->delivery->city((int) ($c['city_id'] ?? 0));
        if (!$city) {
            throw new InvalidArgumentException('Choose a delivery district the store delivers to.');
        }

        $rows = $this->lines($input['items'] ?? []);

        $physicalIdx = array_keys(array_filter($rows, fn ($r) => !$r['digital']));
        $quote = $this->delivery->quote(array_map(fn ($i) => ['product_id' => $rows[$i]['product']->id, 'variant' => $rows[$i]['variant'], 'pieces' => $rows[$i]['quantity']], $physicalIdx), $city->id, false);
        $shippingByRow = [];
        foreach ($physicalIdx as $n => $i) {
            $shippingByRow[$i] = $quote['per_line'][$n];
        }

        $subtotal = array_sum(array_map(fn ($r) => $r['line_cents'] + $r['tax_cents'], $rows));
        $discount = Money::cents($input['discount'] ?? 0);
        if ($discount < 0 || $discount > $subtotal) {
            throw new InvalidArgumentException('The discount cannot be negative or more than the items total.');
        }
        $maxPercent = $staff->hasRole('Super Admin') ? 100 : (float) config('unified_chat.max_discount_percent');
        if ($discount > Money::percentOf($subtotal, $maxPercent)) {
            throw new InvalidArgumentException("Your role may give at most {$maxPercent}% discount from chat.");
        }

        // At most three attempts when the database kills the transaction as a deadlock victim; the idempotency key is re-checked before every attempt,
        // nothing outside the database is touched inside the transaction, and batch units a rollback cannot restore are given back first.
        $entryLevel = DB::transactionLevel();
        for ($attempt = 1; ; $attempt++) {
        $take = null;
        try {
            return DB::transaction(function () use ($conversation, $staff, $input, $key, $rows, $shippingByRow, $discount, $name, $phone, $address, $city, $c, $ip, &$take) {
                $take = $this->ledger->take(array_map(fn ($r, $i) => [
                    'key' => $i, 'product_id' => $r['product']->id, 'variant' => $r['variant'], 'quantity' => $r['quantity'], 'digital' => $r['digital'],
                ], $rows, array_keys($rows)));

                $user = $this->customer($conversation, $name, $phone, $c['email'] ?? null, $ip);
                $guestId = random_int(100000000, 999999999);
                $shipping = json_encode([
                    'name' => $name, 'email' => $c['email'] ?? '', 'phone' => $phone, 'address' => $address, 'city' => $city->name,
                    'country' => 'Bangladesh', 'postal_code' => '', 'order_note' => (string) ($input['note'] ?? ''), 'delivery_type' => 'home_delivery',
                ], JSON_UNESCAPED_UNICODE);

                $combined = new CombinedOrder();
                $combined->user_id = $user->id;
                $combined->temp_user_id = (string) $guestId;
                $combined->shipping_address = $shipping;
                $combined->save();

                $bySeller = [];
                foreach ($rows as $i => $r) {
                    $bySeller[$r['product']->user_id][] = $i;
                }
                $sellerTotals = [];
                foreach ($bySeller as $seller => $idx) {
                    $sellerTotals[$seller] = array_sum(array_map(fn ($i) => $rows[$i]['line_cents'] + $rows[$i]['tax_cents'] + ($shippingByRow[$i] ?? 0), $idx));
                }
                $discountBySeller = array_combine(array_keys($sellerTotals), Money::allocate($discount, array_values($sellerTotals)));

                $grand = 0;
                foreach ($bySeller as $seller => $idx) {
                    $order = new Order();
                    $order->combined_order_id = $combined->id;
                    $order->user_id = $user->id;
                    $order->guest_id = $guestId;
                    $order->seller_id = $seller;
                    $order->shipping_address = $shipping;
                    $order->additional_info = 'Created from ' . ucfirst($conversation->platform) . ' chat #' . $conversation->id . ' by ' . $staff->name;
                    $order->order_note = (string) ($input['note'] ?? '');
                    $order->payment_type = 'cash_on_delivery';
                    $order->shipping_type = 'home_delivery';
                    $order->pickup_point_id = 0;
                    $order->carrier_id = 0;
                    $order->order_from = 'chat';
                    $order->delivery_viewed = '0';
                    $order->payment_status_viewed = '0';
                    $order->code = Order::generateUniqueCode();
                    $order->date = strtotime('now');
                    $order->payment_status = 'unpaid';
                    $order->customer_ip = $ip;
                    $order->save();

                    $orderCents = 0;
                    foreach ($idx as $i) {
                        $r = $rows[$i];
                        $line = $take->line($i);
                        $ship = $shippingByRow[$i] ?? 0;

                        $d = new OrderDetail();
                        $d->order_id = $order->id;
                        $d->seller_id = $r['product']->user_id;
                        $d->product_id = $r['product']->id;
                        $d->variation = $r['variant'];
                        $d->selling_price = Money::amount((int) round($r['line_cents'] / $r['quantity']));
                        $d->price = Money::amount($r['line_cents']);
                        $d->buying_price = $line['avg_cost'];
                        $d->profit = Money::amount($r['line_cents']) - $line['cost'];
                        if ($line['batches']) {
                            $d->purchase_id = $line['batches'][0]['purchase_id'];
                            $d->supplier_id = $line['batches'][0]['supplier_id'];
                        }
                        $d->fifo_batch_info = $take->batchInfo($i);
                        $d->tax = Money::amount($r['tax_cents']);
                        $d->shipping_type = 'home_delivery';
                        $d->shipping_cost = Money::amount($ship);
                        $d->quantity = $r['quantity'];
                        $d->save();

                        Product::where('id', $r['product']->id)->increment('num_of_sale', $r['quantity']);
                        $orderCents += $r['line_cents'] + $r['tax_cents'] + $ship;
                    }

                    $orderDiscount = $discountBySeller[$seller];
                    $order->coupon_discount = Money::amount($orderDiscount);
                    $order->grand_total = Money::amount($orderCents - $orderDiscount);
                    $order->due_amount = $order->grand_total;
                    $order->save();
                    $grand += $orderCents - $orderDiscount;
                }

                $combined->grand_total = Money::amount($grand);
                $combined->save();

                ChatConversationOrder::create([
                    'chat_conversation_id' => $conversation->id, 'combined_order_id' => $combined->id,
                    'idempotency_key' => $key, 'created_by' => $staff->id,
                ]);

                // Same IP/customer tracking the website checkout does.
                BlockedIp::updateOrCreate(['ip_address' => $ip], ['user_id' => $user->id, 'customer_name' => $name, 'customer_phone' => $phone, 'customer_email' => $c['email'] ?? null]);

                if (!$conversation->user_id) {
                    $conversation->forceFill(['user_id' => $user->id])->save();
                }
                ChatAudit::log($staff->id, 'order.created', 'conversation', $conversation->id, ['combined_order_id' => $combined->id]);

                return $combined;
            });
        } catch (InventoryException $e) {
            $this->release($take);
            $p = Product::find($e->context()['product_id'] ?? 0);
            throw new InvalidArgumentException(($p ? $p->name . ' ' : 'An item ') . 'does not have enough stock for this order.');
        } catch (QueryException $e) {
            $this->release($take);
            // A parallel double-submit with the same key won the unique index (or finished while we waited): return its order.
            if ($existing = ChatConversationOrder::where('idempotency_key', $key)->first()) {
                return CombinedOrder::findOrFail($existing->combined_order_id);
            }
            if (\App\Support\DeadlockRetry::canRetry($attempt, $entryLevel, $e)) {
                \App\Support\DeadlockRetry::pause();
                continue;
            }
            throw $e;
        } catch (\Throwable $e) {
            $this->release($take);
            // Laravel reports a deadlock inside an already-open outer transaction as DeadlockException (not a QueryException): same rule
            if (\App\Support\DeadlockRetry::canRetry($attempt, $entryLevel, $e)) {
                \App\Support\DeadlockRetry::pause();
                continue;
            }
            throw $e;
        }
        }
    }

    private function release($take): void
    {
        if ($take !== null) {
            $this->ledger->release($take); // gives back batch units that a rollback cannot (MyISAM)
        }
    }

    /** Validated lines with store prices. */
    private function lines(array $items): array
    {
        if ($items === [] || count($items) > 50) {
            throw new InvalidArgumentException('Add between 1 and 50 products.');
        }
        $rows = [];
        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            if ($qty < 1 || $qty > 999) {
                throw new InvalidArgumentException('Quantity must be between 1 and 999.');
            }
            $product = Product::with(['stocks', 'taxes'])->find((int) ($item['product_id'] ?? 0));
            if (!$product || !$product->published || !$product->approved || $product->auction_product || $product->wholesale_product) {
                throw new InvalidArgumentException('A selected product is not available for sale.');
            }
            $variant = (string) ($item['variant'] ?? '');
            if (!$product->stocks->where('variant', $variant)->first()) {
                throw new InvalidArgumentException($product->name . ': choose a valid variation.');
            }
            $cart = ['variation' => $variant, 'quantity' => $qty];
            $unit = Money::cents(cart_product_price($cart, $product, false, false));
            $unitTax = Money::cents(cart_product_price($cart, $product, false, true)) - $unit;
            $rows[] = [
                'product' => $product, 'variant' => $variant, 'quantity' => $qty, 'digital' => (int) $product->digital === 1,
                'line_cents' => $unit * $qty, 'tax_cents' => $unitTax * $qty,
            ];
        }

        return $rows;
    }

    /** Existing customer by the linked record, then phone; otherwise a new customer with an unguessable password. */
    private function customer(ChatConversation $conversation, string $name, string $phone, ?string $email, string $ip): User
    {
        if ($conversation->user_id && ($linked = User::find($conversation->user_id))) {
            return $linked;
        }
        $local = preg_replace('/^(\+?88)/', '', $phone);
        $user = User::whereIn('phone', [$phone, $local, '+88' . $local, '88' . $local])->first();
        if (!$user && $email) {
            $user = User::where('email', $email)->first();
        }
        if ($user) {
            return $user;
        }

        return User::create(['name' => $name, 'email' => $email ?: null, 'phone' => $phone, 'password' => bcrypt(Str::random(40)), 'last_ip' => $ip]);
    }
}
