<?php

namespace App\Services\Funnel;

use App\Models\Product;
use App\Models\Upload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read-only window onto the existing product catalogue for the funnel editor's
 * product picker. Products are never copied: the editor stores product ids and
 * variant strings, and this class reports their current name, image, price
 * and stock.
 *
 * Prices here are for display while choosing. The checkout recomputes every
 * price on the server and never trusts these numbers.
 */
class FunnelCatalogue
{
    public const MAX_PER_PAGE = 50;

    /**
     * @param array $filters q, category_id, brand_id, status (published|all), in_stock, ids
     */
    public function search(array $filters, int $perPage = 20)
    {
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));

        $query = Product::query()
            ->select(['id', 'name', 'slug', 'thumbnail_img', 'category_id', 'brand_id', 'unit_price', 'discount',
                'discount_type', 'discount_start_date', 'discount_end_date', 'variant_product', 'published',
                'approved', 'current_stock', 'wholesale_product', 'auction_product', 'digital'])
            ->with(['stocks:id,product_id,variant,sku,price,qty', 'taxes', 'main_category:id,name', 'brand:id,name'])
            // Digital, auction and wholesale products use their own price and
            // delivery rules and cannot be sold through a funnel.
            ->where('auction_product', 0)
            ->where('digital', 0)
            ->where('wholesale_product', 0);

        $ids = $this->idList($filters['ids'] ?? null);
        if ($ids !== []) {
            // Explicit lookups (products already bound to a funnel) ignore the
            // status filter, so an unpublished product still shows up as such.
            $query->whereIn('id', $ids);
        } else {
            if (($filters['status'] ?? 'published') !== 'all') {
                $query->where('published', 1)->where('approved', 1);
            }
            $this->applySearch($query, (string) ($filters['q'] ?? ''));
        }

        if (!empty($filters['category_id']) && ctype_digit((string) $filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }
        if (!empty($filters['brand_id']) && ctype_digit((string) $filters['brand_id'])) {
            $query->where('brand_id', (int) $filters['brand_id']);
        }
        if (filter_var($filters['in_stock'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereHas('stocks', function ($q) {
                $q->where('qty', '>', 0);
            });
        }

        return $query->orderBy('name')->orderBy('id')->paginate($perPage);
    }

    /** @param Collection|array $products */
    public function present($products): array
    {
        $products = collect($products);
        $images = $this->imageUrls($products->pluck('thumbnail_img'));

        return $products->map(function (Product $product) use ($images) {
            return $this->presentOne($product, $images);
        })->values()->all();
    }

    private function presentOne(Product $product, array $images): array
    {
        $stocks = $product->stocks;
        $variants = $stocks->map(function ($stock) use ($product) {
            return [
                'variant' => (string) $stock->variant,
                'sku' => $stock->sku,
                'quantity' => max(0, (int) $stock->qty),
                'regular_price' => $this->withTax($product, (float) $stock->price),
                'sale_price' => (float) cart_product_price(['variation' => $stock->variant, 'quantity' => 1], $product, false, true),
            ];
        })->values();

        $quantity = (int) $variants->sum('quantity');
        $sale = $variants->pluck('sale_price');
        $regular = $variants->pluck('regular_price');

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'thumbnail_url' => $images[$product->thumbnail_img] ?? static_asset('assets/img/placeholder.jpg'),
            'sku' => optional($stocks->first(function ($stock) {
                return !empty($stock->sku);
            }))->sku,
            'category' => optional($product->main_category)->name,
            'brand' => optional($product->brand)->name,
            'published' => (int) $product->published === 1 && (int) $product->approved === 1,
            'has_variants' => (int) $product->variant_product === 1 && $variants->count() > 1,
            'price' => [
                'regular_min' => (float) $regular->min(),
                'regular_max' => (float) $regular->max(),
                'sale_min' => (float) $sale->min(),
                'sale_max' => (float) $sale->max(),
                'text' => $this->priceText((float) $sale->min(), (float) $sale->max()),
            ],
            'stock' => [
                'quantity' => $quantity,
                'in_stock' => $quantity > 0,
            ],
            'variants' => (int) $product->variant_product === 1 ? $variants->all() : [],
        ];
    }

    private function applySearch(Builder $query, string $term): void
    {
        $term = trim($term);
        if ($term === '') {
            return;
        }

        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';

        $query->where(function (Builder $q) use ($term, $like) {
            $q->where('name', 'like', $like)
                ->orWhereHas('stocks', function ($s) use ($like) {
                    $s->where('sku', 'like', $like);
                })
                ->orWhereHas('main_category', function ($c) use ($like) {
                    $c->where('name', 'like', $like);
                })
                ->orWhereHas('brand', function ($b) use ($like) {
                    $b->where('name', 'like', $like);
                });

            if (ctype_digit($term)) {
                $q->orWhere('id', (int) $term);
            }
        });
    }

    private function withTax(Product $product, float $price): float
    {
        $tax = 0;
        foreach ($product->taxes as $productTax) {
            $tax += $productTax->tax_type === 'percent' ? ($price * $productTax->tax) / 100 : $productTax->tax;
        }

        return $price + $tax;
    }

    private function priceText(float $min, float $max): string
    {
        $text = format_price(convert_price($min));

        return $min === $max ? $text : $text . ' – ' . format_price(convert_price($max));
    }

    /** One query for every thumbnail on the page instead of one per product. */
    private function imageUrls(Collection $ids): array
    {
        $ids = $ids->filter(function ($id) {
            return ctype_digit((string) $id);
        })->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Upload::whereIn('id', $ids)->get(['id', 'file_name', 'external_link'])
            ->mapWithKeys(function (Upload $upload) {
                return [$upload->id => $upload->external_link ?: my_asset($upload->file_name)];
            })
            ->all();
    }

    private function idList($ids): array
    {
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }
        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', array_filter($ids, function ($id) {
            return ctype_digit(trim((string) $id));
        }))));
    }
}
