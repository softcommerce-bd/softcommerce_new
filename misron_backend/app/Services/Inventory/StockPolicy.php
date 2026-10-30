<?php

namespace App\Services\Inventory;

use App\Services\Inventory\Exceptions\OutOfStock;
use App\Services\Inventory\Exceptions\VariantUnavailable;
use Illuminate\Support\Facades\DB;

/**
 * What a customer may order, per product selling policy. Server-side and authoritative; the storefront only mirrors it.
 *   stock_only : an order needs available stock >= quantity
 *   backorder  : orders allowed at zero or negative available stock, optionally up to max_backorder_qty of shortage
 *   preorder   : like backorder but shown as a preorder, with an optional date and maximum
 */
class StockPolicy
{
    public const STOCK_ONLY = 'stock_only';
    public const BACKORDER = 'backorder';
    public const PREORDER = 'preorder';

    /** @return array{state:string, can_order:bool, available:int, on_hand:int, demand:int, policy:string, max_additional:?int, expected_at:?string} */
    public static function describe(int $productId, $variant, int $quantity = 1): array
    {
        $p = DB::table('products')->where('id', $productId)->first(['stock_policy', 'max_backorder_qty', 'max_preorder_qty', 'preorder_available_at', 'digital']);
        $policy = $p->stock_policy ?? self::STOCK_ONLY;
        $s = StockBalance::snapshot($productId, $variant);
        $available = $s['available'];
        $shortageAfter = max(0, $s['demand'] + $quantity - max($s['on_hand'], 0)); // confirmed shortage if this quantity were confirmed

        if ($p && (int) $p->digital === 1) {
            return self::out('in_stock', true, $s, $policy, null, null);
        }

        if ($policy === self::BACKORDER || $policy === self::PREORDER) {
            $max = $policy === self::BACKORDER ? $p->max_backorder_qty : $p->max_preorder_qty;
            $inStock = $available >= $quantity && !($policy === self::PREORDER && $p->preorder_available_at && now()->lt($p->preorder_available_at));
            $can = $inStock || $max === null || $shortageAfter <= (int) $max;
            $state = $inStock ? 'in_stock' : ($policy === self::PREORDER ? 'preorder' : 'available_on_order');
            if (!$can) {
                $state = 'out_of_stock';
            }

            return self::out($state, $can, $s, $policy, $max === null ? null : max(0, (int) $max - max(0, $s['demand'] - max($s['on_hand'], 0))), $p->preorder_available_at ?? null);
        }

        return self::out($available >= $quantity ? 'in_stock' : 'out_of_stock', $available >= $quantity, $s, self::STOCK_ONLY, null, null);
    }

    private static function out(string $state, bool $can, array $s, string $policy, ?int $maxAdditional, $expected): array
    {
        return ['state' => $state, 'can_order' => $can, 'available' => $s['available'], 'on_hand' => $s['on_hand'], 'demand' => $s['demand'], 'policy' => $policy,
            'max_additional' => $maxAdditional, 'expected_at' => $expected ? (string) $expected : null];
    }

    /**
     * @throws VariantUnavailable no stock row exists for this variation
     * @throws OutOfStock         the policy does not allow this quantity
     */
    public static function assertCanOrder(int $productId, $variant, int $quantity): void
    {
        $key = StockBalance::key($variant);
        if (!DB::table('product_stocks')->where('product_id', $productId)->where('variant', $key)->exists()) {
            throw new VariantUnavailable("No stock row for product {$productId} variation '{$key}'.", ['product_id' => $productId, 'variant' => $key, 'requested' => $quantity]);
        }
        $d = self::describe($productId, $key, $quantity);
        if (!$d['can_order']) {
            throw new OutOfStock("Product {$productId} '{$key}': {$d['available']} available, {$quantity} wanted ({$d['policy']}).",
                ['product_id' => $productId, 'variant' => $key, 'requested' => $quantity, 'available' => max(0, $d['available'])]);
        }
    }

    /**
     * The quantity cart and checkout checks compare against, in place of product_stocks.qty.
     * Legacy mode: the shelf quantity, exactly as before. Confirmed-order mode: what the selling policy allows right now:
     * available stock for stock-only products, available plus the remaining backorder/preorder room (or "plenty") otherwise.
     */
    public static function effectiveQty($stockRow): int
    {
        if (!$stockRow) {
            return 0;
        }
        if (!InventoryMode::confirmedOnly()) {
            return (int) $stockRow->qty;
        }
        $d = self::describe((int) $stockRow->product_id, $stockRow->variant, 1);
        if ($d['policy'] === self::STOCK_ONLY) {
            return max(0, $d['available']);
        }

        return max(0, $d['available']) + ($d['max_additional'] ?? 1000000);
    }

    /** For storefront display (product API): the state a customer sees for one variation. */
    public static function storefrontState(int $productId, $variant): array
    {
        if (!InventoryMode::confirmedOnly()) {
            $qty = (int) DB::table('product_stocks')->where('product_id', $productId)->where('variant', StockBalance::key($variant))->value('qty');

            return ['state' => $qty > 0 ? 'in_stock' : 'out_of_stock', 'can_order' => $qty > 0, 'policy' => self::STOCK_ONLY, 'expected_at' => null];
        }
        $d = self::describe($productId, $variant, 1);

        return ['state' => $d['state'], 'can_order' => $d['can_order'], 'policy' => $d['policy'], 'expected_at' => $d['expected_at']];
    }
}
