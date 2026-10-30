<?php

namespace App\Services\Funnel\Offers;

use App\Services\Funnel\Checkout\CheckoutAudit;
use App\Services\Funnel\ValidationResult;

/**
 * Publish-time check of a page's offers against the live catalogue.
 *
 * The page validator only knows shapes; this knows the shop. Problems that
 * make an offer impossible to honour (a deleted product, a variation that
 * does not exist, a variation left open inside a bundle, a discount that
 * makes a product free) block publishing. Conditions that can change on
 * their own (out of stock, unpublished) are warnings: the live page
 * disables the offer and re-enables it when stock returns.
 */
class OfferAudit
{
    private $engine;
    private $checkout;

    public function __construct(OfferEngine $engine, CheckoutAudit $checkout)
    {
        $this->engine = $engine;
        $this->checkout = $checkout;
    }

    public function audit(ValidationResult $result): void
    {
        // Ordering and stock-record warnings for the same page.
        $this->checkout->audit($result);

        if ($result->bindings === []) {
            return;
        }

        $bindings = array_column($result->bindings, 'binding');
        $resolved = $this->engine->resolve($bindings);
        // The engine's reader now holds the products it just read.
        $catalogue = $this->engine->catalogue();

        foreach ($result->bindings as $entry) {
            $binding = $entry['binding'];
            $view = $resolved[$entry['key']];
            $bundle = count($binding['items']) > 1;

            foreach ($binding['items'] as $i => $item) {
                $path = "{$entry['path']}.items[{$i}]";
                $product = $catalogue->product($item['productId']);

                if ($product === null) {
                    $result->error("{$path}.productId", 'product_missing', 'A chosen product no longer exists in the catalogue. Remove it or choose another.');
                    continue;
                }
                if ((int) $product->digital === 1 || (int) $product->auction_product === 1 || (int) $product->wholesale_product === 1) {
                    $result->error("{$path}.productId", 'product_unsupported', "{$product->name} cannot be sold through a funnel (digital, auction and wholesale products are not supported).");
                    continue;
                }
                if ($item['variant'] !== null && $catalogue->stock($product, $item['variant']) === null) {
                    $result->error("{$path}.variant", 'invalid_variant', "{$product->name} has no variation called \"{$item['variant']}\".");
                    continue;
                }
                if ($bundle && $catalogue->hasVariants($product) && $item['variant'] === null) {
                    $result->error("{$path}.variant", 'variant_required', "Choose the variation of {$product->name} for this bundle.");
                }

                $problem = $view['items'][$i]['problem'] ?? null;
                if ($problem === 'product_unpublished') {
                    $result->warning($path, 'product_unpublished', "{$product->name} is not published on the website, so customers cannot order it until it is.");
                } elseif ($problem === 'out_of_stock') {
                    $result->warning($path, 'out_of_stock', "{$product->name} is out of stock. The offer will be disabled until it is restocked.");
                }
            }

            if (($binding['offer']['type'] ?? 'none') === 'free_gift' && isset($binding['offer']['giftProductId'])) {
                $gift = $view['gift'] ?? null;
                if ($gift && $gift['name'] === null) {
                    $result->error("{$entry['path']}.offer.giftProductId", 'product_missing', 'The free gift product no longer exists in the catalogue.');
                } elseif ($gift && $gift['problem']) {
                    $result->warning("{$entry['path']}.offer.giftProductId", 'gift_unavailable', 'The free gift cannot be sent right now, so the offer will be paused.');
                }
            }

            if ($view['unavailable_reason'] === 'invalid_offer_price') {
                $result->error("{$entry['path']}.offer", 'invalid_offer_price', 'This discount is as large as the price, which would make the product free. Lower the discount.');
            }
            foreach ($view['notes'] as $note) {
                $result->warning("{$entry['path']}.offer", $note['code'], $note['message']);
            }
        }
    }
}
