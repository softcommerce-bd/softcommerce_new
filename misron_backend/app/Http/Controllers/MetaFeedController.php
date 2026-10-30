<?php

namespace App\Http\Controllers;

use App\Models\Product;

/**
 * Product feed for Meta Commerce Manager.
 *
 * Meta fetches this URL on a schedule and rebuilds the catalogue from it, so
 * the feed is generated from the database on every request rather than kept as
 * a file — adding a product, editing its price or selling out its last unit is
 * reflected on the next fetch with no manual step.
 *
 * One <item> is emitted per buyable combination, not per product. A shopper
 * buys a specific size, so a catalogue keyed on the product would let Meta
 * advertise a size that is sold out. Rows are grouped with <g:item_group_id>
 * so Meta still understands the sizes as one garment.
 *
 * The id published here is the product_stocks row id, and the storefront sends
 * that same id as the Pixel's content_ids. The SKU column was unusable for this:
 * it is null on most rows, and where it is set it holds a bare size ("XL") that
 * repeats across unrelated garments, so it would merge six products into one
 * catalogue item. Dynamic ads only retarget when feed and Pixel ids match
 * exactly, so neither side may change its id format alone.
 */
class MetaFeedController extends Controller
{
    public function index()
    {
        $products = Product::with(['stocks', 'brand', 'product_translations', 'taxes'])
            ->where('published', 1)
            ->where('approved', 1)
            ->where('digital', 0)
            ->where('auction_product', 0)
            ->get();

        // Meta requires a brand on every item; products with no brand set fall
        // back to the shop's own name.
        $defaultBrand = get_setting('site_name') ?: config('app.name');

        $items = [];

        foreach ($products as $product) {
            // A product with no slug has no reachable storefront page, and Meta
            // rejects an item whose link 404s.
            if (empty($product->slug)) {
                continue;
            }

            $link = rtrim(config('app.frontend_url'), '/') . '/product/' . $product->slug;
            $title = $product->getTranslation('name');
            $description = $this->plainText($product->getTranslation('description')) ?: $title;
            $brand = $product->brand ? $product->brand->getTranslation('name') : $defaultBrand;
            $thumbnail = uploaded_asset($product->thumbnail_img);

            foreach ($product->stocks as $stock) {
                $items[] = [
                    'id' => (string) $stock->id,
                    'item_group_id' => (string) $product->id,
                    'title' => $this->variantTitle($title, $stock->variant),
                    'description' => $description,
                    'link' => $link,
                    'image_link' => $stock->image ? uploaded_asset($stock->image) : $thumbnail,
                    'price' => $this->price($product, $stock),
                    'availability' => $stock->qty > 0 ? 'in stock' : 'out of stock',
                    'condition' => 'new',
                    'brand' => $brand,
                ];
            }
        }

        return response()
            ->view('feeds.meta', ['items' => $items])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Price for one stock row, in Meta's "<amount> <ISO currency>" format.
     *
     * Variant rows carry their own price, so a larger size that costs more is
     * advertised at its real price. The product-level discount and taxes are
     * applied on top by the same helper the storefront uses, so the feed cannot
     * drift from the price a shopper is shown.
     */
    private function price($product, $stock)
    {
        $original = $product->unit_price;
        $product->unit_price = $stock->price;
        $amount = home_discounted_base_price($product, false);
        $product->unit_price = $original;

        return number_format((float) $amount, 2, '.', '') . ' ' . get_system_default_currency()->code;
    }

    /**
     * Variant rows share a product title, which Meta reports as duplicates.
     * Appending the variant keeps each row distinct and tells a shopper which
     * size the ad is for.
     */
    private function variantTitle($title, $variant)
    {
        $variant = trim((string) $variant);

        return $variant === '' ? $title : $title . ' - ' . str_replace('-', ' ', $variant);
    }

    /** Descriptions are authored as HTML; Meta accepts plain text only. */
    private function plainText($html)
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        return mb_substr($text, 0, 9999);
    }
}
