<?php
// Test-only seed for tests/ui/admin-browser-suite.js (a TEST database; creates suppliers, receipts, an ordered PO, a backorder product). Returns the ids.
use Illuminate\Support\Facades\DB;
use App\Services\Purchasing\{GoodsReceiptService, PurchaseOrderService};
$mk = fn ($n) => DB::table('suppliers')->insertGetId(['slug' => 'e2e-' . $n . uniqid(), 'business_name' => "E2E Supplier $n", 'contact_person_name' => 'Rahim', 'mobile' => '0171' . random_int(1000000, 9999999), 'opening_balance' => '0', 'due_amount' => 0, 'paid_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
$s1 = $mk('Alpha ' . ($run = substr(uniqid(), -5))); $s2 = $mk('Beta ' . $run);
foreach ([['E2E Cash', 'cash', 50000], ['E2E Bank', 'bank', 90000]] as [$n, $sub, $bal]) {
    if (!DB::table('chart_of_accounts')->where('name', $n)->exists()) DB::table('chart_of_accounts')->insert(['code' => 'E2E-' . strtoupper($sub), 'name' => $n, 'type' => 'asset', 'sub_type' => $sub, 'opening_balance' => 0, 'current_balance' => $bal, 'status' => 'active', 'is_deleted' => 0, 'created_at' => now(), 'updated_at' => now()]);
}
$prods = DB::table('products')->where('name', 'like', 'SAMPLE%')->orderBy('id')->limit(6)->get();
$grn = app(GoodsReceiptService::class); $po = app(PurchaseOrderService::class);
// received (uninvoiced) goods for supplier Alpha
$grn->receive('e2e-grn-' . $run, ['supplier_id' => $s1, 'lines' => [['product_id' => $prods[0]->id, 'variant' => '', 'qty' => 10, 'unit_cost' => '120']]]);
// an ordered PO with open lines for Beta
$po->create('e2e-po-' . $run, ['supplier_id' => $s2, 'status' => 'ordered', 'lines' => [['product_id' => $prods[1]->id, 'qty' => 8, 'unit_cost' => '75'], ['product_id' => $prods[2]->id, 'qty' => 5, 'unit_cost' => '40']]]);
// a backorder product with confirmed demand -> purchase requirement
DB::table('products')->where('id', $prods[3]->id)->update(['stock_policy' => 'backorder', 'preferred_supplier_id' => $s1, 'estimated_purchase_cost' => 90]);
return (['run' => $run, 's1' => $s1, 's2' => $s2, 'prods' => $prods->pluck('id', 'name')->all()]);
