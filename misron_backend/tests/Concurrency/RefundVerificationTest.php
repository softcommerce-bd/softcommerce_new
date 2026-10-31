<?php

namespace Tests\Concurrency;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Services\Accounting\JournalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** B. Refund accounting under real parallel processes. */
class RefundVerificationTest extends VerificationCase
{
    private array $makeAdmins = [];

    protected function tearDown(): void
    {
        foreach ($this->makeAdmins as $id) {
            DB::table('model_has_roles')->where('model_id', $id)->where('model_type', \App\Models\User::class)->delete();
        }
        parent::tearDown();
    }

    private function admin(): int
    {
        $id = DB::table('users')->insertGetId(['name' => 'RA ' . Str::random(4), 'email' => Str::random(10) . '@ra.test', 'password' => 'x', 'user_type' => 'admin', 'balance' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $role = \Spatie\Permission\Models\Role::findByName('Super Admin', 'web');
        \App\Models\User::find($id)->assignRole($role);
        $this->makeAdmins[] = $id;

        return $id;
    }

    /** a committed customer order line sold for $price, and a pending refund request for $refund */
    private function fixture(float $price = 1000, float $refund = 300): array
    {
        $user = DB::table('users')->insertGetId(['name' => 'cust', 'email' => Str::random(8) . '@c.test', 'password' => 'x', 'user_type' => 'customer', 'balance' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $o = new Order();
        $o->forceFill(['user_id' => $user, 'seller_id' => 1, 'code' => Order::generateUniqueCode(), 'delivery_status' => 'delivered', 'payment_status' => 'paid', 'payment_type' => 'cash_on_delivery', 'shipping_address' => json_encode(['name' => 'x']), 'grand_total' => $price, 'date' => time(), 'order_from' => 'app'])->save();
        $d = new OrderDetail();
        $d->forceFill(['order_id' => $o->id, 'seller_id' => 1, 'product_id' => 1, 'variation' => '', 'quantity' => 1, 'price' => $price, 'selling_price' => $price, 'tax' => 0, 'shipping_cost' => 0, 'buying_price' => 0, 'profit' => 0, 'delivery_status' => 'delivered'])->save();
        $req = DB::table('refund_requests')->insertGetId(['user_id' => $user, 'order_id' => $o->id, 'order_detail_id' => $d->id, 'seller_id' => 1, 'seller_approval' => 0, 'admin_approval' => 0, 'refund_amount' => $refund, 'reason' => 'r', 'admin_seen' => 0, 'refund_status' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return ['user' => $user, 'order' => $o->id, 'line' => $d->id, 'request' => $req];
    }

    private function liability(): float
    {
        return (float) DB::table('chart_of_accounts')->where('id', \App\Services\Accounting\SystemAccounts::id('customer_wallet'))->value('current_balance');
    }

    public function test_two_administrators_paying_the_same_refund_at_once_credit_the_customer_exactly_once()
    {
        $f = $this->fixture();
        $a1 = $this->admin(); $a2 = $this->admin();
        $walletBefore = $this->liability();
        $results = $this->race([['action' => 'refund_pay_as', 'admin_id' => $a1, 'refund_id' => $f['request']], ['action' => 'refund_pay_as', 'admin_id' => $a2, 'refund_id' => $f['request']]], 60);
        foreach ($results as $r) {
            $this->assertTrue(!empty($r['ok']), 'a worker crashed: ' . json_encode($r));
        }
        $this->assertSame(1, DB::table('wallets')->where('user_id', $f['user'])->where('payment_method', 'Refund')->count(), 'one wallet credit');
        $this->assertEquals(300.0, (float) DB::table('users')->where('id', $f['user'])->value('balance'), 'balance credited once');
        $this->assertSame(1, DB::table('refund_postings')->where('refund_request_id', $f['request'])->count());
        $this->assertEquals($walletBefore + 300, $this->liability(), 'the wallet liability rose by one refund');
        $this->assertSame(1, (int) DB::table('refund_requests')->where('id', $f['request'])->value('refund_status'));
        $this->assertTrue(JournalService::trialBalance(now()->toDateString())['balanced']);
    }

    public function test_the_same_refund_request_or_gateway_reference_from_two_processes_posts_once()
    {
        $f = $this->fixture();
        $r = $this->race([
            ['action' => 'refund_service', 'key' => 'k-a-' . Str::random(4), 'method' => 'wallet', 'amount' => 100, 'order_id' => $f['order'], 'order_detail_id' => $f['line'], 'refund_request_id' => $f['request']],
            ['action' => 'refund_service', 'key' => 'k-b-' . Str::random(4), 'method' => 'wallet', 'amount' => 100, 'order_id' => $f['order'], 'order_detail_id' => $f['line'], 'refund_request_id' => $f['request']],
        ], 60);
        $this->assertSame(1, count(array_filter($r, fn ($x) => !empty($x['ok']))), 'exactly one of the two succeeds: ' . json_encode($r));
        $this->assertSame(1, DB::table('refund_postings')->where('refund_request_id', $f['request'])->count());

        $ref = 'GW-' . Str::random(6);
        $g = $this->race([
            ['action' => 'refund_service', 'key' => 'g-a-' . Str::random(4), 'method' => 'gateway', 'amount' => 50, 'order_id' => $f['order'], 'order_detail_id' => $f['line'], 'external_ref' => $ref],
            ['action' => 'refund_service', 'key' => 'g-b-' . Str::random(4), 'method' => 'gateway', 'amount' => 50, 'order_id' => $f['order'], 'order_detail_id' => $f['line'], 'external_ref' => $ref],
        ], 60);
        $this->assertSame(1, count(array_filter($g, fn ($x) => !empty($x['ok']))), json_encode($g));
        $this->assertSame(1, DB::table('refund_postings')->where('external_ref', $ref)->count());
    }

    public function test_parallel_partial_refunds_against_one_line_never_exceed_the_amount_sold()
    {
        $f = $this->fixture(1000);
        $acct = $this->account('asset', 'bank', 5000);
        $jobs = [];
        foreach (range(1, 6) as $i) { // 6 x 300 = 1800 against a 1000 line, mixed methods
            $m = ['wallet', 'cash', 'gateway'][$i % 3];
            $jobs[] = ['action' => 'refund_service', 'key' => "p-{$i}-" . Str::random(4), 'method' => $m, 'amount' => 300, 'order_id' => $f['order'], 'order_detail_id' => $f['line'], 'account_id' => $m === 'cash' ? $acct : null, 'external_ref' => $m === 'gateway' ? "GWP-{$i}-" . Str::random(4) : null];
        }
        $r = $this->race($jobs, 60);
        $ok = count(array_filter($r, fn ($x) => !empty($x['ok'])));
        $total = (float) DB::table('refund_postings')->where('order_detail_id', $f['line'])->sum('amount');
        $this->assertSame(3, $ok, 'only three 300s fit into 1000: ' . json_encode($r));
        $this->assertEquals(900.0, $total);
        $this->assertLessThanOrEqual(1000.0, $total);
        foreach (array_filter($r, fn ($x) => empty($x['ok'])) as $x) {
            $this->assertStringContainsString('exceed', json_encode($x['error'] ?? ''), 'refused for the right reason');
        }
        $this->assertSame((int) DB::table('refund_postings')->where('order_detail_id', $f['line'])->count(), DB::table('refund_postings')->where('order_detail_id', $f['line'])->whereNotNull('journal_entry_id')->count());
        $this->assertTrue(JournalService::trialBalance(now()->toDateString())['balanced']);
    }
}
