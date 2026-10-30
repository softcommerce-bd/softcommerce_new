<?php

namespace Tests\Feature\Chat;

use App\Models\ChatAccount;
use App\Models\ChatConversation;
use App\Models\ChatConversationOrder;
use App\Models\CombinedOrder;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\ProductStock;
use App\Services\Chat\ChatOrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\Inventory\InventoryTestCase;

/**
 * Chat → order through the store's real stock ledger, price helpers and order tables, so it needs the
 * MySQL `*_test` database (see tests/create_test_database.php), like the funnel checkout tests.
 * NOT runnable on the SQLite harness used by the other chat tests.
 */
class ChatOrderTest extends InventoryTestCase
{
    private ChatConversation $conversation;
    private int $cityId;

    protected function setUp(): void
    {
        if (!@fsockopen((string) env('DB_HOST', '127.0.0.1'), (int) env('DB_PORT', 3306), $errno, $err, 1)) {
            $this->markTestSkipped('The MySQL test database is not reachable; these tests need the store schema.');
        }
        parent::setUp();
        if (!Schema::hasTable('chat_conversations')) {
            $this->artisan('migrate', ['--path' => 'database/migrations/unified_chat', '--force' => true]);
        }
        $this->cityId = DB::table('cities')->insertGetId(['name' => 'Dhaka', 'state_id' => 1, 'cost' => 60, 'status' => 1]);
        $a = ChatAccount::create(['platform' => 'facebook', 'external_id' => 'P' . uniqid(), 'name' => 'Page', 'status' => 'connected']);
        $this->conversation = ChatConversation::create(['chat_account_id' => $a->id, 'platform' => 'facebook', 'external_id' => 'U' . uniqid(), 'customer_name' => 'Buyer']);
    }

    private function input(array $items, array $over = []): array
    {
        return $over + [
            'idempotency_key' => 'key-' . uniqid(), 'items' => $items, 'discount' => 0,
            'customer' => ['name' => 'Rahim', 'phone' => '01712345678', 'address' => 'House 1, Road 2', 'city_id' => $this->cityId],
        ];
    }

    private function service(): ChatOrderService { return app(ChatOrderService::class); }

    public function test_order_uses_store_prices_takes_stock_and_links_the_conversation()
    {
        $staff = $this->makeAdmin();
        $p = $this->makeStockedProduct(['M' => 10], 1000, 400);
        $combined = $this->service()->place($this->conversation, $staff, $this->input([['product_id' => $p->id, 'variant' => 'M', 'quantity' => 3]]), '127.0.0.1');

        $order = Order::where('combined_order_id', $combined->id)->firstOrFail();
        $this->assertSame(['chat', 'cash_on_delivery', 'unpaid'], [$order->order_from, $order->payment_type, $order->payment_status]);
        $detail = OrderDetail::where('order_id', $order->id)->firstOrFail();
        $this->assertSame(3, (int) $detail->quantity);
        $this->assertEquals(3000.0, (float) $detail->price, 'price comes from the product, not the browser');
        $this->assertSame(7, (int) ProductStock::where('product_id', $p->id)->where('variant', 'M')->value('qty'));
        $this->assertEquals(1200.0, (float) $detail->buying_price * 3, 'FIFO cost taken from purchase batches');
        $this->assertDatabaseHas('chat_conversation_orders', ['chat_conversation_id' => $this->conversation->id, 'combined_order_id' => $combined->id]);
        $this->assertNotNull($this->conversation->fresh()->user_id);
    }

    public function test_same_idempotency_key_returns_the_same_order_and_takes_stock_once()
    {
        $staff = $this->makeAdmin();
        $p = $this->makeStockedProduct(['M' => 10], 1000, 400);
        $in = $this->input([['product_id' => $p->id, 'variant' => 'M', 'quantity' => 2]]);
        $a = $this->service()->place($this->conversation, $staff, $in, '127.0.0.1');
        $b = $this->service()->place($this->conversation, $staff, $in, '127.0.0.1');
        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, CombinedOrder::where('id', $a->id)->count());
        $this->assertSame(8, (int) ProductStock::where('product_id', $p->id)->value('qty'));
        $this->assertSame(1, ChatConversationOrder::count());
    }

    public function test_insufficient_stock_refuses_the_whole_order_and_leaves_nothing_behind()
    {
        $staff = $this->makeAdmin();
        $ok = $this->makeStockedProduct(['M' => 10], 1000, 400);
        $low = $this->makeStockedProduct(['M' => 1], 500, 200);
        $before = Order::count();
        try {
            $this->service()->place($this->conversation, $staff, $this->input([['product_id' => $ok->id, 'variant' => 'M', 'quantity' => 2], ['product_id' => $low->id, 'variant' => 'M', 'quantity' => 5]]), '127.0.0.1');
            $this->fail('expected a refusal');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('stock', $e->getMessage());
        }
        $this->assertSame($before, Order::count());
        $this->assertSame(10, (int) ProductStock::where('product_id', $ok->id)->value('qty'), 'the line that could be served was not half-taken');
        $this->assertSame(0, ChatConversationOrder::count());
    }

    public function test_unpublished_product_and_bad_variant_are_refused()
    {
        $staff = $this->makeAdmin();
        $p = $this->makeStockedProduct(['M' => 5], 1000, 400);
        $hidden = $this->makeStockedProduct(['M' => 5], 1000, 400, ['published' => 0]);
        foreach ([[['product_id' => $hidden->id, 'variant' => 'M', 'quantity' => 1]], [['product_id' => $p->id, 'variant' => 'XL', 'quantity' => 1]], [['product_id' => $p->id, 'variant' => 'M', 'quantity' => 0]]] as $items) {
            try {
                $this->service()->place($this->conversation, $staff, $this->input($items), '127.0.0.1');
                $this->fail('expected a refusal');
            } catch (InvalidArgumentException $e) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_discount_is_capped_by_role_but_super_admin_may_go_further()
    {
        config(['unified_chat.max_discount_percent' => 10]);
        $agent = $this->makeUser('staff', ['create_order_from_chat']);
        $p = $this->makeStockedProduct(['M' => 10], 1000, 400);
        $items = [['product_id' => $p->id, 'variant' => 'M', 'quantity' => 1]];
        try {
            $this->service()->place($this->conversation, $agent, $this->input($items, ['discount' => 500]), '127.0.0.1');
            $this->fail('a 50% discount must be refused for this role');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('10%', $e->getMessage());
        }
        $combined = $this->service()->place($this->conversation, $this->makeAdmin(), $this->input($items, ['discount' => 500]), '127.0.0.1');
        $this->assertEquals(500.0, (float) Order::where('combined_order_id', $combined->id)->value('coupon_discount'));
    }

    public function test_the_order_endpoint_is_permission_and_account_gated()
    {
        $p = $this->makeStockedProduct(['M' => 10], 1000, 400);
        $body = $this->input([['product_id' => $p->id, 'variant' => 'M', 'quantity' => 1]]);
        $noPerm = $this->makeUser('staff', ['view_unified_chat', 'view_all_chats']);
        $withPerm = $this->makeUser('staff', ['view_unified_chat', 'view_all_chats', 'create_order_from_chat']);
        $this->conversation->account->staff()->sync([$noPerm->id, $withPerm->id]);
        $url = "/admin/unified-chat/api/conversations/{$this->conversation->id}/orders";
        $this->actingAs($noPerm)->postJson($url, $body)->assertStatus(404);
        $this->forgetAuth();
        $this->actingAs($withPerm)->postJson($url, $body)->assertStatus(201);
        $this->assertSame(1, ChatConversationOrder::count());
    }

    public function test_totals_are_consistent_with_variation_price_product_discount_and_tax()
    {
        $staff = $this->makeAdmin();
        // Variation L costs more than M; product-level 10% discount; 5% tax: the store's own price helpers decide.
        $p = $this->makeProduct(['unit_price' => 1000, 'discount' => 10, 'discount_type' => 'percent', 'variant_product' => 1], [
            ['variant' => 'M', 'qty' => 10, 'price' => 1000], ['variant' => 'L', 'qty' => 10, 'price' => 1200],
        ]);
        $this->makeBatch($p, 'M', 10, 400);
        $this->makeBatch($p, 'L', 10, 500);
        DB::table('product_taxes')->insert(['product_id' => $p->id, 'tax_id' => 1, 'tax' => 5, 'tax_type' => 'percent']);

        $combined = $this->service()->place($this->conversation, $staff, $this->input([
            ['product_id' => $p->id, 'variant' => 'M', 'quantity' => 2], ['product_id' => $p->id, 'variant' => 'L', 'quantity' => 1],
        ], ['discount' => 100]), '127.0.0.1');

        $order = Order::where('combined_order_id', $combined->id)->firstOrFail();
        $d = OrderDetail::where('order_id', $order->id)->orderBy('id')->get();
        $this->assertEquals([1800.0, 1080.0], [(float) $d[0]->price, (float) $d[1]->price], 'M: 2 x (1000 - 10%), L: 1 x (1200 - 10%)');
        $this->assertEquals([90.0, 54.0], [(float) $d[0]->tax, (float) $d[1]->tax], '5% of the discounted price');
        $expected = $d->sum('price') + $d->sum('tax') + $d->sum('shipping_cost') - 100;
        $this->assertEquals(round($expected, 2), (float) $order->grand_total);
        $this->assertEquals((float) $order->grand_total, (float) $combined->fresh()->grand_total);
        $this->assertEquals((float) $order->grand_total, (float) $order->due_amount);
        $this->assertSame(8, $this->stockQty($p, 'M'));
        $this->assertSame(9, $this->stockQty($p, 'L'));
        $this->assertSame(8, $this->batchRemaining($p, 'M'), 'FIFO batch units follow the stock');
    }

    public function test_placement_posts_no_accounting_entries_like_website_and_funnel_orders_and_stock_returns_through_the_ledger()
    {
        $staff = $this->makeAdmin();
        $p = $this->makeStockedProduct(['M' => 10], 1000, 400);
        $logs = DB::table('account_statement_logs')->count();
        $balances = DB::table('chart_of_accounts')->sum('current_balance');

        $combined = $this->service()->place($this->conversation, $staff, $this->input([['product_id' => $p->id, 'variant' => 'M', 'quantity' => 4]]), '127.0.0.1');
        $this->assertSame($logs, DB::table('account_statement_logs')->count());
        $this->assertEquals($balances, DB::table('chart_of_accounts')->sum('current_balance'));
        $this->assertNotSame('pos', Order::where('combined_order_id', $combined->id)->value('order_from'), 'not treated as already-accounted POS: courier settlement books it');

        // The existing cancellation path gives units back to the shelf and to their cost batches, once.
        $detail = OrderDetail::whereIn('order_id', Order::where('combined_order_id', $combined->id)->pluck('id'))->firstOrFail();
        $this->assertTrue(app(\App\Services\Inventory\StockLedger::class)->give($detail, true));
        $this->assertSame(10, $this->stockQty($p, 'M'));
        $this->assertSame(10, $this->batchRemaining($p, 'M'));
        $this->assertFalse(app(\App\Services\Inventory\StockLedger::class)->give($detail, true), 'a second give changes nothing');
        $this->assertSame(10, $this->stockQty($p, 'M'));
    }

    public function test_concurrent_style_double_submit_with_different_keys_cannot_oversell_the_last_unit()
    {
        $staff = $this->makeAdmin();
        $p = $this->makeStockedProduct(['M' => 1], 1000, 400);
        $items = [['product_id' => $p->id, 'variant' => 'M', 'quantity' => 1]];
        $this->service()->place($this->conversation, $staff, $this->input($items), '127.0.0.1');
        $this->expectException(InvalidArgumentException::class);
        try {
            $this->service()->place($this->conversation, $staff, $this->input($items), '127.0.0.1');
        } finally {
            $this->assertSame(0, $this->stockQty($p, 'M'));
            $this->assertSame(1, ChatConversationOrder::count());
        }
    }
}
