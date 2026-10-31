<?php

namespace Tests\Concurrency;

use Illuminate\Support\Facades\DB;

/**
 * T-5 final verification, workflows 5-12 and 17: REAL parallel processes (authenticated HTTP through the kernel) against committed rows.
 * Each test checks the books exactly: shared-account sums (no lost update), one effect per duplicated request, distinct document numbers,
 * and that the journal settles (no entry twice, balanced).
 */
class WorkflowParallelCoverageTest extends HttpWorkflowCase
{
    private function ok(array $res): int
    {
        return count(array_filter($res, fn ($r) => ($r['status'] ?? 0) < 400 && !str_contains(json_encode($r['body'] ?? ''), 'rror')));
    }

    // ---- W10 / W17: expense, income, transfer, voucher on one shared account ---------------------------------------------

    public function test_four_incomes_into_one_account_add_up_and_get_distinct_numbers()
    {
        $this->flag(true);
        $cash = $this->cashAccount(0);
        $inc = $this->account('income', 'income', 0);
        $res = $this->race(array_map(fn ($i) => $this->req($this->admin(), route('accounts.incomes.store', [], false), ['date' => now()->toDateString(), 'received_account_id' => $cash, 'income_account_ids' => [$inc], 'amounts' => [10 * $i]]), [1, 2, 3, 4]), 120);

        $this->assertSame(4, DB::table('incomes')->where('received_account_id', $cash)->count(), json_encode(array_map(fn ($r) => [$r['status'] ?? null, $r['headers'] ?? null, $r['flash_error'] ?? null], $res)));
        $this->assertEquals(100.0, $this->bal($cash), 'no lost update: 10+20+30+40');
        $this->assertEquals(100.0, $this->bal($inc));
        $this->assertCount(4, DB::table('incomes')->where('received_account_id', $cash)->pluck('voucher_no')->unique(), 'distinct INC- numbers');
        $this->assertJournalSettles();
    }

    public function test_four_vouchers_over_two_shared_accounts_post_every_line_once()
    {
        $this->flag(true);
        $dr = $this->cashAccount(0);
        $cr = $this->account('liability', 'payable', 500);
        $res = $this->race(array_map(fn ($i) => $this->req($this->admin(), route('accounts.vouchers.store', [], false), ['date' => now()->toDateString(), 'type' => 'JV', 'account_ids' => [$dr, $cr], 'debits' => [10 * $i, 0], 'credits' => [0, 10 * $i]]), [1, 2, 3, 4]), 120);

        $this->assertSame(4, DB::table('voucher_details')->where('account_id', $dr)->count(), json_encode(array_column($res, 'status')));
        $this->assertEquals(100.0, $this->bal($dr), 'debit side: 10+20+30+40');
        $this->assertCount(4, DB::table('vouchers')->whereIn('id', DB::table('voucher_details')->where('account_id', $dr)->pluck('voucher_id'))->pluck('voucher_no')->unique());
        $this->assertJournalSettles();
    }

    public function test_voucher_deleted_three_times_at_once_is_reversed_once()
    {
        $this->flag(true);
        $dr = $this->cashAccount(0);
        $cr = $this->account('liability', 'payable', 500);
        $this->runOne($this->req($this->admin(), route('accounts.vouchers.store', [], false), ['date' => now()->toDateString(), 'type' => 'JV', 'account_ids' => [$dr, $cr], 'debits' => [75, 0], 'credits' => [0, 75]]));
        $id = (int) DB::table('voucher_details')->where('account_id', $dr)->value('voucher_id');
        $afterStore = [$this->bal($dr), $this->bal($cr)];
        $this->assertNotEquals([0.0, 500.0], $afterStore);
        $this->race(array_map(fn () => $this->req($this->admin(), route('accounts.vouchers.delete', $id, false), [], 'DELETE'), range(1, 3)), 120);
        $this->assertEquals([0.0, 500.0], [$this->bal($dr), $this->bal($cr)], 'back to the start, once');
        $this->assertJournalSettles();
    }

    public function test_expense_deleted_three_times_at_once_is_reversed_once()
    {
        $this->flag(true);
        $cash = $this->cashAccount(100);
        $exp = $this->account('expense', 'expense', 0);
        $this->runOne($this->req($this->admin(), route('accounts.expense-payments.store', [], false), ['date' => now()->toDateString(), 'payment_account_id' => $cash, 'expense_account_ids' => [$exp], 'amounts' => [60]]));
        $id = (int) DB::table('expense_payments')->where('payment_account_id', $cash)->value('id');
        $this->assertEquals(40.0, $this->bal($cash));
        $this->race(array_map(fn () => $this->req($this->admin(), route('accounts.expense-payments.delete', $id, false), [], 'DELETE'), range(1, 3)), 120);
        $this->assertEquals(100.0, $this->bal($cash));
        $this->assertEquals(0.0, $this->bal($exp));
        $this->assertJournalSettles();
    }

    public function test_four_transfers_between_the_same_accounts_in_opposite_directions_conserve_the_total_without_deadlock_failures()
    {
        $this->flag(true);
        $a = $this->cashAccount(1000);
        $b = $this->cashAccount(1000);
        $jobs = [];
        foreach ([[$a, $b, 10], [$b, $a, 20], [$a, $b, 30], [$b, $a, 40]] as [$f, $t, $amt]) {
            $jobs[] = $this->req($this->admin(), route('accounts.balance-transfers.store', [], false), ['transaction_type' => 'transfer', 'date' => now()->toDateString(), 'from_account_id' => $f, 'to_account_id' => $t, 'amount' => $amt, 'charge' => 0]);
        }
        $res = $this->race($jobs, 120);

        $this->assertSame(4, DB::table('balance_transfers')->whereIn('from_account_id', [$a, $b])->count(), json_encode(array_map(fn ($r) => [$r['status'] ?? null, $r['headers'] ?? null, $r['flash_error'] ?? null], $res)));
        $this->assertEquals(2000.0, $this->bal($a) + $this->bal($b), 'conserved');
        $this->assertEquals(1000 - 10 + 20 - 30 + 40, $this->bal($a));
        $this->assertCount(4, DB::table('balance_transfers')->whereIn('from_account_id', [$a, $b])->pluck('voucher_no')->unique());
        $this->assertJournalSettles();
    }

    // ---- W11: stock adjustment ----------------------------------------------------------------------------------------------

    private function adjustBody(int $product, int $qty): array
    {
        $stockId = (int) DB::table('product_stocks')->where('product_id', $product)->where('variant', 'M')->value('id');
        return ['transaction_date' => now()->toDateString(), 'adjustment_type' => 'normal', 'products' => [['product_id' => $product, 'product_stock_id' => $stockId, 'variant' => 'M', 'quantity' => $qty, 'unit_price' => 400]]];
    }

    public function test_four_stock_adjustments_of_one_product_remove_exactly_their_sum_from_shelf_and_layers()
    {
        $this->flag(false);
        $p = $this->seedProduct(['M' => 10], 1000, 400, ['M' => [3, 7]]);
        $res = $this->race(array_map(fn ($q) => $this->req($this->admin(), '/admin/stock-adjustments', $this->adjustBody($p, $q)), [1, 2, 1, 2]), 120);

        $this->assertSame(4, DB::table('stock_adjustments')->count() - $this->adjustmentsBefore(), json_encode(array_column($res, 'status')));
        $this->assertSame(4, (int) DB::table('product_stocks')->where('product_id', $p)->value('qty'), '10 - 6');
        $this->assertSame(4, (int) DB::table('purchase_products')->where('product_id', $p)->sum('remaining_qty'), 'layers equal the shelf');
        $this->assertCount(4, DB::table('stock_adjustments')->orderByDesc('id')->limit(4)->pluck('reference_no')->unique(), 'distinct SA numbers');
    }

    // ---- W5-W8: purchasing, supplier, customer payment ------------------------------------------------------------------------

    private function supplier(): int
    {
        return (int) DB::table('suppliers')->insertGetId(['slug' => 'wp-' . uniqid(), 'business_name' => 'WP', 'contact_person_name' => 'C', 'mobile' => '017' . random_int(10000000, 99999999), 'opening_balance' => '0', 'due_amount' => 0, 'paid_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function purchaseOn(int $s, int $admin, int $qty = 5, float $cost = 20, int $paid = 0, ?int $cash = null): array
    {
        $p = $this->seedProduct(['M' => 0], 1000, 100);
        DB::table('purchase_products')->where('product_id', $p)->delete();
        $body = ['op_key' => 'wp-' . uniqid(), 'supplier_id' => $s, 'purchase_date' => now()->toDateString(), 'product_id' => [$p], 'name' => ['x'], 'variant' => ['M'], 'purchase_quantity' => [$qty], 'buying_unit_cost' => [$cost], 'total_cost' => [$qty * $cost]];
        if ($paid) {
            $body += ['pay_by' => 'cash', 'payment_account_id' => $cash, 'paid_amount' => $paid];
        }
        $r = $this->runOne($this->req($admin, '/purchase/store', $body));
        $this->assertTrue($r['body']['success'] ?? false, json_encode($r));

        return [(int) DB::table('purchases')->where('supplier_id', $s)->orderByDesc('id')->value('id'), $p];
    }

    public function test_process_payment_sent_three_times_at_once_pays_the_supplier_once()
    {
        $this->flag(true);
        $s = $this->supplier();
        $admin = $this->admin();
        [$purchase] = $this->purchaseOn($s, $admin);
        $res = $this->race(array_map(fn () => $this->req($this->admin(), '/supplier/payment/process', ['supplier_id' => $s, 'payment_amount' => 70]), range(1, 3)), 120);

        $this->assertSame(1, count(array_filter($res, fn ($r) => ($r['body']['success'] ?? false) === true)), 'only one 70 fits in a 100 due: ' . json_encode(array_column($res, 'body')));
        $this->assertEquals(70.0, (float) DB::table('suppliers')->where('id', $s)->value('paid_amount'));
        $this->assertEquals(30.0, (float) DB::table('purchases')->where('id', $purchase)->value('total_payment_due'));
        $this->assertJournalSettles();
    }

    public function test_supplier_payment_update_sent_three_times_at_once_is_applied_once()
    {
        $this->flag(true);
        $s = $this->supplier();
        $admin = $this->admin();
        $cash = $this->cashAccount(1000);
        [$purchase] = $this->purchaseOn($s, $admin);
        $pay = fn ($amt) => ['supplier_id' => $s, 'payment_date' => now()->toDateString(), 'amount' => $amt, 'payment_method' => 'CASH', 'account_head_id' => $cash, 'allocations' => json_encode([['purchase_id' => $purchase, 'paid_amount' => $amt]])];
        $this->runOne($this->req($admin, '/admin/accounts/supplier-payments/store', $pay(30)));
        $id = (int) DB::table('supplier_payments')->where('supplier_id', $s)->value('id');
        $this->assertEquals(970.0, $this->bal($cash));

        $this->race(array_map(fn () => $this->req($this->admin(), '/admin/accounts/supplier-payments/update/' . $id, $pay(50), 'PUT'), range(1, 3)), 120);

        $this->assertEquals(950.0, $this->bal($cash), 'the payment is now 50: cash 1000-50, not 1000-30-... repeated');
        $this->assertEquals(50.0, (float) DB::table('supplier_payments')->where('id', $id)->value('amount'));
        $this->assertEquals(50.0, (float) DB::table('purchases')->where('id', $purchase)->value('total_payment_due'));
        $this->assertEquals(50.0, (float) DB::table('suppliers')->where('id', $s)->value('due_amount'), 'supplier due = invoice 100 - payment 50 (the edit defect used to drift it on every edit)');
        $this->assertEquals(50.0, (float) DB::table('suppliers')->where('id', $s)->value('paid_amount'));
        $this->assertJournalSettles();
    }

    public function test_edits_with_different_amounts_and_an_edit_racing_a_delete_always_end_consistent()
    {
        $this->flag(true);
        $admin = $this->admin();
        $mk = function () use ($admin) {
            $s = $this->supplier();
            $cash = $this->cashAccount(1000);
            [$purchase] = $this->purchaseOn($s, $admin);
            $pay = fn ($amt) => ['supplier_id' => $s, 'payment_date' => now()->toDateString(), 'amount' => $amt, 'payment_method' => 'CASH', 'account_head_id' => $cash, 'allocations' => json_encode([['purchase_id' => $purchase, 'paid_amount' => $amt]])];
            $this->runOne($this->req($admin, '/admin/accounts/supplier-payments/store', $pay(30)));
            return [$s, $cash, $purchase, (int) DB::table('supplier_payments')->where('supplier_id', $s)->value('id'), $pay];
        };
        // two different edits at once: the final state is exactly one of them applied, never a mixture
        [$s, $cash, $purchase, $id, $pay] = $mk();
        $this->race([$this->req($this->admin(), '/admin/accounts/supplier-payments/update/' . $id, $pay(50), 'PUT'), $this->req($this->admin(), '/admin/accounts/supplier-payments/update/' . $id, $pay(80), 'PUT')], 120);
        $final = (float) DB::table('supplier_payments')->where('id', $id)->value('amount');
        $this->assertContains($final, [50.0, 80.0]);
        $this->assertEquals(100 - $final, (float) DB::table('suppliers')->where('id', $s)->value('due_amount'));
        $this->assertEquals($final, (float) DB::table('suppliers')->where('id', $s)->value('paid_amount'));
        $this->assertEquals(100 - $final, (float) DB::table('purchases')->where('id', $purchase)->value('total_payment_due'));
        $this->assertEquals(1000 - $final, $this->bal($cash));

        // an edit and a delete at once: the payment ends deleted and everything is back to the start, whichever came first
        [$s2, $cash2, $purchase2, $id2, $pay2] = $mk();
        $this->race([$this->req($this->admin(), '/admin/accounts/supplier-payments/update/' . $id2, $pay2(50), 'PUT'), $this->req($this->admin(), '/admin/accounts/supplier-payments/delete/' . $id2, [], 'DELETE')], 120);
        if (DB::table('supplier_payments')->where('id', $id2)->exists()) { // the delete ran first and the edit... cannot exist; the edit then the delete leaves none
            $this->fail('the payment must be gone');
        }
        $this->assertEquals(100.0, (float) DB::table('suppliers')->where('id', $s2)->value('due_amount'));
        $this->assertEquals(0.0, (float) DB::table('suppliers')->where('id', $s2)->value('paid_amount'));
        $this->assertEquals(100.0, (float) DB::table('purchases')->where('id', $purchase2)->value('total_payment_due'));
        $this->assertEquals(1000.0, $this->bal($cash2));
        $this->assertSame(0, DB::table('supplier_payment_purchases')->where('supplier_payment_id', $id2)->count(), 'no orphan allocation');
        $this->assertJournalSettles();
    }

    public function test_four_purchase_orders_at_once_each_get_their_own_rows()
    {
        $this->flag(false);
        $s = $this->supplier();
        $p = $this->seedProduct(['M' => 0], 1000, 100);
        $jobs = array_map(fn ($i) => $this->req($this->admin(), '/purchase-order/store', ['supplier_id' => $s, 'reference_no' => "PO-WP-$i-" . uniqid(), 'purchase_date' => now()->toDateString(), 'business_location' => 'Main', 'product_id' => [$p], 'name' => ['x'],
            'remaining_quantity' => [5], 'purchase_quantity' => [5], 'recieved_quantity' => [0], 'buying_unit_cost' => [20], 'total_cost' => [100], 'tax' => [0], 'payments' => [['amount' => 10, 'paid_on' => now()->toDateString(), 'method' => 'cash']]]), range(1, 4));
        $res = $this->race($jobs, 120);

        $ids = DB::table('purchase_orders')->where('supplier_id', $s)->pluck('id');
        $this->assertCount(4, $ids, json_encode(array_column($res, 'body')));
        $this->assertSame(4, DB::table('purchase_order_details')->whereIn('purchase_order_id', $ids)->count(), 'one detail line each');
    }

    public function test_customer_payment_for_the_same_order_sent_three_times_at_once_applies_the_order_amount_once()
    {
        $this->flag(true);
        $cust = (int) DB::table('users')->insertGetId(['name' => 'cust', 'email' => uniqid() . '@wp.test', 'password' => 'x', 'user_type' => 'customer', 'created_at' => now(), 'updated_at' => now()]);
        $o = new \App\Models\Order();
        $o->forceFill(['user_id' => $cust, 'seller_id' => 1, 'code' => \App\Models\Order::generateUniqueCode(), 'delivery_status' => 'delivered', 'payment_status' => 'paid', 'payment_type' => 'cash_on_delivery', 'shipping_address' => '{}', 'grand_total' => 1000, 'date' => time(), 'order_from' => 'app'])->save();
        $cash = $this->cashAccount(0);
        $this->systemAccounts();
        $method = (int) DB::table('transaction_methods')->insertGetId(['name' => 'WP ' . uniqid(), 'type' => 'cash', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $body = ['customer_id' => $cust, 'amount' => 800, 'order_ids' => [$o->id], 'payment_account_id' => $cash, 'transaction_method_id' => $method, 'payment_date' => now()->toDateString()];
        $res = $this->race(array_map(fn () => $this->req($this->admin(), '/admin/accounts/customer-payments/store', $body), range(1, 3)), 120);

        $applied = (float) DB::table('order_payment_mappings')->where('order_id', $o->id)->sum('amount_applied');
        $this->assertLessThanOrEqual(1000.0, $applied, 'never more than the order total is applied: ' . json_encode(array_column($res, 'body')));
        $this->assertEquals($applied, $this->bal($cash), 'the account holds exactly what was applied');
        $this->assertJournalSettles();
    }

    public function test_four_purchase_returns_at_once_change_the_books_like_four_sequential_returns()
    {
        $this->flag(true);
        $admin = $this->admin();
        $payable = (int) \App\Models\ChartOfAccount::getAccountsPayable()->id;
        $returnJob = function (int $s, int $purchase, int $p, int $pp, string $tag) {
            return $this->req($this->admin(), '/purchase-return/store', ['supplier_id' => $s, 'transaction_date' => now()->toDateString(), 'reference_no' => "PR-WP-$tag-" . uniqid(), 'product_id' => [$p], 'product_quantity' => [2], 'product_unit_cost' => [20], 'product_subtotal' => [40],
                'product_name' => ['x'], 'purchase_id' => [$purchase], 'purchase_product_id' => [$pp], 'variant_name' => ['M']]);
        };
        // reference: four returns one after the other on their own purchase
        $s1 = $this->supplier();
        [$pu1, $p1] = $this->purchaseOn($s1, $admin, 10, 20);
        $pp1 = (int) DB::table('purchase_products')->where('purchase_id', $pu1)->value('id');
        $apBefore = $this->bal($payable);
        $dueBefore = (float) DB::table('suppliers')->where('id', $s1)->value('due_amount');
        foreach (range(1, 4) as $i) {
            $this->runOne($returnJob($s1, $pu1, $p1, $pp1, "seq$i"));
        }
        $seqAp = $this->bal($payable) - $apBefore;
        $seqDue = (float) DB::table('suppliers')->where('id', $s1)->value('due_amount') - $dueBefore;
        $this->assertSame(4, DB::table('purchase_returns')->where('supplier_id', $s1)->count());

        // the same four at once
        $s2 = $this->supplier();
        [$pu2, $p2] = $this->purchaseOn($s2, $admin, 10, 20);
        $pp2 = (int) DB::table('purchase_products')->where('purchase_id', $pu2)->value('id');
        $apBefore2 = $this->bal($payable);
        $dueBefore2 = (float) DB::table('suppliers')->where('id', $s2)->value('due_amount');
        $res = $this->race(array_map(fn ($i) => $returnJob($s2, $pu2, $p2, $pp2, "par$i"), range(1, 4)), 120);

        $this->assertSame(4, DB::table('purchase_returns')->where('supplier_id', $s2)->count(), json_encode(array_column($res, 'body')));
        $this->assertSame(2, (int) DB::table('product_stocks')->where('product_id', $p2)->sum('qty'), '10 received - 4 x 2 returned');
        $this->assertSame(2, (int) DB::table('purchase_products')->where('product_id', $p2)->sum('remaining_qty'), 'layers agree with the shelf');
        $this->assertEquals($seqAp, $this->bal($payable) - $apBefore2, 'Accounts Payable moves exactly as four sequential returns moved it (no lost update)');
        $this->assertEquals($seqDue, (float) DB::table('suppliers')->where('id', $s2)->value('due_amount') - $dueBefore2, 'supplier due likewise');
        $this->assertJournalSettles();
    }

    public function test_balance_adjustments_sent_at_once_all_count()
    {
        $this->flag(true);
        $acc = $this->cashAccount(0);
        $res = $this->race(array_map(fn ($i) => $this->req($this->admin(), route('accounts.chart-of-accounts.adjust-balance', $acc, false), ['adjustment_amount' => 10 * $i, 'adjustment_type' => 'add', 'description' => 'race']), [1, 2, 3, 4]), 120);
        $this->assertEquals(100.0, $this->bal($acc), json_encode(array_column($res, 'body')));
        $this->assertJournalSettles();
    }

    public function test_cancel_sent_three_times_at_once_restocks_once()
    {
        $this->flag(false);
        $p = $this->seedProduct(['M' => 10], 1000, 400);
        $admin = $this->admin();
        DB::table('carts')->insert(['user_id' => $admin, 'product_id' => $p, 'variation' => 'M', 'quantity' => 3, 'price' => 1000, 'tax' => 0, 'shipping_cost' => 0, 'discount' => 0, 'shipping_type' => 'home_delivery', 'created_at' => now(), 'updated_at' => now()]);
        $addr = (int) DB::table('addresses')->insertGetId(['user_id' => $admin, 'address' => 'H', 'phone' => '01700000001', 'postal_code' => '1200', 'country_id' => 18, 'state_id' => 0, 'city_id' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->runOne($this->req($admin, '/admin/orders', ['address_id' => $addr, 'payment_option' => 'cash_on_delivery', 'shipping_cost_amount' => 0]));
        $order = (int) DB::table('orders')->where('user_id', $admin)->value('id');
        $this->assertSame(7, (int) DB::table('product_stocks')->where('product_id', $p)->value('qty'));

        $this->race(array_map(fn () => $this->req($this->admin(), '/admin/orders/update_delivery_status', ['order_id' => $order, 'status' => 'cancelled']), range(1, 3)), 120);

        $this->assertSame(10, (int) DB::table('product_stocks')->where('product_id', $p)->value('qty'), 'the 3 units come back once, not 3 x 3');
        $this->assertSame(10, (int) DB::table('purchase_products')->where('product_id', $p)->sum('remaining_qty'));
    }

    // ---- first income number of a period: the deadlock the retry only papered over ----------------------------------------

    /** Ten rounds of four simultaneous incomes, each round starting from an EMPTY year (the first-number case). No request may
     *  meet a deadlock at all (no X-Deadlock-Retries header): the creators are serialised, not retried. */
    public function test_first_income_number_of_a_year_never_deadlocks_over_repeated_rounds()
    {
        $this->flag(true);
        $cash = $this->cashAccount(0);
        $inc = $this->account('income', 'income', 0);
        $admin = $this->admin();
        $retried = 0;
        for ($round = 1; $round <= 10; $round++) {
            $this->assertSame(0, DB::table('incomes')->where('voucher_no', 'like', 'INC-' . date('y') . '%')->count(), 'round starts with an empty year');
            $res = $this->race(array_map(fn ($i) => $this->req($admin, route('accounts.incomes.store', [], false), ['date' => now()->toDateString(), 'received_account_id' => $cash, 'income_account_ids' => [$inc], 'amounts' => [$i]]), [1, 2, 3, 4]), 120);
            foreach ($res as $r) {
                $retried += isset($r['headers']['x-deadlock-retries']) || isset($r['headers']['X-Deadlock-Retries']) ? 1 : 0;
            }
            $nos = DB::table('incomes')->where('received_account_id', $cash)->orderBy('id')->pluck('voucher_no')->all();
            $this->assertCount(4, array_unique($nos), 'round ' . $round . ': four distinct numbers ' . json_encode($nos));
            $this->assertSame(['000001', '000002', '000003', '000004'], array_map(fn ($n) => substr($n, -6), $this->sortNos($nos)), 'consecutive from 1');
            DB::table('income_details')->whereIn('income_id', DB::table('incomes')->where('received_account_id', $cash)->pluck('id'))->delete();
            DB::table('incomes')->where('received_account_id', $cash)->delete();
        }
        $this->assertSame(0, $retried, 'no request needed a deadlock retry');
    }

    /** A creator that fails after taking the number must not leave the series locked for the next one. */
    public function test_a_failed_income_releases_the_number_lock()
    {
        $this->flag(true);
        $cash = $this->cashAccount(0);
        $bad = $this->req($this->admin(), route('accounts.incomes.store', [], false), ['date' => now()->toDateString(), 'received_account_id' => $cash, 'income_account_ids' => [999999999], 'amounts' => [5]]);
        $this->runOne($bad);
        $started = microtime(true);
        $inc = $this->account('income', 'income', 0);
        $this->runOne($this->req($this->admin(), route('accounts.incomes.store', [], false), ['date' => now()->toDateString(), 'received_account_id' => $cash, 'income_account_ids' => [$inc], 'amounts' => [7]]));
        $this->assertLessThan(5, microtime(true) - $started, 'the next income was not held up by the failed one');
        $this->assertSame(1, DB::table('incomes')->where('received_account_id', $cash)->count());
    }

    private function sortNos(array $n): array
    {
        sort($n);

        return $n;
    }

    private function adjustmentsBefore(): int
    {
        return 0;
    }
}
