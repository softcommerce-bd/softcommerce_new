<?php

namespace App\Services\Funnel\Offers;

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Upload;

/**
 * Read-only access to the existing catalogue for offers: one query for the
 * products (with stock rows and taxes), one for their images. The funnel
 * never stores or caches any of this; every call reads the live values.
 */
class CatalogueReader
{
    /** @var array<int, Product> */
    private $products = [];

    /** @var array<int, string> */
    private $imageUrls = [];

    /**
     * Forgets everything read so far. The engine calls this at the start of
     * every public operation, so a long-lived instance (a service held by a
     * queue worker or a test) never prices from a stale read.
     */
    public function flush(): void
    {
        $this->products = [];
        $this->imageUrls = [];
    }

    /**
     * Loads products not seen yet.
     *
     * @param int[] $ids
     */
    public function load(array $ids): void
    {
        $missing = array_values(array_diff(array_unique(array_map('intval', $ids)), array_keys($this->products)));
        if ($missing === []) {
            return;
        }

        $found = Product::with(['stocks', 'taxes'])->whereIn('id', $missing)->get();
        $uploadIds = [];
        foreach ($found as $product) {
            $this->products[$product->id] = $product;
            foreach ($this->imageIds($product) as $uploadId) {
                $uploadIds[$uploadId] = true;
            }
        }

        // Ids that do not exist stay absent; callers report them.
        $uploadIds = array_diff(array_keys($uploadIds), array_keys($this->imageUrls));
        if ($uploadIds !== []) {
            foreach (Upload::whereIn('id', $uploadIds)->get(['id', 'file_name', 'external_link']) as $upload) {
                $this->imageUrls[$upload->id] = $upload->external_link ?: my_asset($upload->file_name);
            }
        }
    }

    public function product(int $id): ?Product
    {
        return $this->products[$id] ?? null;
    }

    /** Variant rows of a product; a product without variants has one row. */
    public function variants(Product $product)
    {
        return $product->stocks->values();
    }

    public function hasVariants(Product $product): bool
    {
        return (int) $product->variant_product === 1 && $product->stocks->count() > 0
            && $product->stocks->contains(function ($stock) {
                return (string) $stock->variant !== '';
            });
    }

    public function stock(Product $product, ?string $variant): ?ProductStock
    {
        $wanted = (string) $variant;

        return $product->stocks->first(function ($stock) use ($wanted) {
            return (string) $stock->variant === $wanted;
        });
    }

    /** First variant that can be bought, else the first one: what a card shows by default. */
    public function defaultVariant(Product $product): string
    {
        $rows = $this->variants($product);
        $inStock = $rows->first(function ($stock) {
            return (int) $stock->qty > 0;
        });

        return (string) ($inStock ?: $rows->first())->variant;
    }

    /**
     * Why a product cannot be ordered right now, or null when it can.
     */
    public function problem(?Product $product, ?ProductStock $stock): ?string
    {
        if ($product === null) {
            return 'product_missing';
        }
        if ((int) $product->published !== 1 || (int) $product->approved !== 1) {
            return 'product_unpublished';
        }
        if ((int) $product->digital === 1 || (int) $product->auction_product === 1 || (int) $product->wholesale_product === 1) {
            return 'product_unsupported';
        }
        if ($stock === null) {
            return 'invalid_variant';
        }
        if ((int) $stock->qty <= 0) {
            return 'out_of_stock';
        }

        return null;
    }

    /** The list price: the stock row's own price, before any discount. */
    public function regularUnit(ProductStock $stock): int
    {
        return Money::cents($stock->price);
    }

    /**
     * What the website charges for one piece today: list price less the
     * product's own discount (when its date window is open), before tax. This
     * is the storefront's own function, so funnels and the shop agree.
     */
    public function currentUnit(Product $product, ProductStock $stock): int
    {
        return Money::cents(cart_product_price(['variation' => (string) $stock->variant, 'quantity' => 1], $product, false, false));
    }

    /**
     * Product tax on a line, on the price actually charged. Percent taxes
     * follow the line total; fixed taxes are per piece.
     */
    public function tax(Product $product, int $lineTotal, int $pieces): int
    {
        $tax = 0;
        foreach ($product->taxes as $rule) {
            $tax += $rule->tax_type === 'percent'
                ? Money::percentOf($lineTotal, (float) $rule->tax)
                : Money::cents($rule->tax) * $pieces;
        }

        return $tax;
    }

    public function thumbnail(Product $product): ?string
    {
        return $this->imageUrls[(int) $product->thumbnail_img] ?? null;
    }

    /** Gallery images, in the order the admin set them, thumbnail first when there are none. */
    public function gallery(Product $product): array
    {
        $urls = [];
        foreach ($this->imageIds($product, false) as $id) {
            if (isset($this->imageUrls[$id])) {
                $urls[] = $this->imageUrls[$id];
            }
        }
        if ($urls === [] && ($thumb = $this->thumbnail($product))) {
            $urls[] = $thumb;
        }

        return array_values(array_unique($urls));
    }

    private function imageIds(Product $product, bool $withThumbnail = true): array
    {
        $ids = [];
        foreach (explode(',', (string) $product->photos) as $id) {
            if (ctype_digit(trim($id))) {
                $ids[] = (int) trim($id);
            }
        }
        if ($withThumbnail && ctype_digit((string) $product->thumbnail_img)) {
            $ids[] = (int) $product->thumbnail_img;
        }

        return $ids;
    }
}
