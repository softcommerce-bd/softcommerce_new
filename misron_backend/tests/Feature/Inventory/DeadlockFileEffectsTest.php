<?php

namespace Tests\Feature\Inventory;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * T-5 F-02: files are stored once per request and removed if the request fails; files are deleted only AFTER the commit.
 * purchaseDelete, storePurchaseReturn and updatePurchaseReturn used to delete/store inside the transaction.
 */
class DeadlockFileEffectsTest extends InventoryV2TestCase
{
    private function purchase(bool $withDocument = true): array
    {
        $p = $this->productAtZero();
        $body = ['op_key' => 'fe-' . uniqid(), 'supplier_id' => $this->supplierId, 'purchase_date' => now()->toDateString(), 'product_id' => [$p->id], 'name' => ['x'], 'variant' => [''],
            'purchase_quantity' => [5], 'buying_unit_cost' => [20], 'total_cost' => [100]];
        if ($withDocument) {
            $body['document'] = UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf');
        }
        $this->post(route('purchase.store'), $body, ['Accept' => 'application/json'])->assertOk();
        $purchaseId = (int) DB::table('purchases')->where('supplier_id', $this->supplierId)->orderByDesc('id')->value('id');

        return [$p, $purchaseId, (int) DB::table('purchase_products')->where('purchase_id', $purchaseId)->value('id')];
    }

    private function returnBody(object $p, int $purchaseId, int $ppId, bool $document): array
    {
        $b = ['supplier_id' => $this->supplierId, 'transaction_date' => now()->toDateString(), 'product_id' => [$p->id], 'product_quantity' => [2], 'product_unit_cost' => [20], 'product_subtotal' => [40],
            'product_name' => ['x'], 'purchase_id' => [$purchaseId], 'purchase_product_id' => [$ppId], 'variant_name' => ['']];
        if ($document) {
            $b['document'] = UploadedFile::fake()->create('return.pdf', 10, 'application/pdf');
        }

        return $b;
    }

    public function test_purchase_delete_keeps_the_document_when_the_delete_is_rolled_back()
    {
        Storage::fake('public');
        $this->actingAs($this->makeAdmin());
        [, $purchaseId] = $this->purchase();
        $doc = DB::table('purchases')->where('id', $purchaseId)->value('document');
        $this->assertTrue(Storage::disk('public')->exists($doc));

        $inj = DeadlockInjector::always('/^delete from `purchase_products`/i');
        $this->delete(route('purchase.delete', $purchaseId));
        $this->assertSame(3, $inj->thrown, 'three attempts');
        $this->assertSame(1, DB::table('purchases')->where('id', $purchaseId)->count(), 'the purchase is intact');
        $this->assertTrue(Storage::disk('public')->exists($doc), 'the document survived the rolled-back delete (it used to be deleted before the commit)');
    }

    public function test_purchase_delete_is_retried_after_one_deadlock_and_removes_the_document_only_after_the_commit()
    {
        Storage::fake('public');
        $this->actingAs($this->makeAdmin());
        [, $purchaseId] = $this->purchase();
        $doc = DB::table('purchases')->where('id', $purchaseId)->value('document');

        $once = DeadlockInjector::once('/^delete from `purchase_products`/i');
        $res = $this->delete(route('purchase.delete', $purchaseId));
        $this->assertSame(1, $once->thrown);
        $this->assertSame('1', $res->headers->get('X-Deadlock-Retries'), 'the redirect+flash answer is retried');
        $this->assertSame(0, DB::table('purchases')->where('id', $purchaseId)->count());
        $this->assertFalse(Storage::disk('public')->exists($doc), 'the document is removed after the committed delete');
    }

    public function test_purchase_return_stores_its_document_once_across_a_retry()
    {
        Storage::fake('public');
        $this->actingAs($this->makeAdmin());
        [$p, $purchaseId, $ppId] = $this->purchase(false);
        $once = DeadlockInjector::once('/^insert into `purchase_return_products`/i');
        $this->post(route('purchase-return.store'), $this->returnBody($p, $purchaseId, $ppId, true), ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(1, $once->thrown);
        $this->assertSame(1, DB::table('purchase_returns')->count(), 'one return');
        $this->assertCount(1, Storage::disk('public')->allFiles('purchase_return_documents'), 'the upload was stored once, not once per attempt');
    }

    public function test_purchase_return_leaves_no_file_when_it_finally_fails()
    {
        Storage::fake('public');
        $this->actingAs($this->makeAdmin());
        [$p, $purchaseId, $ppId] = $this->purchase(false);
        $always = DeadlockInjector::always('/^insert into `purchase_return_products`/i');
        $this->post(route('purchase-return.store'), $this->returnBody($p, $purchaseId, $ppId, true), ['Accept' => 'application/json']);
        $this->assertSame(3, $always->thrown);
        $this->assertSame(0, DB::table('purchase_returns')->count());
        $this->assertSame([], Storage::disk('public')->allFiles('purchase_return_documents'), 'no orphan from the failed request');
    }

    public function test_purchase_return_update_keeps_the_old_document_when_it_fails_and_replaces_it_after_commit()
    {
        Storage::fake('public');
        $this->actingAs($this->makeAdmin());
        [$p, $purchaseId, $ppId] = $this->purchase(false);
        $this->post(route('purchase-return.store'), $this->returnBody($p, $purchaseId, $ppId, true), ['Accept' => 'application/json'])->assertOk();
        $returnId = (int) DB::table('purchase_returns')->value('id');
        $old = DB::table('purchase_returns')->where('id', $returnId)->value('document');
        $this->assertTrue(Storage::disk('public')->exists($old));

        $always = DeadlockInjector::always('/^insert into `purchase_return_products`/i');
        $this->put(route('purchase-return.update', $returnId), $this->returnBody($p, $purchaseId, $ppId, true), ['Accept' => 'application/json']);
        $this->assertSame(3, $always->thrown);
        $this->assertSame($old, DB::table('purchase_returns')->where('id', $returnId)->value('document'));
        $this->assertTrue(Storage::disk('public')->exists($old), 'old document kept');
        $this->assertCount(1, Storage::disk('public')->allFiles(), 'and no new file from the failed attempts');
    }
}
