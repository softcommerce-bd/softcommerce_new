<?php

namespace Tests\Feature\Inventory;

use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

/** The invoice / pay / advance / return-to-supplier screens, through their routes. */
class SupplierPaymentsScreenTest extends InventoryV2TestCase
{
    private function cash(): ChartOfAccount
    {
        return ChartOfAccount::create(['code' => 'SP-' . uniqid(), 'name' => 'SP cash ' . uniqid(), 'type' => 'asset', 'sub_type' => 'cash', 'opening_balance' => 0, 'current_balance' => 5000, 'status' => 'active', 'is_deleted' => 0]);
    }

    public function test_record_invoice_then_pay_in_parts_and_a_repeated_submit_pays_once()
    {
        $p = $this->productAtZero();
        $r = $this->receive($p, '', 10, '50');
        $cash = $this->cash();
        $this->actingAs($this->makeAdmin());
        $this->get(route('purchasing.supplier.payments', ['supplier_id' => $this->supplierId]))->assertOk()->assertSee('Record invoice');
        $this->post(route('purchasing.invoices.store'), ['op_key' => 'i1', 'supplier_id' => $this->supplierId, 'goods_receipt_id' => $r['goods_receipt_id'], 'invoice_no' => 'UI-1', 'subtotal' => 500])->assertRedirect();
        $inv = DB::table('supplier_invoices')->where('invoice_no', 'UI-1')->first();
        $this->assertEquals(500.0, (float) $inv->total);
        $this->post(route('purchasing.invoices.store'), ['op_key' => 'i1', 'supplier_id' => $this->supplierId, 'goods_receipt_id' => $r['goods_receipt_id'], 'invoice_no' => 'UI-1', 'subtotal' => 500]);
        $this->assertSame(1, DB::table('supplier_invoices')->where('invoice_no', 'UI-1')->count(), 'double submit records one invoice');

        $this->post(route('purchasing.invoices.pay', $inv->id), ['op_key' => 'p1', 'account_id' => $cash->id, 'amount' => 200])->assertRedirect();
        $this->post(route('purchasing.invoices.pay', $inv->id), ['op_key' => 'p1', 'account_id' => $cash->id, 'amount' => 200]);
        $this->assertEquals(4800.0, (float) $cash->fresh()->current_balance, 'the repeated payment did not leave twice');
        $this->post(route('purchasing.invoices.pay', $inv->id), ['op_key' => 'p2', 'account_id' => $cash->id, 'amount' => 999]); // more than the 300 left
        $this->assertEquals(4800.0, (float) $cash->fresh()->current_balance, 'an overpayment is refused');
        $this->assertSame('partial', DB::table('supplier_invoices')->where('id', $inv->id)->value('status'));
        $this->post(route('purchasing.invoices.pay', $inv->id), ['op_key' => 'p3', 'account_id' => $cash->id, 'amount' => 300]);
        $this->assertSame('paid', DB::table('supplier_invoices')->where('id', $inv->id)->value('status'));
        $this->post(route('purchasing.invoices.pay', $inv->id), ['op_key' => 'p4', 'account_id' => $cash->id, 'amount' => 0])->assertSessionHasErrors('amount');
        $this->post(route('purchasing.advances.store'), ['op_key' => 'a1', 'supplier_id' => $this->supplierId, 'account_id' => $cash->id, 'amount' => 100])->assertRedirect();
        $this->assertEquals(4400.0, (float) $cash->fresh()->current_balance);
    }

    public function test_return_to_supplier_screen_returns_units_once_and_refuses_more_than_returnable()
    {
        $p = $this->productAtZero();
        $r = $this->receive($p, '', 6, '10');
        $line = (int) DB::table('goods_receipt_lines')->where('goods_receipt_id', $r['goods_receipt_id'])->value('id');
        $this->actingAs($this->makeAdmin());
        $this->get(route('purchasing.supplier-returns'))->assertOk()->assertSee('receipt-lines', false);
        $this->post(route('purchasing.supplier-returns.store'), ['op_key' => 'r1', 'receipt_line_id' => $line, 'qty' => 2])->assertRedirect();
        $this->post(route('purchasing.supplier-returns.store'), ['op_key' => 'r1', 'receipt_line_id' => $line, 'qty' => 2]);
        $this->assertSame(4, $this->stockQty($p, ''), 'a repeated submit returns the 2 units once');
        $this->post(route('purchasing.supplier-returns.store'), ['op_key' => 'r2', 'receipt_line_id' => $line, 'qty' => 9])->assertSessionHas('flash_notification');
        $this->assertSame(4, $this->stockQty($p, ''));
    }
}
