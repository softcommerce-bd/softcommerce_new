<?php

namespace Tests\Feature\Inventory;

use App\Models\Address;
use App\Models\Cart;
use App\Models\ChartOfAccount;
use App\Models\Order;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\SystemAccounts;
use App\Services\Inventory\InventoryMode;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * T-5: every affected workflow, with a deadlock injected AFTER real work was done inside its transaction.
 *  once   -> the operation succeeds on the retry and everything exists exactly once
 *  always -> exactly three attempts, then a clean failure with nothing left behind (rows, balances, outbox, files)
 */
class DeadlockWorkflowRetryTest extends InventoryV2TestCase
{
    private function world(): array
    {
        return [
            'purchases' => DB::table('purchases')->count(), 'layers' => DB::table('purchase_products')->count(), 'stock' => (int) DB::table('product_stocks')->sum('qty'),
            'batchQty' => (int) DB::table('purchase_products')->sum('remaining_qty'), 'orders' => DB::table('orders')->count(), 'details' => DB::table('order_details')->count(),
            'supplierDue' => (float) DB::table('suppliers')->sum('due_amount'), 'balances' => DB::table('chart_of_accounts')->orderBy('id')->pluck('current_balance', 'id')->all(),
            'entries' => DB::table('journal_entries')->count(), 'outbox' => DB::table('ledger_outbox')->count(), 'logs' => DB::table('account_statement_logs')->count(),
            'settlements' => DB::table('courier_settlements')->count(), 'supplierPayments' => DB::table('supplier_payments')->count(), 'adjustments' => DB::table('stock_adjustments')->count(),
            'chatOrders' => \Illuminate\Support\Facades\Schema::hasTable('chat_conversation_orders') ? DB::table('chat_conversation_orders')->count() : 0,
        ];
    }

    private function assertUnchanged(array $before, string $why): void
    {
        $this->assertSame(json_encode($before), json_encode($this->world()), $why);
    }

    private function assertJournalConsistent(): void
    {
        $this->assertTrue(JournalService::trialBalance(now()->toDateString())['balanced']);
        $this->assertSame(0, DB::table('ledger_outbox')->whereNull('processed_at')->count(), 'no posting is left waiting');
        $dupes = DB::table('journal_entries')->where('idempotency_key', 'like', 'legacy:%')->groupBy('idempotency_key')->havingRaw('COUNT(*) > 1')->count();
        $this->assertSame(0, $dupes, 'no group was journalled twice');
    }

    private function legacy(): void
    {
        config(['inventory.confirmed_only' => false]);
        InventoryMode::reset();
    }

    // ---- 1. admin create-order (OrderController::store) ---------------------------------------------------

    private function adminOrder(int $qty)
    {
        $this->legacy();
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $p = $this->makeStockedProduct(['M' => 6], 1000, 400);
        Cart::create(['user_id' => $admin->id, 'product_id' => $p->id, 'variation' => 'M', 'quantity' => $qty, 'price' => 1000, 'tax' => 0, 'shipping_cost' => 0, 'discount' => 0, 'shipping_type' => 'home_delivery']);
        $address = Address::create(['user_id' => $admin->id, 'address' => 'House 1', 'phone' => '01700000001', 'postal_code' => '1200', 'country_id' => 18, 'state_id' => 0, 'city_id' => 0]);

        return [$p, fn () => $this->post(route('orders.store'), ['address_id' => $address->id, 'payment_option' => 'cash_on_delivery', 'shipping_cost_amount' => 0])];
    }

    public function test_admin_create_order_retries_a_deadlock_and_takes_stock_and_batches_exactly_once()
    {
        [$p, $place] = $this->adminOrder(2);
        $inj = DeadlockInjector::once('/^update\s+`?product_stocks`?\s+set/i');
        $place();
        $this->assertSame(1, $inj->thrown);
        $this->assertSame(1, Order::where('user_id', auth()->id())->count(), 'one order, not two');
        $this->assertSame(4, $this->stockQty($p, 'M'), 'two units taken once');
        $this->assertSame(4, $this->batchRemaining($p, 'M'), 'the cost layers fell by the same two units once');
        $this->assertSame(1, DB::table('order_details')->where('product_id', $p->id)->count());
    }

    public function test_admin_create_order_gives_up_after_three_attempts_and_leaves_nothing()
    {
        [$p, $place] = $this->adminOrder(2);
        $before = $this->world();
        $inj = DeadlockInjector::always('/^update\s+`?product_stocks`?\s+set/i');
        $place();
        $this->assertSame(3, $inj->thrown, 'exactly three attempts, not four, not nine');
        $this->assertUnchanged($before, 'no order, no stock movement, no layer movement, no balance change');
        $this->assertSame(6, $this->stockQty($p, 'M'));
    }

    // ---- 2. chat orders -------------------------------------------------------------------------------------------

    private function chatPlace(int $qty, string $key)
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('chat_conversations')) {
            $this->markTestSkipped('chat tables missing');
        }
        $a = \App\Models\ChatAccount::create(['platform' => 'facebook', 'external_id' => 'P' . uniqid(), 'name' => 'Page', 'status' => 'connected']);
        $conv = \App\Models\ChatConversation::create(['chat_account_id' => $a->id, 'platform' => 'facebook', 'external_id' => 'U' . uniqid(), 'customer_name' => 'Buyer']);
        $city = DB::table('cities')->insertGetId(['name' => 'Dhaka', 'state_id' => 1, 'cost' => 60, 'status' => 1]);
        $p = $this->makeStockedProduct(['M' => 6], 1000, 400);

        return [$p, fn () => app(\App\Services\Chat\ChatOrderService::class)->place($conv, $this->makeAdmin(), ['idempotency_key' => $key, 'items' => [['product_id' => $p->id, 'variant' => 'M', 'quantity' => $qty]], 'discount' => 0,
            'customer' => ['name' => 'Rahim', 'phone' => '01712345678', 'address' => 'House 1, Road 2', 'city_id' => $city]], '127.0.0.1')];
    }

    public function test_chat_order_retries_a_deadlock_without_duplicating_the_order_or_the_stock_take()
    {
        $this->legacy();
        [$p, $place] = $this->chatPlace(2, 'chat-key-' . uniqid());
        $inj = DeadlockInjector::once('/^insert into `order_details`/i');
        $combined = $place();
        $this->assertSame(1, $inj->thrown);
        $this->assertSame(1, DB::table('chat_conversation_orders')->where('combined_order_id', $combined->id)->count());
        $this->assertSame(1, Order::where('combined_order_id', $combined->id)->count());
        $this->assertSame(4, $this->stockQty($p, 'M'));
        $this->assertSame(4, $this->batchRemaining($p, 'M'));
        $this->assertSame(1, DB::table('order_details')->where('product_id', $p->id)->count());
    }

    public function test_chat_order_fails_cleanly_after_three_attempts()
    {
        $this->legacy();
        [$p, $place] = $this->chatPlace(2, 'chat-key-' . uniqid());
        $before = $this->world();
        $inj = DeadlockInjector::always('/^insert into `order_details`/i');
        try {
            $place();
            $this->fail('the deadlock must surface after the last attempt');
        } catch (\Throwable $e) {
            $this->assertTrue(\App\Support\DeadlockRetry::isDeadlock($e), 'the original deadlock surfaces: ' . $e->getMessage());
        }
        $this->assertSame(3, $inj->thrown);
        $this->assertUnchanged($before, 'nothing remains');
    }

    // ---- 3. courier settlement -----------------------------------------------------------------------------------

    private function settlementFixture(): array
    {
        $p = $this->productAtZero(['stock_policy' => 'backorder']);
        $order = $this->makeOrder([[$p, '', 5]], 'confirmed');
        $this->receive($p, '', 3, '100');
        $this->receive($p, '', 2, '120');
        $this->setStatus($order, 'picked_up');
        $cash = ChartOfAccount::create(['code' => 'S-CASH-' . uniqid(), 'name' => 'Settle cash ' . uniqid(), 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0])->id;
        foreach ([['Sales', 'income', 'sales'], ['Accounts Receivable', 'asset', 'receivable'], ['Courier Income', 'income', 'income'], ['Cost of Goods Sold', 'expense', 'cost_of_sales']] as [$n, $t, $s]) {
            ChartOfAccount::firstOrCreate(['name' => $n], ['code' => 'S-' . strtoupper(substr($n, 0, 4)) . uniqid(), 'type' => $t, 'sub_type' => $s, 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0]);
        }
        DB::table('orders')->where('id', $order->id)->update(['courier_status' => 'delivered', 'courier_name' => 'steadfast', 'due_amount' => 5000, 'grand_total' => 5000]);
        $this->forgetAuth();
        $this->actingAs($this->makeAdmin());

        return [$order, fn () => $this->postJson(route('courier-settlement.settle'), ['order_id' => $order->id, 'courier_fee' => 60, 'account_id' => $cash, 'courier_name' => 'steadfast', 'settlement_fee' => 0, 'due_amount' => 5000, 'settlement_charge' => 0])];
    }

    public function test_courier_settlement_retries_a_deadlock_and_books_cogs_inventory_and_journal_once()
    {
        [$order, $settle] = $this->settlementFixture();
        $before = $this->world();
        $inj = DeadlockInjector::once('/^update `chart_of_accounts`/i');
        $res = $settle();
        $this->assertTrue($res->json('success') ?? false, json_encode($res->json()));
        $this->assertSame(1, $inj->thrown);
        $this->assertSame(1, DB::table('courier_settlements')->where('order_id', $order->id)->count());
        $this->assertEquals(0.0, (float) DB::table('chart_of_accounts')->where('id', SystemAccounts::id('inventory'))->value('current_balance'), 'inventory credited by the 540 of FIFO cost once (540 - 540)');
        $this->assertEquals(540.0, (float) DB::table('chart_of_accounts')->where('sub_type', 'cost_of_sales')->sum('current_balance'), 'COGS 540 once, not 1080');
        $this->assertGreaterThanOrEqual(1, DB::table('ledger_outbox')->whereNotNull('processed_at')->count(), 'the settlement postings were journalled');
        $this->assertJournalConsistent();
    }

    public function test_courier_settlement_gives_up_after_three_attempts_and_the_ledger_is_untouched()
    {
        [$order, $settle] = $this->settlementFixture();
        $before = $this->world();
        $inj = DeadlockInjector::always('/^update `chart_of_accounts`/i');
        $res = $settle();
        $this->assertFalse($res->json('success') ?? true);
        $this->assertSame(3, $inj->thrown);
        $this->assertUnchanged($before, 'no settlement, no balance moved, no statement log, no outbox row, no journal entry');
    }

    // ---- 4. purchases, supplier payments, stock adjustments ----------------------------------------------------

    private function purchaseBody(int $productId, int $qty, array $extra = []): array
    {
        return array_merge(['op_key' => 'dl-' . uniqid(), 'supplier_id' => $this->supplierId, 'purchase_date' => now()->toDateString(), 'product_id' => [$productId], 'name' => ['x'], 'variant' => [''],
            'purchase_quantity' => [$qty], 'buying_unit_cost' => [20], 'total_cost' => [$qty * 20]], $extra);
    }

    public function test_legacy_purchase_retries_a_deadlock_once_with_one_upload_and_one_of_everything()
    {
        Storage::fake('public');
        $p = $this->productAtZero();
        $this->actingAs($this->makeAdmin());
        $cash = ChartOfAccount::create(['code' => 'DP-' . uniqid(), 'name' => 'DP cash ' . uniqid(), 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 1000, 'status' => 'active', 'is_deleted' => 0]);
        $inj = DeadlockInjector::once('/^insert into `purchase_products`/i');
        $res = $this->post(route('purchase.store'), $this->purchaseBody($p->id, 5, ['document' => UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf'), 'pay_by' => 'cash', 'payment_account_id' => $cash->id, 'paid_amount' => 40]), ['Accept' => 'application/json']);
        $res->assertOk()->assertJson(['success' => true]);
        $this->assertSame(1, $inj->thrown);
        $this->assertSame(1, DB::table('purchases')->where('supplier_id', $this->supplierId)->count(), 'one purchase');
        $this->assertSame(1, DB::table('purchase_products')->where('product_id', $p->id)->count(), 'one FIFO layer');
        $this->assertSame(5, $this->stockQty($p, ''), 'stock +5 once');
        $this->assertEquals(60.0, (float) DB::table('suppliers')->where('id', $this->supplierId)->value('due_amount'), 'supplier due once');
        $this->assertEquals(960.0, (float) $cash->fresh()->current_balance, 'the 40 payment left the cash account once');
        $this->assertCount(1, Storage::disk('public')->allFiles('purchases'), 'the upload was stored once');
        $this->assertJournalConsistent();
        $e = DB::table('journal_entries')->where('source_type', 'legacy:purchase.store')->get();
        $this->assertCount(1, $e, 'one journal entry for the purchase');
    }

    public function test_legacy_purchase_gives_up_after_three_attempts_and_removes_its_upload()
    {
        Storage::fake('public');
        $p = $this->productAtZero();
        $this->actingAs($this->makeAdmin());
        $before = $this->world();
        $inj = DeadlockInjector::always('/^insert into `purchase_products`/i');
        $res = $this->post(route('purchase.store'), $this->purchaseBody($p->id, 5, ['document' => UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf')]), ['Accept' => 'application/json']);
        $this->assertSame(3, $inj->thrown);
        $this->assertStringContainsString('Deadlock', (string) $res->getContent());
        $this->assertUnchanged($before, 'no purchase, layer, stock, supplier or ledger change');
        $this->assertSame([], Storage::disk('public')->allFiles('purchases'), 'no orphan upload');
        $this->assertSame(0, DB::table('idempotency_keys')->where('scope', 'legacy_purchase')->count(), 'the double-submit key was rolled back too, so the user can submit again');
    }

    public function test_purchase_update_keeps_the_old_document_until_the_update_commits()
    {
        Storage::fake('public');
        $p = $this->productAtZero();
        $this->actingAs($this->makeAdmin());
        $this->post(route('purchase.store'), $this->purchaseBody($p->id, 5, ['document' => UploadedFile::fake()->create('first.pdf', 10, 'application/pdf')]), ['Accept' => 'application/json'])->assertOk();
        $purchase = \App\Models\Purchase::where('supplier_id', $this->supplierId)->firstOrFail();
        $old = $purchase->document;
        $this->assertNotEmpty($old);
        $body = $this->purchaseBody($p->id, 5, ['document' => UploadedFile::fake()->create('second.pdf', 10, 'application/pdf')]);

        $inj = DeadlockInjector::always('/^update `suppliers`/i');
        $this->put(route('purchase.update', $purchase->id), $body, ['Accept' => 'application/json']);
        $this->assertSame(3, $inj->thrown);
        $this->assertTrue(Storage::disk('public')->exists($old), 'a failed update keeps the existing document');
        $this->assertCount(1, Storage::disk('public')->allFiles('purchases'), 'and leaves no orphan new one');
        $this->assertSame($old, $purchase->fresh()->document);
    }

    public function test_supplier_payment_retries_once_with_one_set_of_attachments()
    {
        Storage::fake('public');
        $p = $this->productAtZero();
        $this->actingAs($this->makeAdmin());
        $cash = ChartOfAccount::create(['code' => 'SPR-' . uniqid(), 'name' => 'SPR cash ' . uniqid(), 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 1000, 'status' => 'active', 'is_deleted' => 0]);
        $this->post(route('purchase.store'), $this->purchaseBody($p->id, 5), ['Accept' => 'application/json'])->assertOk();
        $purchaseId = (int) DB::table('purchases')->where('supplier_id', $this->supplierId)->value('id');
        $inj = DeadlockInjector::once('/^insert into `supplier_payment_purchases`/i');
        $res = $this->post(route('accounts.supplier-payments.store'), ['supplier_id' => $this->supplierId, 'payment_date' => now()->toDateString(), 'amount' => 70, 'payment_method' => 'CASH', 'account_head_id' => $cash->id,
            'allocations' => json_encode([['purchase_id' => $purchaseId, 'paid_amount' => 70]]), 'attachments' => [UploadedFile::fake()->create('slip.pdf', 10, 'application/pdf')]], ['Accept' => 'application/json']);
        $this->assertTrue($res->json('success') ?? false, json_encode($res->json()));
        $this->assertSame(1, $inj->thrown);
        $this->assertSame(1, DB::table('supplier_payments')->where('supplier_id', $this->supplierId)->count(), 'one payment record');
        $this->assertEquals(930.0, (float) $cash->fresh()->current_balance, 'paid once');
        $this->assertEquals(30.0, (float) DB::table('suppliers')->where('id', $this->supplierId)->value('due_amount'), 'supplier due reduced once');
        $this->assertCount(1, Storage::disk('public')->allFiles('supplier-payments'));
        $this->assertJournalConsistent();
    }

    public function test_stock_adjustment_retries_once_and_removes_the_units_from_stock_and_layers_once()
    {
        $this->legacy();
        $this->actingAs($this->makeAdmin());
        $p = $this->makeStockedProduct(['M' => 10], 1000, 400);
        $stockId = (int) DB::table('product_stocks')->where('product_id', $p->id)->where('variant', 'M')->value('id');
        $body = ['transaction_date' => now()->toDateString(), 'adjustment_type' => 'normal', 'products' => [['product_id' => $p->id, 'product_stock_id' => $stockId, 'variant' => 'M', 'quantity' => 3, 'unit_price' => 400]]];
        $inj = DeadlockInjector::once('/^update\s+`?product_stocks`?\s+set/i');
        $this->postJson(route('stock-adjustments.store'), $body)->assertOk();
        $this->assertSame(1, $inj->thrown);
        $this->assertSame(7, $this->stockQty($p, 'M'), 'three units written off once');
        $this->assertSame(7, $this->batchRemaining($p, 'M'));
        $this->assertSame(1, DB::table('stock_adjustments')->count());
    }

    public function test_stock_adjustment_gives_up_after_three_attempts_with_nothing_removed()
    {
        $this->legacy();
        $this->actingAs($this->makeAdmin());
        $p = $this->makeStockedProduct(['M' => 10], 1000, 400);
        $stockId = (int) DB::table('product_stocks')->where('product_id', $p->id)->where('variant', 'M')->value('id');
        $before = $this->world();
        $inj = DeadlockInjector::always('/^update\s+`?product_stocks`?\s+set/i');
        $this->postJson(route('stock-adjustments.store'), ['transaction_date' => now()->toDateString(), 'adjustment_type' => 'normal', 'products' => [['product_id' => $p->id, 'product_stock_id' => $stockId, 'variant' => 'M', 'quantity' => 3, 'unit_price' => 400]]]);
        $this->assertSame(3, $inj->thrown);
        $this->assertUnchanged($before, 'stock, layers and adjustments untouched');
    }
}
