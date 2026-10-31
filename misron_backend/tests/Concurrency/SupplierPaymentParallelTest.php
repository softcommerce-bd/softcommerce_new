<?php

namespace Tests\Concurrency;

use Illuminate\Support\Facades\DB;

/** T-5: real parallel supplier payments. A purchase can never be paid beyond its due, a number is never used twice, no balance update is lost. */
class SupplierPaymentParallelTest extends HttpWorkflowCase
{
    private function supplier(): int
    {
        return (int) DB::table('suppliers')->insertGetId(['slug' => 'sp-' . uniqid(), 'business_name' => 'SP', 'contact_person_name' => 'C', 'mobile' => '017' . random_int(10000000, 99999999), 'opening_balance' => '0', 'due_amount' => 0, 'paid_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** a purchase of 5 x 20 = 100 on credit, created through the real screen */
    private function purchaseOn(int $supplier, int $admin): int
    {
        $p = $this->seedProduct(['M' => 0], 1000, 100);
        DB::table('purchase_products')->where('product_id', $p)->delete();
        $r = $this->runOne($this->req($admin, '/purchase/store', ['op_key' => 'sp-' . uniqid(), 'supplier_id' => $supplier, 'purchase_date' => now()->toDateString(), 'product_id' => [$p], 'name' => ['x'], 'variant' => ['M'],
            'purchase_quantity' => [5], 'buying_unit_cost' => [20], 'total_cost' => [100]]));
        $this->assertTrue($r['body']['success'] ?? false, json_encode($r));

        return (int) DB::table('purchases')->where('supplier_id', $supplier)->value('id');
    }

    private function pay(int $admin, int $supplier, int $purchase, float $amount, int $account): array
    {
        return $this->req($admin, '/admin/accounts/supplier-payments/store', ['supplier_id' => $supplier, 'payment_date' => now()->toDateString(), 'amount' => $amount, 'payment_method' => 'CASH', 'account_head_id' => $account,
            'allocations' => json_encode([['purchase_id' => $purchase, 'paid_amount' => $amount]])]);
    }

    public function test_four_payments_of_70_against_a_100_invoice_pay_it_at_most_once()
    {
        $this->flag(true);
        $s = $this->supplier();
        $admin = $this->admin();
        $purchase = $this->purchaseOn($s, $admin);
        $cash = $this->cashAccount(1000);

        $r = $this->race(array_map(fn () => $this->pay($this->admin(), $s, $purchase, 70, $cash), range(1, 4)), 120);

        $this->assertSame(1, DB::table('supplier_payments')->where('supplier_id', $s)->count(), 'only one 70 fits in a 100 invoice: ' . json_encode($r));
        $this->assertEquals(30.0, (float) DB::table('purchases')->where('id', $purchase)->value('total_payment_due'));
        $this->assertEquals(30.0, (float) DB::table('suppliers')->where('id', $s)->value('due_amount'));
        $this->assertEquals(930.0, $this->bal($cash), 'cash paid once');
        $this->assertJournalSettles();
    }

    public function test_payments_to_different_suppliers_at_once_all_succeed_with_unique_numbers_and_no_lost_cash()
    {
        $this->flag(true);
        $admin = $this->admin();
        $suppliers = [$this->supplier(), $this->supplier(), $this->supplier()];
        $purchases = array_map(fn ($s) => $this->purchaseOn($s, $admin), $suppliers);
        $cash = $this->cashAccount(1000);

        $r = $this->race(array_map(fn ($i) => $this->pay($this->admin(), $suppliers[$i], $purchases[$i], 40, $cash), [0, 1, 2]), 120);

        $this->assertSame(3, DB::table('supplier_payments')->whereIn('supplier_id', $suppliers)->count(), json_encode($r));
        $numbers = DB::table('supplier_payments')->whereIn('supplier_id', $suppliers)->pluck('payment_no');
        $this->assertCount(3, $numbers->unique(), 'no payment number used twice');
        $this->assertEquals(1000 - 120, $this->bal($cash), 'no payment lost from the shared account');
        foreach ($suppliers as $s) {
            $this->assertEquals(60.0, (float) DB::table('suppliers')->where('id', $s)->value('due_amount'));
        }
        $this->assertJournalSettles();
    }
}
