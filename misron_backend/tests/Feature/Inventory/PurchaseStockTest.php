<?php

namespace Tests\Feature\Inventory;

use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseProduct;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Tests\Inventory\InventoryTestCase;

/**
 * Receiving goods, editing a purchase, deleting it, and returning goods to the supplier move the
 * shelf and the purchase batches with single statements and refuse what the records cannot
 * support (removing units that were sold), instead of writing back a number read earlier.
 */
class PurchaseStockTest extends InventoryTestCase
{
    private Supplier $supplier;

    /** @var int[] products whose purchase rows this test made (MyISAM tables do not roll back) */
    private array $touched = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([['Inventory', 'asset', 'inventory', '1-15001'], ['Accounts Payable', 'liability', 'payable', '2-21001']] as [$name, $type, $sub, $code]) {
            ChartOfAccount::create(['code' => $code . '-' . random_int(100, 999), 'name' => $name, 'type' => $type, 'sub_type' => $sub, 'is_deleted' => '0', 'status' => 'active']);
        }
        $this->supplier = Supplier::create(['business_name' => 'Test Supplier', 'mobile' => '01711111111', 'opening_balance' => '0']);
        $this->actingAs($this->makeAdmin());
    }

    protected function tearDown(): void
    {
        if ($this->touched) {
            $purchaseIds = DB::table('purchase_products')->whereIn('product_id', $this->touched)->pluck('purchase_id')->unique()->all();
            DB::table('purchase_products')->whereIn('product_id', $this->touched)->delete();
            DB::table('purchases')->whereIn('id', $purchaseIds)->delete();
            $returnIds = DB::table('purchase_returns')->whereIn('purchase_id', $purchaseIds)->pluck('id')->all();
            DB::table('purchase_return_products')->whereIn('purchase_return_id', $returnIds)->delete();
            DB::table('purchase_returns')->whereIn('id', $returnIds)->delete();
        }
        parent::tearDown();
    }

    private function plainProduct(int $onShelf = 0): Product
    {
        $product = $this->makeProduct(['name' => 'Receiving Test ' . random_int(1000, 9999), 'variant_product' => 0], [['variant' => '', 'qty' => $onShelf, 'price' => 1000]]);
        $this->touched[] = $product->id;

        return $product;
    }

    private function purchaseBody(Product $product, int $qty, float $unitCost = 400): array
    {
        return [
            'supplier_id' => $this->supplier->id,
            'purchase_date' => now()->toDateString(),
            'product_id' => [$product->id],
            'name' => [$product->name],
            'variant' => [null],
            'purchase_quantity' => [$qty],
            'buying_unit_cost' => [$unitCost],
            'total_cost' => [$qty * $unitCost],
        ];
    }

    private function receive(Product $product, int $qty): Purchase
    {
        $this->post(route('purchase.store'), $this->purchaseBody($product, $qty))->assertOk();

        return Purchase::findOrFail(PurchaseProduct::where('product_id', $product->id)->orderByDesc('id')->firstOrFail()->purchase_id);
    }

    private function shelf(Product $product): int
    {
        return (int) DB::table('product_stocks')->where('product_id', $product->id)->sum('qty');
    }

    private function batch(Product $product): PurchaseProduct
    {
        return PurchaseProduct::where('product_id', $product->id)->firstOrFail();
    }

    public function test_receiving_goods_creates_a_costed_batch_and_adds_to_the_shelf(): void
    {
        $product = $this->plainProduct(2);

        $this->receive($product, 10);

        $this->assertSame(12, $this->shelf($product));
        $batch = $this->batch($product);
        $this->assertSame(10, (int) $batch->remaining_qty);
        $this->assertEquals(400, (float) $batch->buying_unit_cost);
        $this->assertSame(10, (int) DB::table('products')->where('id', $product->id)->value('current_stock'));
    }

    public function test_a_purchase_for_a_product_with_no_stock_row_creates_one(): void
    {
        $product = $this->makeProduct(['name' => 'No shelf yet', 'variant_product' => 0], []);
        DB::table('product_stocks')->where('product_id', $product->id)->delete();
        $this->touched[] = $product->id;

        $this->receive($product, 5);

        $this->assertSame(5, $this->shelf($product));
    }

    public function test_the_shelf_is_moved_by_the_difference_so_units_sold_since_are_kept(): void
    {
        $product = $this->plainProduct(0);
        $purchase = $this->receive($product, 10);
        // A shopper buys 4 units through the storefront while the purchase form is open.
        $this->placeGuestOrder([[$product, '', 4]])->assertJson(['result' => true]);
        $this->assertSame(6, $this->shelf($product));

        // The form still shows 10; the admin raises it to 15.
        $this->put(route('purchase.update', $purchase->id), $this->purchaseBody($product, 15))->assertOk();

        $this->assertSame(11, $this->shelf($product), '6 left after the sale, plus the 5 added: the sale is not erased');
        $this->assertSame(11, (int) $this->batch($product)->remaining_qty);
        $this->assertSame('15', (string) $this->batch($product)->purchase_quantity);
    }

    public function test_a_purchase_cannot_be_reduced_below_what_was_already_sold(): void
    {
        $product = $this->plainProduct(0);
        $purchase = $this->receive($product, 10);
        $this->placeGuestOrder([[$product, '', 6]])->assertJson(['result' => true]);

        $response = $this->put(route('purchase.update', $purchase->id), $this->purchaseBody($product, 5));

        $response->assertStatus(500)->assertJson(['success' => false]);
        $this->assertStringContainsString('below sold amount', $response->json('message'));
        $this->assertSame(4, $this->shelf($product));
        $this->assertSame(4, (int) $this->batch($product)->remaining_qty);
        $this->assertSame('10', (string) $this->batch($product)->purchase_quantity);
    }

    public function test_a_purchase_with_sold_units_cannot_be_deleted_but_an_untouched_one_can(): void
    {
        $sold = $this->plainProduct(0);
        $purchaseSold = $this->receive($sold, 10);
        $this->placeGuestOrder([[$sold, '', 2]])->assertJson(['result' => true]);

        $this->get(route('purchase.delete', $purchaseSold->id));
        $this->assertNotNull(Purchase::find($purchaseSold->id), 'a purchase whose units were sold stays');
        $this->assertSame(8, $this->shelf($sold));

        $fresh = $this->plainProduct(3);
        $purchaseFresh = $this->receive($fresh, 7);
        $this->assertSame(10, $this->shelf($fresh));
        $this->get(route('purchase.delete', $purchaseFresh->id));
        $this->assertNull(Purchase::find($purchaseFresh->id));
        $this->assertSame(3, $this->shelf($fresh));
        $this->assertSame(0, PurchaseProduct::where('product_id', $fresh->id)->count());
    }

    private function returnBody(Purchase $purchase, PurchaseProduct $batch, Product $product, int $qty): array
    {
        return [
            'supplier_id' => $this->supplier->id,
            'purchase_id' => [$purchase->id],
            'transaction_date' => now()->toDateString(),
            'product_id' => [$product->id],
            'product_name' => [$product->name],
            'product_quantity' => [$qty],
            'product_unit_cost' => [400],
            'product_subtotal' => [$qty * 400],
            'purchase_product_id' => [$batch->id],
            'variant_name' => [null],
        ];
    }

    public function test_returning_goods_to_the_supplier_takes_them_off_the_shelf_and_the_batch(): void
    {
        $product = $this->plainProduct(0);
        $purchase = $this->receive($product, 10);
        $batch = $this->batch($product);

        $this->post(route('purchase-return.store'), $this->returnBody($purchase, $batch, $product, 3))->assertSuccessful();

        $this->assertSame(7, $this->shelf($product));
        $fresh = $this->batch($product);
        $this->assertSame(7, (int) $fresh->remaining_qty);
        $this->assertSame('7', (string) $fresh->purchase_quantity);
        $this->assertEquals(2800, (float) $fresh->total_cost);
    }

    public function test_a_return_larger_than_what_is_left_is_refused_and_changes_nothing(): void
    {
        $product = $this->plainProduct(0);
        $purchase = $this->receive($product, 10);
        $this->placeGuestOrder([[$product, '', 8]])->assertJson(['result' => true]);
        $batch = $this->batch($product);

        $response = $this->post(route('purchase-return.store'), $this->returnBody($purchase, $batch, $product, 5));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('sold', $response->json('message'));
        $this->assertSame(2, $this->shelf($product), 'the shelf is as the sale left it');
        $this->assertSame(2, (int) $this->batch($product)->remaining_qty);
        $this->assertSame('10', (string) $this->batch($product)->purchase_quantity);
    }

    public function test_changing_a_return_replaces_it_instead_of_stacking_it(): void
    {
        $product = $this->plainProduct(0);
        $purchase = $this->receive($product, 10);
        $this->post(route('purchase-return.store'), $this->returnBody($purchase, $this->batch($product), $product, 2))->assertSuccessful();
        $this->assertSame(8, $this->shelf($product));
        $returnId = DB::table('purchase_returns')->where('purchase_id', $purchase->id)->value('id');

        $body = $this->returnBody($purchase, $this->batch($product), $product, 4);
        unset($body['purchase_product_id'], $body['variant_name']);
        $body['reference_no'] = 'PR-EDIT-' . random_int(100, 999);
        $r2 = $this->put(route('purchase-return.update', $returnId), $body);
        $this->assertContains($r2->getStatusCode(), [200, 302], substr($r2->getContent(), 0, 500));

        $this->assertSame(6, $this->shelf($product), 'the first return of 2 is undone and one of 4 applied');
        $this->assertSame(6, (int) $this->batch($product)->remaining_qty);
        $this->assertSame('6', (string) $this->batch($product)->purchase_quantity);
    }
}
