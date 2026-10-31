<?php

namespace Tests\Feature\Inventory;

/** The admin order page for confirmed-order (v2) lines. */
class OrderDetailsScreenTest extends InventoryV2TestCase
{
    public function test_order_details_page_renders_for_pending_confirmed_and_shipped_v2_orders()
    {
        $p = $this->productAtZero(['stock_policy' => 'backorder']);
        $this->actingAs($this->makeAdmin());
        foreach (['pending', 'confirmed'] as $status) {
            $o = $this->makeOrder([[$p, '', 2]], $status);
            $this->get(route('all_orders.show', encrypt($o->id)))->assertOk()->assertSee($o->code);
        }
        $this->receive($p, '', 5, '10');
        $o = $this->makeOrder([[$p, '', 2]], 'picked_up');
        $this->get(route('all_orders.show', encrypt($o->id)))->assertOk();
    }

    public function test_receiving_a_purchase_order_line_flashes_the_receipt_number_on_the_page_it_redirects_to()
    {
        $p = $this->productAtZero();
        $po = app(\App\Services\Purchasing\PurchaseOrderService::class)->create('flash-po', ['supplier_id' => $this->supplierId, 'status' => 'ordered', 'lines' => [['product_id' => $p->id, 'qty' => 5, 'unit_cost' => '10']]]);
        $detail = \Illuminate\Support\Facades\DB::table('purchase_order_details')->where('purchase_order_id', $po['purchase_order_id'])->first();
        $this->actingAs($this->makeAdmin());
        $this->followingRedirects()->post(route('purchasing.receiving.receive', $po['purchase_order_id']), ['op_key' => 'f1', 'invoice_no' => 'FL-1', 'lines' => [['detail_id' => $detail->id, 'qty' => 3, 'unit_cost' => '10']]])
            ->assertOk()->assertSee('Goods received: GRN-', false)->assertSee('invoice recorded', false);
    }
}
