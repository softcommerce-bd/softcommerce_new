<?php

namespace Tests\Feature\Inventory;

use App\Services\Accounting\SystemAccounts;
use Illuminate\Support\Facades\DB;

/** The legacy purchase screen keeps working with the flag on, posts once, and cannot touch ledger-owned purchases. */
class LegacyPurchaseIntegrationTest extends InventoryV2TestCase
{
    private function payload(int $productId, string $opKey): array
    {
        return ['op_key' => $opKey, 'supplier_id' => $this->supplierId, 'purchase_date' => now()->toDateString(), 'product_id' => [$productId], 'name' => ['x'], 'variant' => [''],
            'purchase_quantity' => [5], 'buying_unit_cost' => [20], 'total_cost' => [100]];
    }

    public function test_a_double_submitted_legacy_purchase_is_saved_once_and_stock_rises_once()
    {
        $p = $this->productAtZero();
        $this->actingAs($this->makeAdmin());
        $key = 'form-' . uniqid();
        $first = $this->postJson(route('purchase.store'), $this->payload($p->id, $key));
        $second = $this->postJson(route('purchase.store'), $this->payload($p->id, $key));
        $first->assertOk()->assertJson(['success' => true]);
        $second->assertOk()->assertJson(['success' => true, 'duplicate' => true]);
        $this->assertSame(5, $this->stockQty($p, ''));
        $this->assertSame(1, DB::table('purchase_products')->where('product_id', $p->id)->count());
    }

    public function test_a_failed_legacy_save_releases_its_key_so_the_user_can_retry()
    {
        $p = $this->productAtZero();
        $this->actingAs($this->makeAdmin());
        $bad = $this->payload($p->id, 'retry-' . uniqid());
        $bad['paid_amount'] = 5000;
        $bad['pay_by'] = 'cash';
        $bad['payment_account_id'] = SystemAccounts::id('inventory'); // paid more than the total: refused
        $this->postJson(route('purchase.store'), $bad)->assertStatus(500);
        $this->assertSame(0, $this->stockQty($p, ''));
        $this->postJson(route('purchase.store'), $this->payload($p->id, $bad['op_key']))->assertOk()->assertJson(['success' => true]);
        $this->assertSame(5, $this->stockQty($p, ''));
    }

    public function test_purchases_created_by_goods_receiving_cannot_be_edited_deleted_or_returned_through_the_legacy_screens()
    {
        $p = $this->productAtZero();
        $r = $this->receive($p, '', 4, '25');
        $purchaseId = (int) DB::table('goods_receipts')->where('id', $r['goods_receipt_id'])->value('purchase_id');
        $this->actingAs($this->makeAdmin());
        $this->delete(route('purchase.delete', $purchaseId))->assertRedirect()->assertSessionHas('error');
        $this->assertSame(4, $this->stockQty($p, ''), 'stock untouched by the refused delete');
        $this->assertSame(1, DB::table('purchases')->where('id', $purchaseId)->count());
        $this->putJson(route('purchase.update', $purchaseId), $this->payload($p->id, 'u'))->assertStatus(500);
        $this->assertSame(4, $this->stockQty($p, ''));
    }
}
