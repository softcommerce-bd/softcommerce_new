<?php

namespace Tests\Feature\Inventory;

use App\Models\ChartOfAccount;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\Money;
use App\Services\Accounting\SystemAccounts;
use App\Services\Inventory\InventoryReconciliation;
use App\Services\Purchasing\GoodsReceiptService;
use App\Services\Purchasing\PurchaseOrderService;
use App\Services\Purchasing\PurchaseRequirementsService;
use App\Services\Purchasing\SupplierLedgerService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchasingAccountingTest extends InventoryV2TestCase
{
    private int $cash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cash = ChartOfAccount::create(['code' => 'T-CASH-' . uniqid(), 'name' => 'Test cash ' . uniqid(), 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 100000, 'status' => 'active', 'is_deleted' => 0])->id;
    }

    private function bal(int $id): float
    {
        return (float) DB::table('chart_of_accounts')->where('id', $id)->value('current_balance');
    }

    private function po(array $lines, string $status = 'ordered', ?string $key = null): array
    {
        return app(PurchaseOrderService::class)->create($key ?? 'po-' . uniqid(), ['supplier_id' => $this->supplierId, 'status' => $status, 'lines' => $lines]);
    }

    public function test_draft_and_ordered_purchase_orders_change_neither_stock_nor_books()
    {
        $p = $this->productAtZero();
        $before = [DB::table('journal_entries')->count(), DB::table('purchase_products')->count(), $this->stockQty($p, ''), $this->bal(SystemAccounts::id('inventory')), $this->bal(SystemAccounts::id('payable'))];
        $this->po([['product_id' => $p->id, 'qty' => 10, 'unit_cost' => '50']], 'draft');
        $this->po([['product_id' => $p->id, 'qty' => 10, 'unit_cost' => '50']], 'ordered');
        $after = [DB::table('journal_entries')->count(), DB::table('purchase_products')->count(), $this->stockQty($p, ''), $this->bal(SystemAccounts::id('inventory')), $this->bal(SystemAccounts::id('payable'))];
        $this->assertSame($before, $after);
        $this->assertSame(10, $this->snap($p)['incoming'], 'only the ORDERED one counts as incoming; the draft does not');
        $this->assertSame(0, $this->snap($p)['on_hand'], 'incoming is not physical stock');
    }

    public function test_partial_receiving_updates_the_order_and_receiving_twice_does_not_duplicate()
    {
        $p = $this->productAtZero();
        $po = $this->po([['product_id' => $p->id, 'qty' => 10, 'unit_cost' => '50']]);
        $detail = DB::table('purchase_order_details')->where('purchase_order_id', $po['purchase_order_id'])->first();
        $line = fn ($q) => ['purchase_order_detail_id' => $detail->id, 'product_id' => $p->id, 'qty' => $q, 'unit_cost' => '50'];
        $svc = app(GoodsReceiptService::class);

        $svc->receive('rcv-1', ['supplier_id' => $this->supplierId, 'purchase_order_id' => $po['purchase_order_id'], 'lines' => [$line(4)]]);
        $again = $svc->receive('rcv-1', ['supplier_id' => $this->supplierId, 'purchase_order_id' => $po['purchase_order_id'], 'lines' => [$line(4)]]); // double click / retry
        $this->assertTrue($again['replayed']);
        $this->assertSame(4, $this->stockQty($p, ''), 'the same receipt key adds stock once');
        $this->assertSame(1, DB::table('goods_receipts')->count());
        $this->assertSame('partially_received', DB::table('purchase_orders')->where('id', $po['purchase_order_id'])->value('status'));
        $this->assertEquals(6, DB::table('purchase_order_details')->where('id', $detail->id)->value('pending_quantity'));

        try {
            $svc->receive('rcv-2', ['supplier_id' => $this->supplierId, 'purchase_order_id' => $po['purchase_order_id'], 'lines' => [$line(7)]]);
            $this->fail('receiving more than is pending must be refused');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(4, $this->stockQty($p, ''), 'a refused receipt changes nothing');
        }
        $svc->receive('rcv-3', ['supplier_id' => $this->supplierId, 'purchase_order_id' => $po['purchase_order_id'], 'lines' => [$line(6)]]);
        $this->assertSame([10, 'received'], [$this->stockQty($p, ''), DB::table('purchase_orders')->where('id', $po['purchase_order_id'])->value('status')]);
        $this->assertSame(0, $this->snap($p)['incoming']);
    }

    public function test_receiving_creates_a_fifo_layer_at_actual_cost_and_a_balanced_entry_without_a_payable()
    {
        $p = $this->productAtZero();
        $r = $this->receive($p, '', 10, '65.5');
        $layer = DB::table('purchase_products')->where('product_id', $p->id)->first();
        $this->assertSame([10, '65.5000'], [(int) $layer->remaining_qty, $layer->buying_unit_cost]);
        $inv = SystemAccounts::id('inventory');
        $this->assertEquals(655.0, $this->bal($inv));
        $this->assertEquals(655.0, $this->bal(SystemAccounts::id('grni')), 'goods are owed through the clearing account, not Accounts Payable');
        $this->assertEquals(0.0, $this->bal(SystemAccounts::id('payable')));
        $this->assertEquals(0.0, (float) DB::table('suppliers')->where('id', $this->supplierId)->value('due_amount'), 'no supplier due until the invoice is recorded');
        $this->assertTrue(JournalService::trialBalance(now()->toDateString())['balanced']);
        $this->assertSame(0, DB::table('stock_movements')->where('type', 'goods_received')->where('source_id', $r['goods_receipt_id'])->where('on_hand_delta', '<>', 10)->count());
    }

    public function test_landed_cost_is_capitalised_into_the_layers_in_proportion_and_the_books_balance()
    {
        $a = $this->productAtZero();
        $b = $this->productAtZero();
        app(GoodsReceiptService::class)->receive('landed-1', ['supplier_id' => $this->supplierId, 'landed_costs' => ['amount' => '30', 'account_id' => $this->cash],
            'lines' => [['product_id' => $a->id, 'qty' => 10, 'unit_cost' => '100'], ['product_id' => $b->id, 'qty' => 5, 'unit_cost' => '200']]]);
        $la = (float) DB::table('purchase_products')->where('product_id', $a->id)->value('buying_unit_cost');
        $lb = (float) DB::table('purchase_products')->where('product_id', $b->id)->value('buying_unit_cost');
        $this->assertEquals(2030.0, round($la * 10 + $lb * 5, 2), 'layers carry goods 2000 + freight 30 = 2030');
        $this->assertEqualsWithDelta(101.5, $la, 0.0001);
        $this->assertEqualsWithDelta(203.0, $lb, 0.0001, 'each product has half the goods value, so each takes 15 of the 30');
        $this->assertEquals(2030.0, $this->bal(SystemAccounts::id('inventory')));
        $this->assertEquals(2000.0, $this->bal(SystemAccounts::id('grni')));
        $this->assertEquals(99970.0, $this->bal($this->cash), 'the 30 of freight came out of the account that bore it');
    }

    public function test_invoice_clears_grni_posts_the_payable_and_records_a_price_difference()
    {
        $p = $this->productAtZero();
        $r = $this->receive($p, '', 10, '100'); // goods 1000
        $svc = app(SupplierLedgerService::class);
        $inv = $svc->recordInvoice('inv-1', ['supplier_id' => $this->supplierId, 'goods_receipt_id' => $r['goods_receipt_id'], 'invoice_no' => 'S-1', 'subtotal' => '1050']);
        $this->assertEquals(0.0, $this->bal(SystemAccounts::id('grni')), 'the receipt clearing account is cleared');
        $this->assertEquals(1050.0, $this->bal(SystemAccounts::id('payable')));
        $this->assertEquals(50.0, $this->bal(SystemAccounts::id('variance')), 'the 50 difference is a visible variance; physical stock and FIFO layers are untouched');
        $this->assertEquals(1050.0, (float) DB::table('suppliers')->where('id', $this->supplierId)->value('due_amount'));
        $this->assertSame(10, $this->stockQty($p, ''));
        $this->assertEquals(100.0, (float) DB::table('purchase_products')->where('product_id', $p->id)->value('buying_unit_cost'));

        $this->expectException(InvalidArgumentException::class);
        $svc->recordInvoice('inv-2', ['supplier_id' => $this->supplierId, 'goods_receipt_id' => $r['goods_receipt_id'], 'invoice_no' => 'S-2', 'subtotal' => '1000']); // receipt already invoiced
    }

    public function test_duplicate_invoice_number_and_replayed_invoice_post_once()
    {
        $p = $this->productAtZero();
        $r = $this->receive($p, '', 1, '10');
        $svc = app(SupplierLedgerService::class);
        $svc->recordInvoice('same', ['supplier_id' => $this->supplierId, 'goods_receipt_id' => $r['goods_receipt_id'], 'invoice_no' => 'A1', 'subtotal' => '10']);
        $this->assertTrue($svc->recordInvoice('same', ['supplier_id' => $this->supplierId, 'goods_receipt_id' => $r['goods_receipt_id'], 'invoice_no' => 'A1', 'subtotal' => '10'])['replayed']);
        $this->assertSame(1, DB::table('supplier_invoices')->count());
        $this->assertEquals(10.0, $this->bal(SystemAccounts::id('payable')));
        $r2 = $this->receive($p, '', 1, '10');
        $this->expectException(InvalidArgumentException::class);
        $svc->recordInvoice('other', ['supplier_id' => $this->supplierId, 'goods_receipt_id' => $r2['goods_receipt_id'], 'invoice_no' => 'A1', 'subtotal' => '10']);
    }

    public function test_partial_payments_reduce_payable_and_cash_and_are_never_expenses_and_retries_do_not_double_pay()
    {
        $p = $this->productAtZero();
        $r = $this->receive($p, '', 10, '100');
        $svc = app(SupplierLedgerService::class);
        $inv = $svc->recordInvoice('i', ['supplier_id' => $this->supplierId, 'goods_receipt_id' => $r['goods_receipt_id'], 'invoice_no' => 'P1', 'subtotal' => '1000'])['supplier_invoice_id'];
        $expensesBefore = (float) DB::table('chart_of_accounts')->where('type', 'expense')->sum('current_balance');

        $svc->pay('pay-1', $inv, $this->cash, '400');
        $this->assertTrue($svc->pay('pay-1', $inv, $this->cash, '400')['replayed'], 'a double-submitted payment is recognised');
        $this->assertEquals(100000 - 400, $this->bal($this->cash));
        $this->assertEquals(600.0, $this->bal(SystemAccounts::id('payable')));
        $this->assertEquals(600.0, (float) DB::table('suppliers')->where('id', $this->supplierId)->value('due_amount'));
        $this->assertSame('partial', DB::table('supplier_invoices')->where('id', $inv)->value('status'));
        $svc->pay('pay-2', $inv, $this->cash, '600');
        $this->assertSame('paid', DB::table('supplier_invoices')->where('id', $inv)->value('status'));
        $this->assertEquals(0.0, $this->bal(SystemAccounts::id('payable')));
        $this->assertEquals($expensesBefore, (float) DB::table('chart_of_accounts')->where('type', 'expense')->sum('current_balance'), 'a payment is not a purchase expense');
        $this->assertEquals('0.00', SupplierLedgerService::outstanding($this->supplierId));
        try {
            $svc->pay('pay-3', $inv, $this->cash, '1');
            $this->fail('overpaying must be refused');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals(100000 - 1000, $this->bal($this->cash));
        }
    }

    public function test_payment_from_an_account_without_funds_is_refused_and_posts_nothing()
    {
        $p = $this->productAtZero();
        $r = $this->receive($p, '', 1, '10');
        $svc = app(SupplierLedgerService::class);
        $inv = $svc->recordInvoice('i', ['supplier_id' => $this->supplierId, 'goods_receipt_id' => $r['goods_receipt_id'], 'invoice_no' => 'F1', 'subtotal' => '10'])['supplier_invoice_id'];
        $poor = ChartOfAccount::create(['code' => 'T-POOR-' . uniqid(), 'name' => 'Poor ' . uniqid(), 'type' => 'asset', 'sub_type' => 'bank', 'opening_balance' => 0, 'current_balance' => 5, 'status' => 'active', 'is_deleted' => 0])->id;
        $entries = DB::table('journal_entries')->count();
        try {
            $svc->pay('poor-1', $inv, $poor, '10');
            $this->fail('expected refusal');
        } catch (InvalidArgumentException $e) {
            $this->assertSame($entries, DB::table('journal_entries')->count());
            $this->assertSame(0, DB::table('idempotency_keys')->where('key', 'poor-1')->count(), 'a failed operation leaves no key behind, so a corrected retry works');
        }
        $this->expectException(InvalidArgumentException::class); // a non-money account is not a payment source
        $svc->pay('poor-2', $inv, SystemAccounts::id('inventory'), '1');
    }

    public function test_supplier_advance_then_applied_to_an_invoice_and_one_payment_across_invoices()
    {
        $p = $this->productAtZero();
        $svc = app(SupplierLedgerService::class);
        $svc->advance('adv-1', $this->supplierId, $this->cash, '300');
        $this->assertEquals(300.0, (float) DB::table('suppliers')->where('id', $this->supplierId)->value('opening_balance'));
        $this->assertEquals(300.0, $this->bal(SystemAccounts::id('advance')), 'an advance is an asset (money the supplier holds for us), not an expense');
        $this->assertEquals(0.0, $this->bal(SystemAccounts::id('payable')));

        $mk = fn ($no, $amt) => $svc->recordInvoice('mk-' . $no, ['supplier_id' => $this->supplierId, 'goods_receipt_id' => $this->receive($p, '', 1, $amt)['goods_receipt_id'], 'invoice_no' => $no, 'subtotal' => $amt])['supplier_invoice_id'];
        $i1 = $mk('M1', '200');
        $i2 = $mk('M2', '500');
        $svc->applyAdvance('app-1', $i1, '200');
        $this->assertSame('paid', DB::table('supplier_invoices')->where('id', $i1)->value('status'));
        $this->assertEquals(100.0, $this->bal(SystemAccounts::id('advance')));
        try {
            $svc->applyAdvance('app-2', $i2, '150');
            $this->fail('more than the advance held');
        } catch (InvalidArgumentException $e) {
        }
        $svc->payMany('many-1', $this->supplierId, $this->cash, [['invoice_id' => $i2, 'amount' => '300']]);
        $this->assertEquals(200.0, $this->bal(SystemAccounts::id('payable')));
        $this->assertEquals('200.00', SupplierLedgerService::outstanding($this->supplierId));
        $this->assertTrue(JournalService::trialBalance(now()->toDateString())['balanced']);
    }

    public function test_journal_rejects_unbalanced_or_malformed_entries_and_writes_nothing()
    {
        $j = app(JournalService::class);
        $cash = $this->cash;
        $before = [DB::table('journal_entries')->count(), DB::table('journal_lines')->count(), $this->bal($cash)];
        foreach ([[['account' => $cash, 'debit' => 100], ['account' => SystemAccounts::id('payable'), 'credit' => 99]], [['account' => $cash, 'debit' => 100]], [['account' => $cash, 'debit' => 100, 'credit' => 100], ['account' => SystemAccounts::id('payable'), 'credit' => 0]], [['account' => $cash, 'debit' => -5], ['account' => SystemAccounts::id('payable'), 'credit' => -5]]] as $i => $lines) {
            try {
                $j->post("bad-$i", '2026-01-01', $lines);
                $this->fail("entry $i should have been rejected");
            } catch (InvalidArgumentException $e) {
                $this->assertTrue(true);
            }
        }
        try { // account that does not exist: nothing may be half-written
            $j->post('bad-acct', '2026-01-01', [['account' => $cash, 'debit' => 100], ['account' => 99999999, 'credit' => 100]]);
            $this->fail('unknown account');
        } catch (InvalidArgumentException $e) {
        }
        $this->assertSame($before, [DB::table('journal_entries')->count(), DB::table('journal_lines')->count(), $this->bal($cash)]);
    }

    public function test_reversal_posts_the_mirror_once_and_history_is_reported_as_of_a_date()
    {
        $j = app(JournalService::class);
        $payable = SystemAccounts::id('payable');
        $e = $j->post('rev-src', '2026-03-01', [['account' => $this->cash, 'debit' => 50000], ['account' => $payable, 'credit' => 50000]], 'test', 1, 'src');
        $this->assertEquals(500.0, $this->bal($payable));
        $this->assertSame('500.00', JournalService::balanceAsOf($payable, '2026-03-01'));
        $this->assertSame('0.00', JournalService::balanceAsOf($payable, '2026-02-28'), 'history as of an earlier date does not include later postings');
        $j->reverse($e['journal_entry_id'], 'rev-1', 'mistake');
        $this->assertEquals(0.0, $this->bal($payable));
        $this->assertTrue($j->reverse($e['journal_entry_id'], 'rev-1', 'mistake')['replayed']);
        $this->expectException(InvalidArgumentException::class);
        $j->reverse($e['journal_entry_id'], 'rev-2', 'again'); // a second, different key still cannot reverse it twice
    }

    public function test_money_arithmetic_is_exact()
    {
        $this->assertSame(1000001, Money::micro('100.0001'));
        $this->assertSame(30, Money::cents(Money::micro(0.1) + Money::micro(0.2)), '0.1 + 0.2 is exactly 0.30');
        $this->assertSame([3334, 3333, 3333], array_values(Money::allocate(10000, [1, 1, 1])), 'allocation never loses a cent');
        $this->expectException(InvalidArgumentException::class);
        Money::micro('1.23456');
    }

    public function test_requirements_exclude_pending_and_cancelled_and_never_double_count_across_orders()
    {
        $p = $this->productAtZero(['stock_policy' => 'backorder', 'preferred_supplier_id' => $this->supplierId]);
        $this->makeOrder([[$p, '', 4]]);                         // pending: not a requirement
        $o1 = $this->makeOrder([[$p, '', 3]], 'confirmed');
        $o2 = $this->makeOrder([[$p, '', 2]], 'confirmed');
        $o3 = $this->makeOrder([[$p, '', 9]], 'confirmed');
        $this->setStatus($o3, 'cancelled');                      // cancelled: not a requirement
        $svc = app(PurchaseRequirementsService::class);

        $items = $svc->items(['only_shortage' => true]);
        $this->assertCount(1, $items);
        $this->assertSame([5, 5, 0], [$items[0]['demand'], $items[0]['shortage'], $items[0]['incoming']]);
        $this->assertEqualsCanonicalizing([$o1->code, $o2->code], $items[0]['order_codes']);
        $this->assertSame(['needs_purchase', 'needs_purchase'], array_column($items[0]['rows'], 'status'));

        // Buy for the requirement once: the shortage is covered and cannot be bought again.
        $demandIds = array_column($items[0]['rows'], 'demand_id');
        $po = $this->po([['product_id' => $p->id, 'qty' => 5, 'unit_cost' => '70', 'demand_ids' => $demandIds]]);
        $items = $svc->items();
        $this->assertSame([5, 0], [$items[0]['incoming'], $items[0]['shortage']]);
        $this->assertSame(['purchase_ordered', 'purchase_ordered'], array_column($items[0]['rows'], 'status'));
        $this->assertSame([], $svc->items(['only_shortage' => true]), 'covered demand disappears from the shortage list');
        $this->assertSame(0, array_sum(array_column($items[0]['rows'], 'uncovered')));
        $this->expectException(InvalidArgumentException::class);
        $this->po([['product_id' => $p->id, 'qty' => 5, 'unit_cost' => '70', 'demand_ids' => [$demandIds[0]]]]);
        $this->assertSame(1, DB::table('purchase_requirement_links')->where('order_demand_id', $demandIds[0])->count());
    }

    public function test_requirement_statuses_follow_real_quantities_through_partial_receipt_and_cancellation()
    {
        $p = $this->productAtZero(['stock_policy' => 'backorder']);
        $o1 = $this->makeOrder([[$p, '', 3]], 'confirmed');
        $o2 = $this->makeOrder([[$p, '', 3]], 'confirmed');
        $ids = DB::table('order_demands')->orderBy('id')->pluck('id')->all();
        $po = $this->po([['product_id' => $p->id, 'qty' => 6, 'unit_cost' => '70', 'demand_ids' => $ids]]);
        $detail = DB::table('purchase_order_details')->where('purchase_order_id', $po['purchase_order_id'])->first();
        app(GoodsReceiptService::class)->receive('part', ['supplier_id' => $this->supplierId, 'purchase_order_id' => $po['purchase_order_id'], 'lines' => [['purchase_order_detail_id' => $detail->id, 'product_id' => $p->id, 'qty' => 4, 'unit_cost' => '70']]]);

        $st = fn () => array_column(app(PurchaseRequirementsService::class)->items()[0]['rows'], 'status');
        $this->assertSame(['ready_for_fulfillment', 'partially_received'], $st(), 'oldest order is complete (3), the next has 1 of 3');

        $this->setStatus($o2, 'cancelled');                      // demand cancelled while the purchase is in progress
        $this->assertSame(['ready_for_fulfillment'], $st());
        $this->assertSame(1, app(\App\Services\Inventory\StockBalance::class)::snapshot($p->id, '')['free'], 'the unit that was for the cancelled order is free stock now');
        $this->setStatus($o1, 'picked_up');
        $this->assertSame([], app(PurchaseRequirementsService::class)->items());
        $this->assertSame([], app(InventoryReconciliation::class)->stockInvariantViolations());
    }

    public function test_reconcile_report_is_clean_after_a_full_cycle()
    {
        $p = $this->productAtZero(['stock_policy' => 'backorder']);
        $o = $this->makeOrder([[$p, '', 2]], 'confirmed');
        $r = $this->receive($p, '', 2, '100');
        $svc = app(SupplierLedgerService::class);
        $inv = $svc->recordInvoice('rc-i', ['supplier_id' => $this->supplierId, 'goods_receipt_id' => $r['goods_receipt_id'], 'invoice_no' => 'RC', 'subtotal' => '200'])['supplier_invoice_id'];
        $svc->pay('rc-p', $inv, $this->cash, '200');
        $this->setStatus($o, 'delivered');
        $report = app(InventoryReconciliation::class)->report();
        foreach (['v2_invariants', 'journal_balance', 'unbalanced_entries', 'negative_physical', 'uncosted_dispatches'] as $k) {
            $this->assertSame('ok', $report[$k]['severity'], $k . ': ' . json_encode($report[$k]));
        }
        $this->assertSame([], DB::select('SELECT journal_entry_id FROM journal_lines GROUP BY journal_entry_id HAVING ROUND(SUM(debit) - SUM(credit), 2) <> 0'));
    }
}
