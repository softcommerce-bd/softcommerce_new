<?php

namespace Tests\Feature\Inventory;

use App\Models\Address;
use App\Models\Cart;
use App\Models\ChartOfAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * T-5: a request that deadlocks once and then succeeds must cause exactly the external effects of a request that never deadlocked:
 * the same outgoing HTTP calls, mail, notifications, queued jobs, application events and stored files.
 * (Calls that bypass the framework's fakes, e.g. raw curl, cannot be counted here; none is made on these paths.)
 */
class DeadlockSideEffectTest extends InventoryV2TestCase
{
    private array $events = [];

    private function arm(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        Notification::fake();
        Mail::fake();
        Queue::fake();
        Bus::fake();
        Storage::fake('public');
        $this->events = [];
        Event::listen('*', function ($name) {
            if (str_starts_with($name, 'App\\') || str_starts_with($name, 'order.') || str_starts_with($name, 'purchase.')) {
                $this->events[] = $name;
            }
        });
    }

    /** a comparable summary of everything that left the application */
    private function effects(): array
    {
        $events = $this->events;
        sort($events);

        return [
            'http' => Http::recorded()->count(),
            'mail' => count(Mail::sent(\Illuminate\Mail\Mailable::class)),
            'queued' => count(Queue::pushedJobs()) ,
            'bus' => count(Bus::dispatched(\Illuminate\Contracts\Queue\ShouldQueue::class)),
            'events' => array_count_values($events),
            'files' => count(Storage::disk('public')->allFiles()),
        ];
    }

    private function runTwice(callable $scenario, string $regex): array
    {
        $this->arm();
        $scenario();
        $clean = $this->effects();

        $this->arm();
        $inj = DeadlockInjector::once($regex);
        $scenario();
        $this->assertSame(1, $inj->thrown, 'the deadlock really happened');

        return [$clean, $this->effects()];
    }

    public function test_admin_create_order_causes_the_same_external_effects_with_or_without_a_deadlock()
    {
        config(['inventory.confirmed_only' => false]);
        \App\Services\Inventory\InventoryMode::reset();
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $place = function () use ($admin) {
            $p = $this->makeStockedProduct(['M' => 6], 1000, 400);
            Cart::where('user_id', $admin->id)->delete();
            Cart::create(['user_id' => $admin->id, 'product_id' => $p->id, 'variation' => 'M', 'quantity' => 2, 'price' => 1000, 'tax' => 0, 'shipping_cost' => 0, 'discount' => 0, 'shipping_type' => 'home_delivery']);
            $address = Address::create(['user_id' => $admin->id, 'address' => 'House 1', 'phone' => '01700000001', 'postal_code' => '1200', 'country_id' => 18, 'state_id' => 0, 'city_id' => 0]);
            $this->post(route('orders.store'), ['address_id' => $address->id, 'payment_option' => 'cash_on_delivery', 'shipping_cost_amount' => 0]);
            DB::table('combined_orders')->where('user_id', $admin->id)->where('created_at', '>=', now()->subSeconds(15))->update(['created_at' => now()->subMinute()]); // so the second scenario is a new order, not a resubmit
        };
        [$clean, $retried] = $this->runTwice($place, '/^update\s+`?product_stocks`?\s+set/i');
        $this->assertSame($clean, $retried);
    }

    public function test_legacy_purchase_stores_its_document_once_and_makes_the_same_external_effects()
    {
        $this->actingAs($this->makeAdmin());
        $p = $this->productAtZero();
        $scenario = function () use ($p) {
            $this->post(route('purchase.store'), ['op_key' => 'se-' . uniqid(), 'supplier_id' => $this->supplierId, 'purchase_date' => now()->toDateString(), 'product_id' => [$p->id], 'name' => ['x'], 'variant' => [''],
                'purchase_quantity' => [5], 'buying_unit_cost' => [20], 'total_cost' => [100], 'document' => UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        };
        [$clean, $retried] = $this->runTwice($scenario, '/^insert into `purchase_products`/i');
        $this->assertSame(1, $clean['files']);
        $this->assertSame($clean, $retried, 'one file, same calls');
    }

    public function test_supplier_payment_causes_the_same_external_effects_and_stores_its_attachment_once()
    {
        $this->actingAs($this->makeAdmin());
        $p = $this->productAtZero();
        $cash = ChartOfAccount::create(['code' => 'SE-' . uniqid(), 'name' => 'SE cash ' . uniqid(), 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 1000, 'status' => 'active', 'is_deleted' => 0]);
        $this->post(route('purchase.store'), ['op_key' => 'se-' . uniqid(), 'supplier_id' => $this->supplierId, 'purchase_date' => now()->toDateString(), 'product_id' => [$p->id], 'name' => ['x'], 'variant' => [''],
            'purchase_quantity' => [20], 'buying_unit_cost' => [20], 'total_cost' => [400]], ['Accept' => 'application/json'])->assertOk();
        $purchaseId = (int) DB::table('purchases')->where('supplier_id', $this->supplierId)->value('id');
        $scenario = function () use ($purchaseId, $cash) {
            $this->post(route('accounts.supplier-payments.store'), ['supplier_id' => $this->supplierId, 'payment_date' => now()->toDateString(), 'amount' => 70, 'payment_method' => 'CASH', 'account_head_id' => $cash->id,
                'allocations' => json_encode([['purchase_id' => $purchaseId, 'paid_amount' => 70]]), 'attachments' => [UploadedFile::fake()->create('slip.pdf', 10, 'application/pdf')]], ['Accept' => 'application/json'])->assertOk();
        };
        [$clean, $retried] = $this->runTwice($scenario, '/^insert into `supplier_payment_purchases`/i');
        $this->assertSame(1, $clean['files']);
        $this->assertSame($clean, $retried);
    }

    public function test_chat_order_causes_the_same_external_effects_with_or_without_a_deadlock()
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('chat_conversations')) {
            $this->markTestSkipped('chat tables missing');
        }
        config(['inventory.confirmed_only' => false]);
        \App\Services\Inventory\InventoryMode::reset();
        $a = \App\Models\ChatAccount::create(['platform' => 'facebook', 'external_id' => 'P' . uniqid(), 'name' => 'Page', 'status' => 'connected']);
        $conv = \App\Models\ChatConversation::create(['chat_account_id' => $a->id, 'platform' => 'facebook', 'external_id' => 'U' . uniqid(), 'customer_name' => 'Buyer']);
        $city = DB::table('cities')->insertGetId(['name' => 'Dhaka', 'state_id' => 1, 'cost' => 60, 'status' => 1]);
        $staff = $this->makeAdmin();
        $scenario = function () use ($conv, $city, $staff) {
            $p = $this->makeStockedProduct(['M' => 6], 1000, 400);
            app(\App\Services\Chat\ChatOrderService::class)->place($conv, $staff, ['idempotency_key' => 'se-' . uniqid(), 'items' => [['product_id' => $p->id, 'variant' => 'M', 'quantity' => 2]], 'discount' => 0,
                'customer' => ['name' => 'Rahim', 'phone' => '01712345678', 'address' => 'House 1, Road 2', 'city_id' => $city]], '127.0.0.1');
        };
        [$clean, $retried] = $this->runTwice($scenario, '/^update\s+`?product_stocks`?\s+set/i');
        $this->assertSame($clean, $retried);
    }

    public function test_courier_settlement_makes_no_external_call_and_the_same_effects_with_a_deadlock()
    {
        $p = $this->productAtZero(['stock_policy' => 'backorder']);
        $make = function () use ($p) {
            $order = $this->makeOrder([[$p, '', 5]], 'confirmed');
            $this->receive($p, '', 5, '100');
            $this->setStatus($order, 'picked_up');
            DB::table('orders')->where('id', $order->id)->update(['courier_status' => 'delivered', 'courier_name' => 'steadfast', 'due_amount' => 5000, 'grand_total' => 5000]);

            return $order;
        };
        $cash = ChartOfAccount::create(['code' => 'SEC-' . uniqid(), 'name' => 'SEC cash ' . uniqid(), 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0])->id;
        foreach ([['Sales', 'income', 'sales'], ['Accounts Receivable', 'asset', 'receivable'], ['Courier Income', 'income', 'income'], ['Cost of Goods Sold', 'expense', 'cost_of_sales']] as [$n, $t, $s]) {
            ChartOfAccount::firstOrCreate(['name' => $n], ['code' => 'SE-' . strtoupper(substr($n, 0, 4)) . uniqid(), 'type' => $t, 'sub_type' => $s, 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0]);
        }
        $this->forgetAuth();
        $this->actingAs($this->makeAdmin());
        $scenario = function () use ($make, $cash) {
            $order = $make();
            $this->postJson(route('courier-settlement.settle'), ['order_id' => $order->id, 'courier_fee' => 60, 'account_id' => $cash, 'courier_name' => 'steadfast', 'settlement_fee' => 0, 'due_amount' => 5000, 'settlement_charge' => 0])->assertOk();
        };
        [$clean, $retried] = $this->runTwice($scenario, '/^update `chart_of_accounts`/i');
        $this->assertSame(0, $clean['http'], 'settlement makes no outgoing call');
        $this->assertSame($clean, $retried);
    }

    public function test_the_detector_would_see_a_repeated_external_call_positive_control()
    {
        $this->arm();
        $inj = DeadlockInjector::once('/^update\s+`?chart_of_accounts`?/i');
        $this->actingAs($this->makeAdmin());
        $acc = ChartOfAccount::create(['code' => 'PC-' . uniqid(), 'name' => 'PC ' . uniqid(), 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0]);
        // a (badly written) retried unit of work that calls out from INSIDE the transaction
        \App\Support\DeadlockRetry::$baseLevel = DB::transactionLevel();
        try {
        \App\Support\DeadlockRetry::run(function () use ($acc) {
            return DB::transaction(function () use ($acc) {
                Http::post('https://courier.example/api', ['x' => 1]);
                $acc->update(['current_balance' => 5]);
            });
        });
        } finally {
            \App\Support\DeadlockRetry::$baseLevel = 0;
        }
        $this->assertSame(1, $inj->thrown);
        $this->assertSame(2, $this->effects()['http'], 'the harness counts the repeated call, so a zero elsewhere means no call was made');
    }

    /** The retried controller methods contain no outgoing call, mail, notification, SMS, queue dispatch or event of their own (one level: helpers they call are covered by the tests above). */
    public function test_retried_controller_methods_contain_no_external_call_of_their_own()
    {
        $forbidden = '/Http::|curl_init|new\s+Client\(|Mail::|Notification::|->notify\(|NotificationUtility|SmsUtility|send_sms|sendSMS|event\(|dispatch\(|Queue::|SteadFast|Pathao|Redx|CourierService|file_get_contents\(\s*[\'"]http/i';
        $hits = [];
        $scanned = 0;
        foreach (config('deadlock_retry.controllers') as $class => $methods) {
            $ref = new \ReflectionClass($class);
            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
                if ($m->class !== $class || ($methods !== '*' && !in_array($m->name, $methods, true))) {
                    continue;
                }
                $scanned++;
                $code = implode('', array_slice(file($m->getFileName()), $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1));
                $code = preg_replace('#^\s*//.*$#m', '', $code); // commented-out code does not run
                if (preg_match($forbidden, $code, $found)) {
                    $hits[] = $ref->getShortName() . '@' . $m->name . ' -> ' . $found[0];
                }
            }
        }
        $this->assertGreaterThan(30, $scanned, 'the audit really looked at the configured methods');
        $this->assertSame([], $hits, 'a retried method calls out of the application: move the call after the commit or take the method out of config/deadlock_retry.php');
    }
}
