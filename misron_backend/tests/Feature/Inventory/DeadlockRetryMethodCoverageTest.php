<?php

namespace Tests\Feature\Inventory;

use App\Models\ChartOfAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * T-5 final verification: method-level deadlock coverage for the transactional methods of config/deadlock_retry.php that had no executed test.
 * Every scenario is run two ways on identical fixtures:
 *   once   : a fresh fixture, one injected deadlock (a real-shaped SQLSTATE 40001 / 1213 raised AFTER the statement ran, with the server-side
 *            rollback emulated). The request must be retried and end in EXACTLY the state of a clean run on a twin fixture (no duplicated
 *            document, balance, log, number or file).
 *   always : a deadlock on every attempt. Exactly three attempts, then the observable state equals the state before the request.
 * The "observation" of a scenario is a vector of balances and counts for the accounts and documents of that fixture.
 */
class DeadlockRetryMethodCoverageTest extends InventoryV2TestCase
{
    private const LEDGER = '/^(insert into `account_statement_logs`|update `chart_of_accounts`)/i';

    private function acct(string $tag, float $balance, string $type = 'asset', string $sub = 'cash'): ChartOfAccount
    {
        return ChartOfAccount::create(['code' => 'MC-' . $tag . uniqid(), 'name' => 'MC ' . $tag . uniqid(), 'type' => $type, 'sub_type' => $sub, 'opening_balance' => 0, 'current_balance' => $balance, 'status' => 'active', 'is_deleted' => 0]);
    }

    private function bal(ChartOfAccount $a): float
    {
        return (float) DB::table('chart_of_accounts')->where('id', $a->id)->value('current_balance');
    }

    public static function methods(): array
    {
        return array_map(fn ($n) => [$n], ['voucher_update', 'voucher_destroy', 'expense_store', 'expense_update', 'expense_destroy', 'income_update', 'income_destroy', 'transfer_update', 'transfer_destroy',
            'supplier_payment_update', 'supplier_payment_destroy', 'supplier_process_payment', 'purchase_update_payment', 'customer_payment_store', 'customer_payment_destroy', 'stock_adjustment_destroy', 'courier_unsettle', 'po_store', 'po_update', 'order_edit_update', 'order_edit_delete', 'order_items_status']);
    }

    /** @return array{setup: callable, act: callable, observe: callable, inject: string} */
    private function scenario(string $name): array
    {
        return $this->{'s_' . $name}();
    }

    /** @dataProvider methods */
    public function test_one_deadlock_is_retried_and_ends_in_the_state_of_a_clean_run(string $name)
    {
        $this->actingAs($this->makeAdmin());
        $s = $this->scenario($name);
        $clean = $s['setup']();
        $s['act']($clean);
        $expected = $s['observe']($clean);

        $twin = $s['setup']();
        $inj = DeadlockInjector::once($s['inject']);
        $res = $s['act']($twin);
        $this->assertSame(1, $inj->thrown, "$name: the deadlock really happened");
        $this->assertSame($expected, $s['observe']($twin), "$name: the retried request ends exactly like a clean run (nothing duplicated, nothing lost)");
        $this->assertSame('1', $res->headers->get('X-Deadlock-Retries'), "$name: the request was retried once by the middleware");
    }

    /** @dataProvider methods */
    public function test_a_deadlock_on_every_attempt_stops_after_three_and_leaves_nothing(string $name)
    {
        $this->actingAs($this->makeAdmin());
        $s = $this->scenario($name);
        $ctx = $s['setup']();
        $before = $s['observe']($ctx);
        $inj = DeadlockInjector::always($s['inject']);
        $s['act']($ctx);
        $this->assertSame(3, $inj->thrown, "$name: exactly three attempts, not more");
        $this->assertSame($before, $s['observe']($ctx), "$name: no partial transaction remains");
    }

    /** Regression found by this suite: after the LATEST customer payment / stock adjustment was deleted, the next one got the same unique number and failed with 500. */
    public function test_a_document_created_after_the_latest_one_was_deleted_gets_a_new_number()
    {
        $this->actingAs($this->makeAdmin());
        foreach (['customer_payment_destroy', 'stock_adjustment_destroy'] as $name) {
            $s = $this->scenario($name);
            $first = $s['setup']();
            $s['act']($first);          // creates, then deletes the newest document
            $second = $s['setup']();    // creating again used to fail on the reused number
            $this->assertNotNull($second, "$name: a new document can be created after the newest was deleted");
        }
    }

    // ---- fixtures -------------------------------------------------------------------------------------------------------------

    private function s_voucher_update(): array
    {
        $body = fn ($dr, $cr, $amt) => ['date' => now()->toDateString(), 'type' => 'JV', 'account_ids' => [$dr->id, $cr->id], 'debits' => [$amt, 0], 'credits' => [0, $amt]];
        $obs = fn ($c) => [$this->bal($c['dr']), $this->bal($c['cr']), DB::table('voucher_details')->where('voucher_id', $c['id'])->count(), (float) DB::table('voucher_details')->where('voucher_id', $c['id'])->sum('debit')];
        return ['inject' => self::LEDGER, 'observe' => $obs,
            'setup' => function () use ($body) {
                $dr = $this->acct('dr', 0);
                $cr = $this->acct('cr', 200, 'liability', 'payable');
                $this->post(route('accounts.vouchers.store'), $body($dr, $cr, 75));
                $id = (int) DB::table('voucher_details')->where('account_id', $dr->id)->value('voucher_id');
                return ['dr' => $dr, 'cr' => $cr, 'id' => $id];
            },
            'act' => fn ($c) => $this->put(route('accounts.vouchers.update', $c['id']), $body($c['dr'], $c['cr'], 90))];
    }

    private function s_voucher_destroy(): array
    {
        $s = $this->s_voucher_update();
        $s['act'] = fn ($c) => $this->delete(route('accounts.vouchers.delete', $c['id']));
        $s['observe'] = fn ($c) => [$this->bal($c['dr']), $this->bal($c['cr']), DB::table('vouchers')->where('id', $c['id'])->count()];
        return $s;
    }

    private function expenseBody($cash, $exp, $amt): array
    {
        return ['date' => now()->toDateString(), 'payment_account_id' => $cash->id, 'expense_account_ids' => [$exp->id], 'amounts' => [$amt]];
    }

    private function s_expense_store(): array
    {
        return ['inject' => self::LEDGER,
            'observe' => fn ($c) => [$this->bal($c['cash']), $this->bal($c['exp']), DB::table('expense_payments')->where('payment_account_id', $c['cash']->id)->count()],
            'setup' => fn () => ['cash' => $this->acct('cash', 100), 'exp' => $this->acct('exp', 0, 'expense', 'expense')],
            'act' => fn ($c) => $this->post(route('accounts.expense-payments.store'), $this->expenseBody($c['cash'], $c['exp'], 60))];
    }

    private function s_expense_update(): array
    {
        $s = $this->s_expense_store();
        $s['setup'] = function () {
            $c = ['cash' => $this->acct('cash', 100), 'exp' => $this->acct('exp', 0, 'expense', 'expense')];
            $this->post(route('accounts.expense-payments.store'), $this->expenseBody($c['cash'], $c['exp'], 60));
            $c['id'] = (int) DB::table('expense_payments')->where('payment_account_id', $c['cash']->id)->value('id');
            return $c;
        };
        $s['act'] = fn ($c) => $this->put(route('accounts.expense-payments.update', $c['id']), $this->expenseBody($c['cash'], $c['exp'], 40));
        return $s;
    }

    private function s_expense_destroy(): array
    {
        $s = $this->s_expense_update();
        $s['act'] = fn ($c) => $this->delete(route('accounts.expense-payments.delete', $c['id']));
        return $s;
    }

    private function incomeBody($cash, $inc, $amt): array
    {
        return ['date' => now()->toDateString(), 'received_account_id' => $cash->id, 'income_account_ids' => [$inc->id], 'amounts' => [$amt]];
    }

    private function s_income_update(): array
    {
        return ['inject' => self::LEDGER,
            'observe' => fn ($c) => [$this->bal($c['cash']), $this->bal($c['inc']), DB::table('incomes')->where('received_account_id', $c['cash']->id)->count(), (float) DB::table('incomes')->where('received_account_id', $c['cash']->id)->sum('total_amount')],
            'setup' => function () {
                $c = ['cash' => $this->acct('cash', 0), 'inc' => $this->acct('inc', 0, 'income', 'income')];
                $this->post(route('accounts.incomes.store'), $this->incomeBody($c['cash'], $c['inc'], 250));
                $c['id'] = (int) DB::table('incomes')->where('received_account_id', $c['cash']->id)->value('id');
                return $c;
            },
            'act' => fn ($c) => $this->put(route('accounts.incomes.update', $c['id']), $this->incomeBody($c['cash'], $c['inc'], 300))];
    }

    private function s_income_destroy(): array
    {
        $s = $this->s_income_update();
        $s['act'] = fn ($c) => $this->delete(route('accounts.incomes.delete', $c['id']));
        return $s;
    }

    private function transferBody($a, $b, $amt): array
    {
        return ['transaction_type' => 'transfer', 'date' => now()->toDateString(), 'from_account_id' => $a->id, 'to_account_id' => $b->id, 'amount' => $amt, 'charge' => 0];
    }

    private function s_transfer_update(): array
    {
        return ['inject' => self::LEDGER,
            'observe' => fn ($c) => [$this->bal($c['a']), $this->bal($c['b']), DB::table('balance_transfers')->where('from_account_id', $c['a']->id)->count(), (float) DB::table('balance_transfers')->where('from_account_id', $c['a']->id)->sum('amount')],
            'setup' => function () {
                $c = ['a' => $this->acct('a', 500), 'b' => $this->acct('b', 0)];
                $this->post(route('accounts.balance-transfers.store'), $this->transferBody($c['a'], $c['b'], 100));
                $c['id'] = (int) DB::table('balance_transfers')->where('from_account_id', $c['a']->id)->value('id');
                return $c;
            },
            'act' => fn ($c) => $this->put(route('accounts.balance-transfers.update', $c['id']), $this->transferBody($c['a'], $c['b'], 150))];
    }

    private function s_transfer_destroy(): array
    {
        $s = $this->s_transfer_update();
        $s['act'] = fn ($c) => $this->delete(route('accounts.balance-transfers.delete', $c['id']));
        return $s;
    }

    // ---- purchasing / supplier ------------------------------------------------------------------------------------------------

    private function purchaseOn(int $supplierId, int $qty = 5, float $cost = 20, array $extra = []): array
    {
        $p = $this->productAtZero();
        $this->post(route('purchase.store'), array_merge(['op_key' => 'mc-' . uniqid(), 'supplier_id' => $supplierId, 'purchase_date' => now()->toDateString(), 'product_id' => [$p->id], 'name' => ['x'], 'variant' => [''],
            'purchase_quantity' => [$qty], 'buying_unit_cost' => [$cost], 'total_cost' => [$qty * $cost]], $extra), ['Accept' => 'application/json'])->assertOk();

        return [(int) DB::table('purchases')->where('supplier_id', $supplierId)->orderByDesc('id')->value('id'), $p];
    }

    private function newSupplier(): int
    {
        return (int) DB::table('suppliers')->insertGetId(['slug' => 'mc-' . uniqid(), 'business_name' => 'MC', 'contact_person_name' => 'C', 'mobile' => '017' . random_int(10000000, 99999999), 'opening_balance' => '0', 'due_amount' => 0, 'paid_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function supplierObserve(array $c): array
    {
        return [(float) DB::table('suppliers')->where('id', $c['s'])->value('due_amount'), (float) DB::table('suppliers')->where('id', $c['s'])->value('paid_amount'), $this->bal($c['cash']),
            (float) DB::table('purchases')->where('id', $c['purchase'])->value('total_payment_due'), DB::table('supplier_payments')->where('supplier_id', $c['s'])->count(), (float) DB::table('supplier_payments')->where('supplier_id', $c['s'])->sum('amount'),
            (float) DB::table('supplier_payment_purchases')->whereIn('supplier_payment_id', DB::table('supplier_payments')->where('supplier_id', $c['s'])->pluck('id'))->sum('paid_amount')];
    }

    private function paymentBody(array $c, float $amt): array
    {
        return ['supplier_id' => $c['s'], 'payment_date' => now()->toDateString(), 'amount' => $amt, 'payment_method' => 'CASH', 'account_head_id' => $c['cash']->id, 'allocations' => json_encode([['purchase_id' => $c['purchase'], 'paid_amount' => $amt]])];
    }

    private function supplierFixture(bool $withPayment): array
    {
        $s = $this->newSupplier();
        [$purchase] = $this->purchaseOn($s);
        $c = ['s' => $s, 'purchase' => $purchase, 'cash' => $this->acct('cash', 1000)];
        if ($withPayment) {
            $this->post(route('accounts.supplier-payments.store'), $this->paymentBody($c, 30), ['Accept' => 'application/json'])->assertOk();
            $c['pay'] = (int) DB::table('supplier_payments')->where('supplier_id', $s)->value('id');
        }
        return $c;
    }

    private function s_supplier_payment_update(): array
    {
        return ['inject' => self::LEDGER, 'observe' => fn ($c) => $this->supplierObserve($c), 'setup' => fn () => $this->supplierFixture(true),
            'act' => fn ($c) => $this->put(route('accounts.supplier-payments.update', $c['pay']), $this->paymentBody($c, 50), ['Accept' => 'application/json'])];
    }

    private function s_supplier_payment_destroy(): array
    {
        $s = $this->s_supplier_payment_update();
        $s['act'] = fn ($c) => $this->delete(route('accounts.supplier-payments.delete', $c['pay']), [], ['Accept' => 'application/json']);
        return $s;
    }

    private function s_supplier_process_payment(): array
    {
        return ['inject' => '/^update `suppliers`/i', 'observe' => fn ($c) => [(float) DB::table('suppliers')->where('id', $c['s'])->value('paid_amount'), (float) DB::table('suppliers')->where('id', $c['s'])->value('due_amount'), (float) DB::table('purchases')->where('id', $c['purchase'])->value('total_payment_due')],
            'setup' => fn () => $this->supplierFixture(false),
            'act' => fn ($c) => $this->post(route('supplier.payment.process'), ['supplier_id' => $c['s'], 'payment_amount' => 50], ['Accept' => 'application/json'])];
    }

    private function s_purchase_update_payment(): array
    {
        return ['inject' => '/^update `suppliers`|^update `purchases`/i',
            'observe' => fn ($c) => [(float) DB::table('purchases')->where('id', $c['purchase'])->value('total_payment_due'), (float) DB::table('purchases')->where('id', $c['purchase'])->value('total_cash_amount'), (float) DB::table('suppliers')->where('id', $c['s'])->value('opening_balance')],
            'setup' => fn () => $this->supplierFixture(false),
            'act' => fn ($c) => $this->post(route('purchase.payment.update'), ['purchase_id' => $c['purchase'], 'supplier_id' => $c['s'], 'payments' => [['method' => 'cash', 'amount' => 40, 'paid_on' => now()->toDateString()]]])];
    }

    // ---- customer payments, stock adjustments, courier -----------------------------------------------------------------------

    private function paidOrder(int $customerId, float $total): int
    {
        $o = new \App\Models\Order();
        $o->forceFill(['user_id' => $customerId, 'seller_id' => 1, 'code' => \App\Models\Order::generateUniqueCode(), 'delivery_status' => 'delivered', 'payment_status' => 'paid', 'payment_type' => 'cash_on_delivery', 'shipping_address' => '{}', 'grand_total' => $total, 'date' => time(), 'order_from' => 'app'])->save();
        return $o->id;
    }

    private function customerFixture(bool $withPayment): array
    {
        $cust = (int) DB::table('users')->insertGetId(['name' => 'cust', 'email' => uniqid() . '@mc.test', 'password' => 'x', 'user_type' => 'customer', 'created_at' => now(), 'updated_at' => now()]);
        $cash = $this->acct('cash', 0);
        $method = (int) DB::table('transaction_methods')->insertGetId(['name' => 'MC ' . uniqid(), 'type' => 'cash', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $c = ['cust' => $cust, 'cash' => $cash, 'order' => $this->paidOrder($cust, 1000), 'method' => $method];
        foreach (['Accounts Receivable' => ['asset', 'receivable'], 'Sales' => ['income', 'sales']] as $n => [$t, $sub]) {
            ChartOfAccount::firstOrCreate(['name' => $n], ['code' => 'MC-' . substr($n, 0, 3) . uniqid(), 'type' => $t, 'sub_type' => $sub, 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0]);
        }
        if ($withPayment) {
            $this->post(route('accounts.customer-payments.store'), $this->customerBody($c), ['Accept' => 'application/json'])->assertOk();
            $c['pay'] = (int) DB::table('customer_payments')->where('customer_id', $cust)->value('id');
        }
        return $c;
    }

    private function customerBody(array $c): array
    {
        return ['customer_id' => $c['cust'], 'amount' => 600, 'order_ids' => [$c['order']], 'payment_account_id' => $c['cash']->id, 'transaction_method_id' => $c['method'], 'payment_date' => now()->toDateString()];
    }

    private function customerObserve(array $c): array
    {
        return [$this->bal($c['cash']), DB::table('customer_payments')->where('customer_id', $c['cust'])->count(), (float) DB::table('order_payment_mappings')->where('order_id', $c['order'])->sum('amount_applied'), DB::table('vouchers')->whereIn('id', DB::table('customer_payments')->where('customer_id', $c['cust'])->pluck('voucher_id'))->count(), (string) DB::table('orders')->where('id', $c['order'])->value('payment_status')];
    }

    private function s_customer_payment_store(): array
    {
        return ['inject' => self::LEDGER, 'observe' => fn ($c) => $this->customerObserve($c), 'setup' => fn () => $this->customerFixture(false),
            'act' => fn ($c) => $this->post(route('accounts.customer-payments.store'), $this->customerBody($c), ['Accept' => 'application/json'])];
    }

    private function s_customer_payment_destroy(): array
    {
        return ['inject' => self::LEDGER, 'observe' => fn ($c) => $this->customerObserve($c), 'setup' => fn () => $this->customerFixture(true),
            'act' => fn ($c) => $this->delete(route('accounts.customer-payments.delete', $c['pay']), [], ['Accept' => 'application/json'])];
    }

    private function s_stock_adjustment_destroy(): array
    {
        $obs = fn ($c) => [(int) DB::table('product_stocks')->where('product_id', $c['p'])->sum('qty'), (int) DB::table('purchase_products')->where('product_id', $c['p'])->sum('remaining_qty'), DB::table('stock_adjustments')->where('id', $c['id'])->whereNull('deleted_at')->count()];
        return ['inject' => '/^update\s+`?product_stocks`?\s+set/i', 'observe' => $obs,
            'setup' => function () {
                config(['inventory.confirmed_only' => false]);
                \App\Services\Inventory\InventoryMode::reset();
                $p = $this->makeStockedProduct(['M' => 10], 1000, 400);
                $stockId = (int) DB::table('product_stocks')->where('product_id', $p->id)->where('variant', 'M')->value('id');
                $this->postJson(route('stock-adjustments.store'), ['transaction_date' => now()->toDateString(), 'adjustment_type' => 'normal', 'products' => [['product_id' => $p->id, 'product_stock_id' => $stockId, 'variant' => 'M', 'quantity' => 3, 'unit_price' => 400]]])->assertOk();
                return ['p' => $p->id, 'id' => (int) DB::table('stock_adjustments')->orderByDesc('id')->value('id')];
            },
            'act' => fn ($c) => $this->deleteJson(route('stock-adjustments.destroy', $c['id']))];
    }

    private function s_courier_unsettle(): array
    {
        return ['inject' => '/^update `chart_of_accounts`/i',
            'observe' => fn ($c) => [$this->bal($c['cash']), DB::table('courier_settlements')->where('order_id', $c['order'])->count(), DB::table('account_statement_logs')->where('order_id', $c['order'])->count(), (string) DB::table('orders')->where('id', $c['order'])->value('settlement_status')],
            'setup' => function () {
                $p = $this->productAtZero(['stock_policy' => 'backorder']);
                $order = $this->makeOrder([[$p, '', 5]], 'confirmed');
                $this->receive($p, '', 5, '100');
                $this->setStatus($order, 'picked_up');
                $cash = $this->acct('cash', 0);
                foreach ([['Sales', 'income', 'sales'], ['Accounts Receivable', 'asset', 'receivable'], ['Courier Income', 'income', 'income'], ['Cost of Goods Sold', 'expense', 'cost_of_sales']] as [$n, $t, $s]) {
                    ChartOfAccount::firstOrCreate(['name' => $n], ['code' => 'MC-' . strtoupper(substr($n, 0, 4)) . uniqid(), 'type' => $t, 'sub_type' => $s, 'opening_balance' => 0, 'current_balance' => 0, 'status' => 'active', 'is_deleted' => 0]);
                }
                DB::table('orders')->where('id', $order->id)->update(['courier_status' => 'delivered', 'courier_name' => 'steadfast', 'due_amount' => 5000, 'grand_total' => 5000]);
                $this->postJson(route('courier-settlement.settle'), ['order_id' => $order->id, 'courier_fee' => 60, 'account_id' => $cash->id, 'courier_name' => 'steadfast', 'settlement_fee' => 0, 'due_amount' => 5000, 'settlement_charge' => 0])->assertJson(['success' => true]);
                return ['cash' => $cash, 'order' => $order->id, 'sid' => (int) DB::table('courier_settlements')->where('order_id', $order->id)->value('id')];
            },
            'act' => fn ($c) => $this->postJson(route('courier-settlement.unsettle'), ['settlement_id' => $c['sid']])];
    }
    // ---- purchase orders -------------------------------------------------------------------------------------------------------

    private function poBody(int $supplierId, int $productId, int $qty, string $ref): array
    {
        return ['supplier_id' => $supplierId, 'reference_no' => $ref, 'purchase_date' => now()->toDateString(), 'business_location' => 'Main', 'product_id' => [$productId], 'name' => ['x'],
            'remaining_quantity' => [$qty], 'purchase_quantity' => [$qty], 'recieved_quantity' => [0], 'buying_unit_cost' => [20], 'total_cost' => [$qty * 20], 'tax' => [0],
            'payments' => [['amount' => 10, 'paid_on' => now()->toDateString(), 'method' => 'cash']]];
    }

    private function poObserve(array $c): array
    {
        $ids = DB::table('purchase_orders')->where('supplier_id', $c['s'])->pluck('id');
        return [$ids->count(), DB::table('purchase_order_details')->whereIn('purchase_order_id', $ids)->count(), (float) DB::table('purchase_order_details')->whereIn('purchase_order_id', $ids)->sum('total_cost'),
            (float) DB::table('purchase_orders')->where('supplier_id', $c['s'])->sum('total_payment_due'), (string) DB::table('suppliers')->where('id', $c['s'])->value('opening_balance')];
    }

    private function s_po_store(): array
    {
        return ['inject' => '/^insert into `purchase_order_details`/i', 'observe' => fn ($c) => $this->poObserve($c),
            'setup' => fn () => ['s' => $this->newSupplier(), 'p' => $this->productAtZero(), 'ref' => 'PO-' . uniqid()],
            'act' => fn ($c) => $this->post(route('purchase.order.store'), $this->poBody($c['s'], $c['p']->id, 5, $c['ref']), ['Accept' => 'application/json'])];
    }

    private function s_po_update(): array
    {
        return ['inject' => '/^insert into `purchase_order_details`/i', 'observe' => fn ($c) => $this->poObserve($c),
            'setup' => function () {
                $c = ['s' => $this->newSupplier(), 'p' => $this->productAtZero(), 'ref' => 'PO-' . uniqid()];
                $this->post(route('purchase.order.store'), $this->poBody($c['s'], $c['p']->id, 5, $c['ref']), ['Accept' => 'application/json'])->assertOk();
                $c['id'] = (int) DB::table('purchase_orders')->where('supplier_id', $c['s'])->value('id');
                return $c;
            },
            'act' => fn ($c) => $this->post(route('purchase.order.update', $c['id']), $this->poBody($c['s'], $c['p']->id, 8, $c['ref']), ['Accept' => 'application/json'])];
    }
    // ---- order edit / delete line / items status (legacy stock model: stock and cost layers move with the order) -----------------

    private function orderFixture(): array
    {
        config(['inventory.confirmed_only' => false]);
        \App\Services\Inventory\InventoryMode::reset();
        $product = $this->makeStockedProduct(['M' => 10], 1000, 400);
        $phone = '0179' . random_int(1000000, 9999999);
        $this->placeGuestOrder([[$product, 'M', 3]], [], $phone)->assertJson(['result' => true]);
        $order = \App\Models\Order::where('guest_id', $phone)->firstOrFail();
        $this->forgetAuth();
        $this->actingAs($this->makeAdmin()); // placing the guest order logged the session out

        return ['p' => $product, 'order' => $order, 'line' => \App\Models\OrderDetail::where('order_id', $order->id)->firstOrFail()];
    }

    private function orderObserve(array $c): array
    {
        return [$this->stockQty($c['p'], 'M'), $this->batchRemaining($c['p'], 'M'), DB::table('order_details')->where('id', $c['line']->id)->count(), (int) DB::table('order_details')->where('id', $c['line']->id)->value('quantity'), (string) DB::table('order_details')->where('id', $c['line']->id)->value('delivery_status')];
    }

    private function s_order_edit_update(): array
    {
        return ['inject' => '/^update\s+`?product_stocks`?\s+set/i', 'observe' => fn ($c) => $this->orderObserve($c), 'setup' => fn () => $this->orderFixture(),
            'act' => fn ($c) => $this->post(route('order.edit.update'), ['id' => $c['order']->id, 'grand_total' => '9,999', 'shipping_cost' => '0', 'name' => 'Buyer', 'email' => 'buyer@example.com', 'address' => 'House 1', 'phone' => '01712345678',
                'order_detail_id' => [$c['line']->id], 'product_id' => [$c['p']->id], 'variation' => ['M'], 'qty' => [5], 'total' => ['5,000']])];
    }

    private function s_order_edit_delete(): array
    {
        return ['inject' => '/^update\s+`?product_stocks`?\s+set/i', 'observe' => fn ($c) => $this->orderObserve($c), 'setup' => fn () => $this->orderFixture(),
            'act' => fn ($c) => $this->post(route('order.edit.delete'), ['id' => $c['line']->id], ['Accept' => 'application/json'])];
    }

    private function s_order_items_status(): array
    {
        return ['inject' => '/^update\s+`?product_stocks`?\s+set/i', 'observe' => fn ($c) => $this->orderObserve($c), 'setup' => fn () => $this->orderFixture(),
            'act' => fn ($c) => $this->post(route('orders.update-items-status'), ['order_id' => $c['order']->id, 'updates' => [['order_detail_id' => $c['line']->id, 'delivery_status' => 'cancelled']]], ['Accept' => 'application/json'])];
    }
}
