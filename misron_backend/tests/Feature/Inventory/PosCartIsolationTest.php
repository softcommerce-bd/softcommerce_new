<?php

namespace Tests\Feature\Inventory;

use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use App\Utility\PosUtility;
use Tests\Inventory\InventoryTestCase;

/**
 * Approved rule (P-1): a POS sale deletes only the cart rows it sold. Other counters' carts and a registered customer's separate website cart stay.
 */
class PosCartIsolationTest extends InventoryTestCase
{
    private function posCart(int $owner, $userId, $temp, $product, int $qty): Cart
    {
        return Cart::create(['owner_id' => $owner, 'user_id' => $userId, 'temp_user_id' => $temp, 'product_id' => $product->id, 'variation' => 'M', 'quantity' => $qty, 'price' => $product->unit_price, 'tax' => 0, 'shipping_cost' => 0, 'discount' => 0]);
    }

    private function sale(array $data): array
    {
        return PosUtility::orderStore(array_merge(['payment_type' => 'cash_on_delivery', 'shippingInfo' => ['name' => 'Customer', 'phone' => '01700000000', 'address' => 'Counter']], $data));
    }

    public function test_a_walk_in_sale_leaves_the_carts_of_other_walk_in_customers_alone()
    {
        $this->actingAs($this->makeAdmin());
        $owner = (int) User::where('user_type', 'admin')->orderBy('id')->value('id');
        $product = $this->makeStockedProduct(['M' => 20], 1000, 400);
        $mine = $this->posCart($owner, null, 'WALK-A', $product, 2);
        $other = $this->posCart($owner, null, 'WALK-B', $product, 3);

        $this->assertSame(1, $this->sale(['user_id' => null, 'temp_usder_id' => 'WALK-A'])['success']);

        $this->assertNull(Cart::find($mine->id), 'the sold cart is gone');
        $this->assertNotNull(Cart::find($other->id), "another counter's walk-in cart is untouched");
        $this->assertSame(18, $this->stockQty($product, 'M'));
    }

    public function test_a_sale_to_a_registered_customer_leaves_that_customers_website_cart_untouched()
    {
        $this->actingAs($this->makeAdmin());
        $owner = (int) User::where('user_type', 'admin')->orderBy('id')->value('id');
        $customer = new User();
        $customer->forceFill(['name' => 'Reg', 'email' => uniqid() . '@pos.test', 'password' => 'x', 'user_type' => 'customer'])->save();
        $product = $this->makeStockedProduct(['M' => 20], 1000, 400);
        $pos = $this->posCart($owner, $customer->id, null, $product, 2);
        $website = Cart::create(['owner_id' => null, 'user_id' => $customer->id, 'temp_user_id' => null, 'product_id' => $product->id, 'variation' => 'M', 'quantity' => 5, 'price' => $product->unit_price, 'tax' => 0, 'shipping_cost' => 0, 'discount' => 0]);

        $result = $this->sale(['user_id' => $customer->id, 'temp_usder_id' => null]);

        $this->assertSame(1, $result['success'], $result['message'] ?? '');
        $this->assertNull(Cart::find($pos->id), 'the POS cart of this sale is gone');
        $this->assertNotNull(Cart::find($website->id), "the customer's separate website cart is untouched");
        $this->assertSame(5, (int) Cart::find($website->id)->quantity);
        $this->assertSame(18, $this->stockQty($product, 'M'), 'only the 2 sold units left the shelf');
    }
}
