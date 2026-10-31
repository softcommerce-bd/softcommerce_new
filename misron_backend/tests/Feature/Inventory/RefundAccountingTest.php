<?php

namespace Tests\Feature\Inventory;

use App\Models\ChartOfAccount;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\RefundService;
use App\Services\Accounting\SystemAccounts;
use App\Services\Inventory\AccountingReconciliation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Wallet, cash and gateway refunds: one correct entry each, never twice. */
class RefundAccountingTest extends InventoryV2TestCase
{
    private function refundRequest(float $amount = 300.0): array
    {
        $p = $this->productAtZero(['unit_price' => 1000]);
        $order = $this->makeOrder([[$p, '', 1]], 'pending');
        $line = $this->detail($order);
        $line->forceFill(['price' => 1000, 'tax' => 0])->save();
        $id = DB::table('refund_requests')->insertGetId(['user_id' => $order->user_id, 'order_id' => $order->id, 'order_detail_id' => $line->id, 'seller_id' => 1, 'seller_approval' => 0, 'admin_approval' => 0,
            'refund_amount' => $amount, 'reason' => 'damaged', 'admin_seen' => 0, 'refund_status' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return [$id, $order, $line];
    }

    private function bal(string $key): float
    {
        return (float) DB::table('chart_of_accounts')->where('id', SystemAccounts::id($key))->value('current_balance');
    }

    public function test_paying_a_refund_to_the_wallet_credits_the_customer_once_and_posts_one_balanced_entry()
    {
        [$id, $order] = $this->refundRequest(300);
        $this->actingAs($this->makeAdmin());
        $wallets = DB::table('wallets')->count();
        $this->post(route('refund_request_money_by_admin'), ['refund_id' => $id])->assertRedirect();
        $this->assertSame($wallets + 1, DB::table('wallets')->count());
        $this->assertEquals(300.0, (float) DB::table('users')->where('id', $order->user_id)->value('balance'));
        $this->assertEquals(300.0, $this->bal('sales_returns'), 'Dr Sales Returns and Refunds');
        $this->assertEquals(300.0, $this->bal('customer_wallet'), 'Cr Customer Wallet Balances (we owe the customer)');
        $this->assertSame(1, DB::table('refund_postings')->where('refund_request_id', $id)->count());
        $this->assertTrue(JournalService::trialBalance(now()->toDateString())['balanced']);
    }

    public function test_a_second_click_or_a_second_admin_pays_nothing_more()
    {
        [$id, $order] = $this->refundRequest(300);
        $this->actingAs($this->makeAdmin());
        $this->post(route('refund_request_money_by_admin'), ['refund_id' => $id]);
        $this->post(route('refund_request_money_by_admin'), ['refund_id' => $id])->assertSessionHas('flash_notification');
        $this->actingAs($this->makeAdmin());
        $this->post(route('refund_request_money_by_admin'), ['refund_id' => $id]);
        $this->assertEquals(300.0, (float) DB::table('users')->where('id', $order->user_id)->value('balance'), 'the customer was credited once');
        $this->assertSame(1, DB::table('wallets')->where('user_id', $order->user_id)->where('payment_method', 'Refund')->count());
        $this->assertSame(1, DB::table('refund_postings')->count());
        $this->assertEquals(300.0, $this->bal('customer_wallet'));
    }

    public function test_a_failed_posting_rolls_the_wallet_credit_back()
    {
        [$id, $order] = $this->refundRequest(5000); // more than the 1000 line: the posting refuses
        $this->actingAs($this->makeAdmin());
        $this->post(route('refund_request_money_by_admin'), ['refund_id' => $id]);
        $this->assertEquals(0.0, (float) DB::table('users')->where('id', $order->user_id)->value('balance'), 'nothing was credited');
        $this->assertSame(0, DB::table('wallets')->where('user_id', $order->user_id)->count());
        $this->assertSame(0, (int) DB::table('refund_requests')->where('id', $id)->value('refund_status'));
    }

    public function test_cash_and_gateway_refunds_credit_the_right_account_and_cannot_be_duplicated_or_exceed_the_line()
    {
        [, $order, $line] = $this->refundRequest();
        $cash = ChartOfAccount::create(['code' => 'RF-' . uniqid(), 'name' => 'Refund cash ' . uniqid(), 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 1000, 'status' => 'active', 'is_deleted' => 0]);
        $svc = app(RefundService::class);
        $svc->record('c1', 'cash', 200, $order->id, $line->id, null, $cash->id);
        $this->assertEquals(800.0, (float) $cash->fresh()->current_balance, 'cash left the cash account');
        $this->assertTrue($svc->record('c1', 'cash', 200, $order->id, $line->id, null, $cash->id)['replayed'], 'the same key replays');
        $this->assertEquals(800.0, (float) $cash->fresh()->current_balance);

        $svc->record('g1', 'gateway', 300, $order->id, $line->id, null, null, 'GW-REF-1');
        $this->assertEquals(-300.0, $this->bal('gateway_clearing'), 'credited to Payment Gateway Clearing');
        foreach ([['g2', 'gateway', 100, 'GW-REF-1', 'already recorded'], ['g3', 'gateway', 100, null, "gateway's refund reference"], ['c2', 'cash', 600, null, 'exceed']] as [$k, $m, $a, $ref, $msg]) {
            try {
                $svc->record($k, $m, $a, $order->id, $line->id, null, $m === 'cash' ? $cash->id : null, $ref);
                $this->fail("$k should have been refused");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($msg, $e->getMessage());
            }
        }
        $this->assertSame(2, DB::table('refund_postings')->count(), 'only the two valid refunds exist');
        $this->assertTrue(JournalService::trialBalance(now()->toDateString())['balanced']);
    }

    public function test_with_the_flag_off_the_duplicate_guard_still_applies_and_no_journal_is_written()
    {
        config(['inventory.confirmed_only' => false]);
        \App\Services\Inventory\InventoryMode::reset();
        [$id, $order] = $this->refundRequest(250);
        $this->actingAs($this->makeAdmin());
        $entries = DB::table('journal_entries')->count();
        $this->post(route('refund_request_money_by_admin'), ['refund_id' => $id]);
        $this->post(route('refund_request_money_by_admin'), ['refund_id' => $id]);
        $this->assertEquals(250.0, (float) DB::table('users')->where('id', $order->user_id)->value('balance'), 'credited once even with the flag off (the old code credited twice)');
        $this->assertSame($entries, DB::table('journal_entries')->count());
    }

    public function test_reconciliation_reports_refund_postings_and_informs_about_the_wallet_difference()
    {
        [$id] = $this->refundRequest(100);
        $this->actingAs($this->makeAdmin());
        $this->post(route('refund_request_money_by_admin'), ['refund_id' => $id]);
        $sev = collect((new AccountingReconciliation())->checks())->pluck('severity', 'key');
        $this->assertSame('ok', $sev['refunds_without_journal']);
        $this->assertSame('info', $sev['wallet_liability_vs_user_balances']);
    }

    public function test_bank_refund_and_multiple_partial_refunds_add_up_to_exactly_the_line_and_not_a_cent_more()
    {
        [, $order, $line] = $this->refundRequest();
        $bank = ChartOfAccount::create(['code' => 'RB-' . uniqid(), 'name' => 'Refund bank ' . uniqid(), 'type' => 'asset', 'sub_type' => 'bank', 'opening_balance' => 0, 'current_balance' => 5000, 'status' => 'active', 'is_deleted' => 0]);
        $svc = app(RefundService::class);
        $svc->record('m1', 'cash', 300, $order->id, $line->id, null, $bank->id);   // a bank account is accepted wherever a cash account is
        $svc->record('m2', 'wallet', 400, $order->id, $line->id);
        $svc->record('m3', 'gateway', 300, $order->id, $line->id, null, null, 'GW-LAST');
        $this->assertEquals(4700.0, (float) $bank->fresh()->current_balance);
        $this->assertEquals(1000.0, (float) DB::table('refund_postings')->where('order_detail_id', $line->id)->sum('amount'), 'the three partial refunds equal the line');
        $this->expectException(InvalidArgumentException::class);
        $svc->record('m4', 'wallet', 0.01, $order->id, $line->id);
    }

    public function test_a_refused_refund_leaves_wallet_balance_postings_and_every_account_exactly_as_before()
    {
        [$id, $order] = $this->refundRequest(5000); // above the 1000 line: refused at posting time
        $this->actingAs($this->makeAdmin());
        $snap = fn () => json_encode([DB::table('wallets')->count(), (float) DB::table('users')->where('id', $order->user_id)->value('balance'), DB::table('refund_postings')->count(), DB::table('journal_entries')->count(),
            DB::table('journal_lines')->count(), DB::table('chart_of_accounts')->orderBy('id')->pluck('current_balance', 'id')->all(), DB::table('refund_requests')->where('id', $id)->value('refund_status')]);
        $before = $snap();
        $this->post(route('refund_request_money_by_admin'), ['refund_id' => $id]);
        $this->assertSame($before, $snap(), 'nothing moved: no wallet row, no balance, no posting, no entry, no account change');
    }
}
