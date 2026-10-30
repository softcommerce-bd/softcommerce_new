<?php

namespace App\Services\Funnel\Offers;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Resolves funnel offers against the live catalogue and prices selections.
 *
 * Two entry points share one calculation, so a price shown on a card and the
 * price quoted for the same selection cannot differ:
 *
 *   resolve()  what a page needs to draw its cards: live names, images,
 *              prices, stock and each offer's status. For display only.
 *   quote()    the authoritative price of a customer's selection. The only
 *              inputs it reads from the customer are offer key, variation
 *              and quantity; amounts, discounts and prices in a request are
 *              never read.
 *
 * What lives in the funnel (rules, immutable per published version): offer
 * keys, which products and variations, how many pieces make an offer unit,
 * the discount rule, dates, limits, free-delivery flag and display text.
 * What is read live on every call: names, images, list and current prices,
 * product taxes, stock and whether a product can be sold at all. A website
 * price change therefore changes the payable amount through the same rule;
 * it never changes the rule.
 */
class OfferEngine
{
    private $catalogue;

    public function __construct(CatalogueReader $catalogue)
    {
        $this->catalogue = $catalogue;
    }

    /** The reader the last call loaded into; used by the publish audit. */
    public function catalogue(): CatalogueReader
    {
        return $this->catalogue;
    }

    // ── Display ──────────────────────────────────────────────────────────

    /**
     * @param array $bindings clean bindings (see PageValidator)
     * @return array<string, array> resolved offers by key
     */
    public function resolve(array $bindings, ?CarbonInterface $now = null): array
    {
        $now = $now ?: Carbon::now();
        $this->catalogue->flush();
        $this->catalogue->load($this->productIds($bindings));

        $out = [];
        foreach ($bindings as $binding) {
            $out[$binding['key']] = $this->resolveOne($binding, $now);
        }

        return $out;
    }

    private function resolveOne(array $binding, CarbonInterface $now): array
    {
        $offer = $binding['offer'] ?? ['type' => 'none'];
        $items = [];
        $notes = [];
        $problem = null;

        foreach ($binding['items'] as $item) {
            $product = $this->catalogue->product($item['productId']);
            $variant = $this->displayVariant($product, $item);
            $stock = $product ? $this->catalogue->stock($product, $variant) : null;
            $itemProblem = $this->catalogue->problem($product, $stock);
            $problem = $problem ?: $itemProblem;

            $items[] = $this->describeItem($item, $product, $variant, $stock, $itemProblem);
        }

        $status = $this->status($offer, $now);
        $reason = $problem ?: ($status === 'active' ? null : 'offer_' . ($status === 'scheduled' ? 'not_started' : 'expired'));

        // Gifts must be available too, or the page would promise what it cannot send.
        $gift = null;
        if (($offer['type'] ?? '') === 'free_gift' && isset($offer['giftProductId'])) {
            $gift = $this->describeGift($offer);
            $reason = $reason ?: ($gift['problem'] ? 'gift_unavailable' : null);
        }

        $price = null;
        $variantPrices = [];
        if ($reason === null || $reason === 'out_of_stock' || $reason === 'offer_expired' || $reason === 'offer_not_started' || $reason === 'gift_unavailable') {
            $math = $this->mathFor($binding, $items, 1);
            if ($math !== null) {
                $price = $this->priceBlock($math);
                if ($math['error']) {
                    $reason = $reason ?: $math['error'];
                } elseif (!$math['beneficial'] && ($offer['type'] ?? 'none') !== 'none' && !in_array($offer['type'], ['quantity_discount', 'bxgy', 'free_gift'], true)) {
                    $notes[] = ['code' => 'offer_not_beneficial', 'message' => 'The offer price is not lower than the current website price, so customers get no discount.'];
                }
            }
            if (count($binding['items']) === 1 && count($items) === 1 && !$items[0]['lock_variant'] && count($items[0]['variants']) > 1) {
                foreach ($items[0]['variants'] as $row) {
                    $variantItems = [['current' => $row['current_cents'], 'quantity' => $items[0]['quantity']]];
                    $variantMath = OfferMath::price($variantItems, $offer, 1);
                    $variantPrices[$row['variant']] = $this->priceBlock($variantMath) + ['available' => $row['quantity'] > 0, 'quantity' => $row['quantity']];
                }
            }
        }

        $display = $binding['display'] ?? [];

        return [
            'key' => $binding['key'],
            'type' => $offer['type'] ?? 'none',
            'status' => $status,
            'starts_at' => $offer['startsAt'] ?? null,
            'ends_at' => $offer['endsAt'] ?? null,
            'free_delivery' => (bool) ($offer['freeDelivery'] ?? false),
            'max_per_order' => $offer['maxPerOrder'] ?? null,
            'tiers' => $offer['tiers'] ?? [],
            'buy_qty' => $offer['buyQty'] ?? null,
            'get_qty' => $offer['getQty'] ?? null,
            'gift' => $gift,
            'available' => $reason === null,
            'unavailable_reason' => $reason,
            'unavailable_message' => $reason ? $this->message($reason) : null,
            'items' => $items,
            'title' => $display['title'] ?? $this->defaultTitle($items),
            'price' => $price,
            'variant_prices' => (object) $variantPrices,
            'notes' => $notes,
        ];
    }

    private function describeItem(array $item, $product, ?string $variant, $stock, ?string $problem): array
    {
        $variants = [];
        if ($product) {
            foreach ($this->catalogue->variants($product) as $row) {
                $current = $this->catalogue->currentUnit($product, $row);
                $variants[] = [
                    'variant' => (string) $row->variant,
                    'sku' => $row->sku,
                    'quantity' => max(0, (int) $row->qty),
                    'regular_cents' => $this->catalogue->regularUnit($row),
                    'current_cents' => $current,
                ];
            }
        }

        $current = $stock ? $this->catalogue->currentUnit($product, $stock) : 0;

        return [
            'product_id' => $item['productId'],
            'name' => $product ? $product->name : null,
            'slug' => $product ? $product->slug : null,
            'image' => $product ? $this->catalogue->thumbnail($product) : null,
            'images' => $product ? $this->catalogue->gallery($product) : [],
            'quantity' => $item['quantity'],
            'variant' => $variant,
            'lock_variant' => (bool) ($item['lockVariant'] ?? false),
            'has_variants' => $product ? $this->catalogue->hasVariants($product) : false,
            'stock_quantity' => $stock ? max(0, (int) $stock->qty) : 0,
            // The catalogue id the Meta feed and the storefront Pixel use for this item.
            'stock_id' => $stock ? (int) $stock->id : null,
            'regular' => $stock ? Money::amount($this->catalogue->regularUnit($stock)) : null,
            'current' => $stock ? Money::amount($current) : null,
            'current_cents' => $current,
            'regular_cents' => $stock ? $this->catalogue->regularUnit($stock) : 0,
            'problem' => $problem,
            'variants' => array_map(function ($row) {
                return [
                    'variant' => $row['variant'],
                    'sku' => $row['sku'],
                    'quantity' => $row['quantity'],
                    'current' => Money::amount($row['current_cents']),
                    'regular' => Money::amount($row['regular_cents']),
                    'current_cents' => $row['current_cents'],
                ];
            }, $variants),
        ];
    }

    private function describeGift(array $offer): array
    {
        $product = $this->catalogue->product((int) $offer['giftProductId']);
        $variant = $product ? ($offer['giftVariant'] ?? ($this->catalogue->hasVariants($product) ? $this->catalogue->defaultVariant($product) : '')) : null;
        $stock = $product ? $this->catalogue->stock($product, $variant) : null;

        return [
            'product_id' => (int) $offer['giftProductId'],
            'name' => $product ? $product->name : null,
            'image' => $product ? $this->catalogue->thumbnail($product) : null,
            'variant' => $variant,
            'quantity' => (int) ($offer['giftQuantity'] ?? 1),
            'problem' => $this->catalogue->problem($product, $stock),
        ];
    }

    private function mathFor(array $binding, array $items, int $n): ?array
    {
        $priced = [];
        foreach ($items as $item) {
            if ($item['regular'] === null) {
                return null;
            }
            $priced[] = ['current' => $item['current_cents'], 'quantity' => $item['quantity']];
        }

        return $priced === [] ? null : OfferMath::price($priced, $binding['offer'] ?? ['type' => 'none'], $n);
    }

    private function priceBlock(array $math): array
    {
        $savings = max(0, $math['discount']);

        return [
            'regular' => $this->money($math['unit_base']),
            'offer' => $this->money($math['unit_price']),
            'savings' => $this->money($savings),
            'savings_percent' => $math['total_base'] > 0 ? round($savings * 100 / $math['total_base'], 1) : 0,
            'has_discount' => $savings > 0,
        ];
    }

    private function displayVariant($product, array $item): ?string
    {
        if (!$product) {
            return null;
        }
        if ($item['variant'] !== null && $this->catalogue->stock($product, $item['variant'])) {
            return $item['variant'];
        }

        return $this->catalogue->hasVariants($product) ? $this->catalogue->defaultVariant($product) : '';
    }

    private function defaultTitle(array $items): string
    {
        $names = array_filter(array_map(function ($item) {
            return $item['name'];
        }, $items));

        return implode(' + ', $names);
    }

    // ── Pricing ──────────────────────────────────────────────────────────

    /**
     * Prices a selection from the customer.
     *
     * @param array $bindings   clean bindings of the published (or previewed) page
     * @param mixed $selections  untrusted input; a list of ['key' => string, 'variant' => ?string, 'quantity' => int]
     * @return array valid, errors, lines, totals, fingerprint
     */
    public function quote(array $bindings, $selections, ?CarbonInterface $now = null): array
    {
        $now = $now ?: Carbon::now();
        $this->catalogue->flush();
        $errors = [];
        $byKey = [];
        foreach ($bindings as $binding) {
            $byKey[$binding['key']] = $binding;
        }

        $selections = $this->cleanSelections($selections, $errors);
        $ids = [];
        foreach ($selections as $selection) {
            if (isset($byKey[$selection['key']])) {
                $ids = array_merge($ids, $this->productIds([$byKey[$selection['key']]]));
            }
        }
        $this->catalogue->load($ids);

        $lines = [];
        $needs = [];
        $names = [];
        foreach ($selections as $selection) {
            $binding = $byKey[$selection['key']] ?? null;
            if ($binding === null) {
                // An offer that is not on the published page is not an offer.
                $errors[] = $this->error('unknown_offer', $selection['key'], 'This offer is not available.');
                continue;
            }
            $line = $this->quoteLine($binding, $selection, $now, $errors, $needs, $names);
            if ($line !== null) {
                $lines[] = $line;
            }
        }

        // Several offers can draw on the same stock; check the sum.
        foreach ($needs as $id => $need) {
            [$productId, $variant] = explode('|', $id, 2);
            $stock = $this->catalogue->stock($this->catalogue->product((int) $productId), $variant);
            $have = $stock ? max(0, (int) $stock->qty) : 0;
            if ($need['quantity'] > $have) {
                $name = $names[$id] ?? 'This product';
                $errors[] = $this->error('out_of_stock', $need['keys'][0], $have > 0
                    ? "Only {$have} of {$name} left."
                    : "{$name} is out of stock.", ['available' => $have, 'product' => $name]);
            }
        }

        if ($errors !== [] || $lines === []) {
            if ($lines === [] && $errors === []) {
                $errors[] = $this->error('empty_selection', null, 'Choose at least one product.');
            }

            return ['valid' => false, 'errors' => $errors, 'lines' => [], 'totals' => null, 'fingerprint' => null];
        }

        $totals = $this->totals($lines);

        return [
            'valid' => true,
            'errors' => [],
            'lines' => array_map([$this, 'presentLine'], $lines),
            'totals' => $this->presentTotals($totals),
            'fingerprint' => $this->fingerprint($lines, $totals),
            // Minor-unit figures for server callers (checkout), never sent to browsers.
            '_cents' => ['lines' => $lines, 'totals' => $totals],
        ];
    }

    private function cleanSelections($selections, array &$errors): array
    {
        $max = (int) config('funnel.pricing.max_lines');
        if (!is_array($selections) || !$this->isList($selections)) {
            $errors[] = $this->error('invalid_selection', null, 'The selection is not valid.');

            return [];
        }
        if (count($selections) > $max) {
            $errors[] = $this->error('too_many_lines', null, "At most {$max} offers can be ordered at once.");

            return [];
        }

        $clean = [];
        $seen = [];
        foreach ($selections as $selection) {
            $key = is_array($selection) ? ($selection['key'] ?? null) : null;
            $quantity = is_array($selection) ? ($selection['quantity'] ?? null) : null;
            if (is_string($quantity) && ctype_digit($quantity)) {
                $quantity = (int) $quantity;
            }
            if (!is_string($key) || $key === '' || !is_int($quantity) || $quantity < 1) {
                $errors[] = $this->error('invalid_selection', is_string($key) ? $key : null, 'Choose a quantity of at least 1.');
                continue;
            }
            if (isset($seen[$key])) {
                $errors[] = $this->error('duplicate_selection', $key, 'The same offer was chosen twice.');
                continue;
            }
            $seen[$key] = true;
            $variant = is_array($selection) && isset($selection['variant']) && is_string($selection['variant']) ? $selection['variant'] : null;
            $clean[] = ['key' => $key, 'variant' => $variant, 'quantity' => $quantity];
        }

        return $clean;
    }

    private function quoteLine(array $binding, array $selection, CarbonInterface $now, array &$errors, array &$needs, array &$names): ?array
    {
        $key = $binding['key'];
        $offer = $binding['offer'] ?? ['type' => 'none'];
        $n = $selection['quantity'];
        $before = count($errors);

        $status = $this->status($offer, $now);
        if ($status !== 'active') {
            $errors[] = $this->error('offer_' . ($status === 'scheduled' ? 'not_started' : 'expired'), $key, $this->message('offer_' . ($status === 'scheduled' ? 'not_started' : 'expired')));

            return null;
        }

        $cap = min((int) config('funnel.pricing.max_quantity'), (int) ($offer['maxPerOrder'] ?? PHP_INT_MAX));
        if ($n > $cap) {
            $errors[] = $this->error('limit_exceeded', $key, "You can order at most {$cap} of this offer.", ['max' => $cap]);

            return null;
        }

        $single = count($binding['items']) === 1;
        $resolved = [];
        foreach ($binding['items'] as $item) {
            $product = $this->catalogue->product($item['productId']);
            if ($product === null) {
                $errors[] = $this->error('product_missing', $key, $this->message('product_missing'));
                continue;
            }

            $variant = $item['variant'];
            if ($single && !($item['lockVariant'] ?? false) && $selection['variant'] !== null) {
                $variant = $selection['variant'];
            }
            if ($variant === null) {
                if ($this->catalogue->hasVariants($product)) {
                    $errors[] = $this->error('variant_required', $key, 'Choose a variation.');
                    continue;
                }
                $variant = '';
            }

            $stock = $this->catalogue->stock($product, $variant);
            $problem = $this->catalogue->problem($product, $stock);
            if ($problem !== null) {
                $errors[] = $this->error($problem, $key, $this->message($problem, $product->name), ['product' => $product->name]);
                continue;
            }

            $pieces = $item['quantity'] * $n;
            $this->need($needs, $names, $product->id, $variant, $pieces, $key, $product->name);
            $resolved[] = ['product' => $product, 'stock' => $stock, 'variant' => $variant, 'per_unit' => $item['quantity'], 'pieces' => $pieces];
        }

        $gift = null;
        if (($offer['type'] ?? '') === 'free_gift') {
            $gift = $this->giftLine($offer, $n, $key, $errors, $needs, $names);
        }

        if (count($errors) > $before || $resolved === []) {
            return null;
        }

        $math = OfferMath::price(array_map(function ($row) {
            return ['current' => $this->catalogue->currentUnit($row['product'], $row['stock']), 'quantity' => $row['per_unit']];
        }, $resolved), $offer, $n);

        if ($math['error']) {
            $errors[] = $this->error($math['error'], $key, $this->message($math['error']));

            return null;
        }

        $items = [];
        $tax = 0;
        $regularTotal = 0;
        $currentTotal = 0;
        foreach ($resolved as $i => $row) {
            $current = $this->catalogue->currentUnit($row['product'], $row['stock']);
            $regular = $this->catalogue->regularUnit($row['stock']);
            $lineTotal = $math['allocation'][$i];
            $lineTax = $this->catalogue->tax($row['product'], $lineTotal, $row['pieces']);
            $tax += $lineTax;
            $regularTotal += $regular * $row['pieces'];
            $currentTotal += $current * $row['pieces'];
            $items[] = [
                'product_id' => $row['product']->id,
                'name' => $row['product']->name,
                'variant' => $row['variant'],
                'sku' => $row['stock']->sku,
                'pieces' => $row['pieces'],
                'unit_regular' => $regular,
                'unit_current' => $current,
                'total_current' => $current * $row['pieces'],
                'total' => $lineTotal,
                'tax' => $lineTax,
            ];
        }

        return [
            'key' => $key,
            'quantity' => $n,
            'type' => $offer['type'] ?? 'none',
            'items' => $items,
            'gift' => $gift,
            'regular_total' => $regularTotal,
            'current_total' => $currentTotal,
            'offer_total' => $math['total'],
            'discount' => $currentTotal - $math['total'],
            'tax' => $tax,
            'free_units' => $math['free_units'],
            'tier' => $math['tier'],
            'free_delivery' => (bool) ($offer['freeDelivery'] ?? false),
        ];
    }

    private function giftLine(array $offer, int $n, string $key, array &$errors, array &$needs, array &$names): ?array
    {
        $product = $this->catalogue->product((int) ($offer['giftProductId'] ?? 0));
        $variant = $product ? ($offer['giftVariant'] ?? ($this->catalogue->hasVariants($product) ? $this->catalogue->defaultVariant($product) : '')) : null;
        $stock = $product ? $this->catalogue->stock($product, $variant) : null;
        if ($this->catalogue->problem($product, $stock) !== null) {
            $errors[] = $this->error('gift_unavailable', $key, $this->message('gift_unavailable'));

            return null;
        }
        $pieces = (int) ($offer['giftQuantity'] ?? 1) * $n;
        $this->need($needs, $names, $product->id, $variant, $pieces, $key, $product->name);

        return ['product_id' => $product->id, 'name' => $product->name, 'variant' => $variant, 'sku' => $stock->sku, 'pieces' => $pieces];
    }

    private function need(array &$needs, array &$names, int $productId, string $variant, int $pieces, string $key, string $name): void
    {
        $id = $productId . '|' . $variant;
        $needs[$id] = ['quantity' => ($needs[$id]['quantity'] ?? 0) + $pieces, 'keys' => array_merge($needs[$id]['keys'] ?? [], [$key])];
        $names[$id] = $name . ($variant !== '' ? " ({$variant})" : '');
    }

    private function totals(array $lines): array
    {
        $sum = function (string $field) use ($lines) {
            return array_sum(array_column($lines, $field));
        };

        return [
            'regular_subtotal' => $sum('regular_total'),
            'website_subtotal' => $sum('current_total'),
            'funnel_discount' => $sum('discount'),
            'offer_subtotal' => $sum('offer_total'),
            'tax' => $sum('tax'),
            'total_before_delivery' => $sum('offer_total') + $sum('tax'),
            'free_delivery' => in_array(true, array_column($lines, 'free_delivery'), true),
        ];
    }

    private function presentTotals(array $totals): array
    {
        return [
            // List prices, before any discount.
            'regular_subtotal' => $this->money($totals['regular_subtotal']),
            // What the website itself would charge today.
            'website_subtotal' => $this->money($totals['website_subtotal']),
            // The funnel offer's own discount, on top of the website price.
            'funnel_discount' => $this->money($totals['funnel_discount']),
            // Products after the funnel discount, before tax.
            'product_subtotal' => $this->money($totals['offer_subtotal']),
            'tax' => $this->money($totals['tax']),
            // Delivery is priced at checkout by the store's own shipping rules.
            'delivery' => ['status' => 'calculated_at_checkout', 'free' => $totals['free_delivery'], 'amount' => null],
            'total_before_delivery' => $this->money($totals['total_before_delivery']),
            'total_savings' => $this->money(max(0, $totals['regular_subtotal'] - $totals['offer_subtotal'])),
        ];
    }

    private function presentLine(array $line): array
    {
        return [
            'key' => $line['key'],
            'quantity' => $line['quantity'],
            'type' => $line['type'],
            'items' => array_map(function ($item) {
                return [
                    'product_id' => $item['product_id'],
                    'name' => $item['name'],
                    'variant' => $item['variant'],
                    'pieces' => $item['pieces'],
                    'unit_regular' => $this->money($item['unit_regular']),
                    'unit_website' => $this->money($item['unit_current']),
                    'total' => $this->money($item['total']),
                    'tax' => $this->money($item['tax']),
                ];
            }, $line['items']),
            'gift' => $line['gift'],
            'regular_total' => $this->money($line['regular_total']),
            'website_total' => $this->money($line['current_total']),
            'discount' => $this->money($line['discount']),
            'total' => $this->money($line['offer_total']),
            'tax' => $this->money($line['tax']),
            'free_units' => $line['free_units'],
            'tier' => $line['tier'],
            'free_delivery' => $line['free_delivery'],
        ];
    }

    /**
     * A hash of what was priced. Checkout will send back the one it showed
     * the customer; a mismatch means a price moved in between and the
     * customer must confirm the new total.
     */
    private function fingerprint(array $lines, array $totals): string
    {
        $core = array_map(function ($line) {
            return [$line['key'], $line['quantity'], $line['offer_total'], $line['tax'], array_map(function ($i) {
                return [$i['product_id'], $i['variant'], $i['pieces'], $i['total']];
            }, $line['items'])];
        }, $lines);

        return sha1(json_encode([$core, $totals]));
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function status(array $offer, CarbonInterface $now): string
    {
        if (!empty($offer['startsAt']) && $now->lt(Carbon::parse($offer['startsAt']))) {
            return 'scheduled';
        }
        if (!empty($offer['endsAt']) && $now->gt(Carbon::parse($offer['endsAt']))) {
            return 'expired';
        }

        return 'active';
    }

    private function productIds(array $bindings): array
    {
        $ids = [];
        foreach ($bindings as $binding) {
            foreach ($binding['items'] as $item) {
                $ids[] = $item['productId'];
            }
            if (!empty($binding['offer']['giftProductId'])) {
                $ids[] = (int) $binding['offer']['giftProductId'];
            }
        }

        return $ids;
    }

    private function money(int $cents): array
    {
        return ['amount' => Money::amount($cents), 'text' => Money::format($cents)];
    }

    private function error(string $code, ?string $key, string $message, array $extra = []): array
    {
        return ['code' => $code, 'key' => $key, 'message' => $message] + $extra;
    }

    private function isList(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1) || $value === [];
    }

    public function message(string $code, ?string $name = null): string
    {
        $subject = $name ?: 'This product';

        return [
            'product_missing' => 'This product is no longer in the catalogue.',
            'product_unpublished' => "{$subject} is not on sale right now.",
            'product_unsupported' => "{$subject} cannot be sold through a funnel.",
            'invalid_variant' => 'That variation does not exist.',
            'out_of_stock' => "{$subject} is out of stock.",
            'offer_expired' => 'This offer has ended.',
            'offer_not_started' => 'This offer has not started yet.',
            'gift_unavailable' => 'The free gift is out of stock, so this offer is paused.',
            'invalid_offer_price' => 'This offer\'s discount is larger than the price, so it is paused.',
        ][$code] ?? 'This offer is not available.';
    }
}
