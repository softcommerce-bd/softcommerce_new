<?php

namespace Tests\Inventory;

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseProduct;
use App\Services\Inventory\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Funnel\FunnelTestCase;

/**
 * Base for the stock tests.
 *
 * Every test runs in a rolled-back transaction against a *_test database. One
 * table needs extra care: on a database that still has the original schema,
 * purchase_products and purchases are MyISAM, which ignores transactions, so
 * the batches a test creates (or consumes) would stay behind. Those tests
 * delete what they made in tearDown. On the converted schema (InnoDB) the
 * rollback does it and the cleanup finds nothing to do.
 */
abstract class InventoryTestCase extends FunnelTestCase
{
    /** @var int[] */
    private array $madeBatches = [];

    /** @var int[] */
    private array $madePurchases = [];

    protected function setUp(): void
    {
        parent::setUp();
        StockLedger::forgetEngineCache();
    }

    protected function tearDown(): void
    {
        // Runs before the framework rolls the transaction back; MyISAM rows are
        // already committed, so they have to be removed by hand.
        if ($this->madeBatches) {
            DB::table('purchase_products')->whereIn('id', $this->madeBatches)->delete();
        }
        if ($this->madePurchases) {
            DB::table('purchases')->whereIn('id', $this->madePurchases)->delete();
        }
        $this->madeBatches = $this->madePurchases = [];

        parent::tearDown();
    }

    /** True once product_stocks has a unique key on (product_id, variant). */
    protected function stockKeyIsUnique(): bool
    {
        return DB::selectOne(
            "SELECT 1 AS found FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'product_stocks' AND non_unique = 0 AND index_name <> 'PRIMARY' GROUP BY index_name HAVING GROUP_CONCAT(column_name ORDER BY seq_in_index) = 'product_id,variant'"
        ) !== null;
    }

    /** True when purchase_products rolls back with the transaction. */
    protected function batchesAreTransactional(): bool
    {
        return app(StockLedger::class)->fifoIsTransactional();
    }

    /**
     * A purchase batch holding $qty units bought at $cost each.
     *
     * @return PurchaseProduct
     */
    protected function makeBatch(Product $product, ?string $variant, int $qty, float $cost = 100, ?int $remaining = null, ?int $purchaseId = null): PurchaseProduct
    {
        if ($purchaseId === null) {
            $purchaseId = (int) DB::table('purchases')->insertGetId([
                'supplier_id' => 1,
                'reference_no' => 'T-' . Str::upper(Str::random(8)),
                'purchase_date' => date('Y-m-d'),
                'net_total_amount' => $qty * $cost,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->madePurchases[] = $purchaseId;
        }

        $batch = PurchaseProduct::create([
            'product_id' => $product->id,
            'purchase_id' => $purchaseId,
            'product_name' => $product->name,
            'variant' => $variant,
            'purchase_quantity' => (string) $qty,
            'remaining_qty' => $remaining ?? $qty,
            'buying_unit_cost' => (string) $cost,
            'total_cost' => (string) ($qty * $cost),
            'product_tax' => '0',
        ]);
        $this->madeBatches[] = $batch->id;

        return $batch;
    }

    /** A variation product whose stock rows and batches agree. */
    protected function makeStockedProduct(array $variants = ['M' => 10], float $price = 1000, float $cost = 400, array $attributes = []): Product
    {
        $product = $this->makeProduct(array_merge(['unit_price' => $price, 'variant_product' => 1], $attributes), array_map(
            fn ($variant, $qty) => ['variant' => $variant, 'qty' => $qty, 'price' => $price],
            array_keys($variants),
            array_values($variants)
        ));
        foreach ($variants as $variant => $qty) {
            $this->makeBatch($product, (string) $variant, $qty, $cost);
        }

        return $product;
    }

    protected function stockQty(Product $product, string $variant): int
    {
        return (int) ProductStock::where('product_id', $product->id)->where('variant', $variant)->value('qty');
    }

    /** Sum of remaining units in the batches of a variation. */
    protected function batchRemaining(Product $product, ?string $variant): int
    {
        return (int) PurchaseProduct::where('product_id', $product->id)
            ->when($variant === null || $variant === '', fn ($q) => $q->whereNull('variant'), fn ($q) => $q->where('variant', $variant))
            ->sum('remaining_qty');
    }

    /**
     * Puts lines into a guest cart and posts the storefront checkout.
     *
     * @param  array<int, array{0: Product, 1: string, 2: int}>  $lines  [product, variant, quantity]
     */
    protected function placeGuestOrder(array $lines, array $body = [], ?string $tempUserId = null)
    {
        $tempUserId = $tempUserId ?? (string) random_int(900000000, 999999999);

        foreach ($lines as [$product, $variant, $quantity]) {
            Cart::create([
                'temp_user_id' => $tempUserId,
                'product_id' => $product->id,
                'variation' => $variant,
                'quantity' => $quantity,
                'price' => $product->unit_price,
                'tax' => 0,
                'shipping_cost' => 0,
                'discount' => 0,
            ]);
        }

        $this->forgetAuth();

        return $this->postJson('/api/v2/guest/order/store', array_merge([
            'temp_user_id' => $tempUserId,
            'payment_type' => 'cash_on_delivery',
            'delivery_type' => 'home_delivery',
            'guest_info' => [
                'name' => 'Test Buyer',
                'phone' => '017' . random_int(10000000, 99999999),
                'address' => 'House 1, Road 2, Dhaka',
                'city_name' => 'Dhaka',
            ],
        ], $body));
    }
}
