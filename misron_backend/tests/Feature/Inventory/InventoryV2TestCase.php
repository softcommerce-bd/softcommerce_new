<?php

namespace Tests\Feature\Inventory;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Services\Inventory\InventoryMode;
use App\Services\Inventory\StockBalance;
use App\Services\Purchasing\GoodsReceiptService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Inventory\InventoryTestCase;

/** Confirmed-order stock model, on the real MySQL test schema with the inventory_v2 migrations applied. */
abstract class InventoryV2TestCase extends InventoryTestCase
{
    protected int $supplierId;

    protected function setUp(): void
    {
        parent::setUp();
        if (!\Illuminate\Support\Facades\Schema::hasTable('order_demands')) {
            $this->markTestSkipped('inventory_v2 migrations are not applied to this test database.');
        }
        config(['inventory.confirmed_only' => true]);
        InventoryMode::reset();
        \App\Services\Accounting\LegacyLedgerBridge::reset();
        $this->supplierId = DB::table('suppliers')->insertGetId(['slug' => 's-' . Str::random(6), 'business_name' => 'Test Supplier', 'contact_person_name' => 'T', 'mobile' => '017' . random_int(10000000, 99999999), 'opening_balance' => '0', 'due_amount' => 0, 'paid_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        config(['inventory.confirmed_only' => false]);
        InventoryMode::reset();
        \App\Services\Accounting\LegacyLedgerBridge::reset();
        parent::tearDown();
    }

    /** A product with a stock row at zero and no cost layers, optionally with a selling policy. */
    protected function productAtZero(array $attrs = [], array $variants = ['']): Product
    {
        return $this->makeProduct($attrs + ['variant_product' => count($variants) > 1 ? 1 : 0, 'unit_price' => 1000], array_map(fn ($v) => ['variant' => $v, 'qty' => 0, 'price' => 1000], $variants));
    }

    /** @param array<int, array{0:Product,1:string,2:int}> $lines [product, variant, qty] */
    protected function makeOrder(array $lines, string $status = 'pending'): Order
    {
        $user = $this->makeUser('customer');
        $order = new Order();
        $order->forceFill(['user_id' => $user->id, 'seller_id' => 1, 'code' => Order::generateUniqueCode(), 'delivery_status' => 'pending', 'payment_status' => 'unpaid', 'payment_type' => 'cash_on_delivery',
            'shipping_address' => json_encode(['name' => 'T', 'phone' => '01700000000', 'address' => 'x']), 'grand_total' => 0, 'date' => time(), 'order_from' => 'app'])->save();
        foreach ($lines as [$product, $variant, $qty]) {
            $d = new OrderDetail();
            $d->order_id = $order->id;
            $d->seller_id = 1;
            $d->product_id = $product->id;
            $d->variation = $variant;
            $d->quantity = $qty;
            $d->price = 1000 * $qty;
            $d->selling_price = 1000;
            $d->tax = 0;
            $d->shipping_cost = 0;
            $d->buying_price = 0;
            $d->profit = 0;
            $d->delivery_status = 'pending';
            $d->save();
        }
        if ($status !== 'pending') {
            $this->setStatus($order, $status);
        }

        return $order->fresh();
    }

    protected function setStatus(Order $order, string $status): Order
    {
        $order = Order::find($order->id);
        $order->delivery_status = $status;
        $order->save();

        return $order;
    }

    protected function receive(Product $p, string $variant, int $qty, string $cost, ?string $key = null, array $extra = []): array
    {
        return app(GoodsReceiptService::class)->receive($key ?? 'k-' . Str::random(10), $extra + ['supplier_id' => $this->supplierId, 'lines' => [['product_id' => $p->id, 'variant' => $variant, 'qty' => $qty, 'unit_cost' => $cost]]]);
    }

    protected function snap(Product $p, string $variant = ''): array
    {
        return StockBalance::snapshot($p->id, $variant);
    }

    protected function detail(Order $o, int $i = 0): OrderDetail
    {
        return OrderDetail::where('order_id', $o->id)->orderBy('id')->get()[$i];
    }
}
