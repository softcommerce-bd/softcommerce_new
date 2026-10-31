<?php

namespace Tests\Concurrency;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** T-5: real parallel chat placements (committed rows, separate processes). Stock and FIFO layers must stay in step; one idempotency key is one order. */
class ChatOrderConcurrencyTest extends ConcurrencyTestCase
{
    private const EXTRA = ['chat_conversation_orders', 'chat_conversations', 'chat_accounts', 'account_statement_logs', 'stock_movements', 'idempotency_keys'];
    private array $high = [];
    private int $staff;
    private int $conversation;
    private int $city;

    protected function setUp(): void
    {
        parent::setUp();
        if (!Schema::hasTable('chat_conversations')) {
            $this->markTestSkipped('chat tables missing');
        }
        foreach (self::EXTRA as $t) {
            $this->high[$t] = Schema::hasTable($t) ? (int) DB::table($t)->max('id') : 0;
        }
        $this->staff = (int) DB::table('users')->insertGetId(['name' => 'chat staff', 'email' => uniqid() . '@c.test', 'password' => 'x', 'user_type' => 'admin', 'created_at' => now(), 'updated_at' => now()]);
        $account = \App\Models\ChatAccount::create(['platform' => 'facebook', 'external_id' => 'P' . uniqid(), 'name' => 'Page', 'status' => 'connected']);
        $this->conversation = \App\Models\ChatConversation::create(['chat_account_id' => $account->id, 'platform' => 'facebook', 'external_id' => 'U' . uniqid(), 'customer_name' => 'Buyer'])->id;
        $this->city = (int) DB::table('cities')->insertGetId(['name' => 'Dhaka', 'state_id' => 1, 'cost' => 60, 'status' => 1]);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse(self::EXTRA) as $t) {
            if (Schema::hasTable($t)) {
                DB::table($t)->where('id', '>', $this->high[$t] ?? PHP_INT_MAX)->delete();
            }
        }
        parent::tearDown();
    }

    private function job(int $product, int $qty, string $key): array
    {
        return ['action' => 'chat_place', 'conversation_id' => $this->conversation, 'user_id' => $this->staff, 'input' => [
            'idempotency_key' => $key, 'items' => [['product_id' => $product, 'variant' => 'M', 'quantity' => $qty]], 'discount' => 0,
            'customer' => ['name' => 'Rahim', 'phone' => '01712345678', 'address' => 'House 1, Road 2', 'city_id' => $this->city]]];
    }

    public function test_the_same_idempotency_key_sent_in_parallel_makes_one_order_and_takes_stock_once()
    {
        $product = $this->seedProduct(['M' => 10], 1000, 400);
        $key = 'chat-par-' . uniqid();
        $results = $this->race(array_fill(0, 4, $this->job($product, 2, $key)), 120);

        $ids = array_unique(array_filter(array_column($results, 'combined_order_id')));
        $this->assertNotEmpty($ids, 'at least one placement succeeded: ' . json_encode($results));
        $this->assertCount(1, $ids, 'every successful caller got the same order: ' . json_encode($results));
        $this->assertSame(1, DB::table('chat_conversation_orders')->count() - $this->high['chat_conversation_orders'], 'one conversation link');
        $this->assertSame(8, (int) DB::table('product_stocks')->where('product_id', $product)->value('qty'), 'stock taken once (10 - 2)');
        $this->assertSame(8, (int) DB::table('purchase_products')->where('product_id', $product)->sum('remaining_qty'), 'FIFO layers taken once, in step with the shelf');
        $this->assertSame(1, DB::table('order_details')->where('product_id', $product)->count(), 'one order line');
    }

    public function test_different_keys_in_parallel_never_oversell_and_layers_match_the_shelf()
    {
        $product = $this->seedProduct(['M' => 6], 1000, 400);
        $results = $this->race(array_map(fn ($i) => $this->job($product, 2, "chat-diff-{$i}-" . uniqid()), range(1, 5)), 120);

        $won = count(array_filter($results, fn ($r) => !empty($r['ok'])));
        $this->assertSame(3, $won, 'six units, two each: exactly three orders fit: ' . json_encode($results));
        $this->assertSame(0, (int) DB::table('product_stocks')->where('product_id', $product)->value('qty'));
        $this->assertSame(0, (int) DB::table('purchase_products')->where('product_id', $product)->sum('remaining_qty'));
        $this->assertSame(3, DB::table('order_details')->where('product_id', $product)->count(), 'no line for a refused placement');
        $this->assertSame(6, (int) DB::table('order_details')->where('product_id', $product)->sum('quantity'));
        foreach ($results as $r) {
            if (empty($r['ok'])) {
                $this->assertStringNotContainsString('Deadlock', json_encode($r['error'] ?? ''), 'a refusal is out-of-stock, never a leaked deadlock: ' . json_encode($r));
            }
        }
    }
}
