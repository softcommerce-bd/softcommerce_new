<?php
// Test-only (isolated TEST database): stock and policies so the storefront shows every availability state.
use Illuminate\Support\Facades\DB;
use App\Services\Purchasing\GoodsReceiptService;
use App\Models\{Order, OrderDetail, User};
$grn = app(GoodsReceiptService::class); $run = substr(uniqid(), -5);
$sup = DB::table('suppliers')->insertGetId(['slug' => 'sf-' . $run, 'business_name' => 'SF Supplier ' . $run, 'contact_person_name' => 'x', 'mobile' => '01700000000', 'opening_balance' => '0', 'due_amount' => 0, 'paid_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
$recv = fn ($pid, $variant, $qty) => $grn->receive("sf-$run-$pid-$variant", ['supplier_id' => $sup, 'lines' => [['product_id' => $pid, 'variant' => $variant, 'qty' => $qty, 'unit_cost' => '300']]]);
$recv(5327, '', 10);                       // in stock
foreach (['S' => 5, 'M' => 0, 'L' => 3] as $v => $q) { if ($q) $recv(5331, $v, $q); }   // variations: S in stock, M none, L in stock
DB::table('products')->whereIn('id', [5327, 5329, 5331, 5334])->update(['stock_policy' => 'stock_only']);
DB::table('products')->where('id', 5328)->update(['stock_policy' => 'backorder', 'max_backorder_qty' => 5]);   // zero stock, backorder with a limit
DB::table('products')->where('id', 5330)->update(['stock_policy' => 'backorder']);                              // negative available (confirmed demand 6, stock 0)
DB::table('products')->where('id', 5333)->update(['stock_policy' => 'preorder', 'max_preorder_qty' => 5, 'preorder_available_at' => now()->addDays(30)->toDateString()]);
$u = User::where('user_type', 'customer')->first() ?? tap(new User, fn ($x) => $x->forceFill(['name' => 'SF Cust', 'email' => 'sf' . $run . '@e2e.test', 'password' => bcrypt('x'), 'user_type' => 'customer', 'banned' => 0])->save());
$o = new Order(); $o->forceFill(['user_id' => $u->id, 'seller_id' => 1, 'code' => Order::generateUniqueCode(), 'delivery_status' => 'pending', 'payment_status' => 'unpaid', 'payment_type' => 'cash_on_delivery', 'shipping_address' => json_encode(['name' => 'T', 'phone' => '01700000000', 'address' => 'x']), 'grand_total' => 0, 'date' => time(), 'order_from' => 'app'])->save();
$d = new OrderDetail(); $d->order_id = $o->id; $d->seller_id = 1; $d->product_id = 5330; $d->variation = ''; $d->quantity = 6; $d->price = 6000; $d->selling_price = 1000; $d->tax = 0; $d->shipping_cost = 0; $d->buying_price = 0; $d->profit = 0; $d->delivery_status = 'pending'; $d->save();
$o->delivery_status = 'confirmed'; $o->save();
// a Size attribute and choice options so the product page shows size buttons
if (!DB::table('attributes')->where('id', 9001)->exists()) DB::table('attributes')->insert(['id' => 9001, 'name' => 'Size', 'created_at' => now(), 'updated_at' => now()]);
DB::table('products')->where('id', 5331)->update(['attributes' => '["9001"]', 'choice_options' => '[{"attribute_id":"9001","values":["S","M","L","XL"]}]']);
DB::table('products')->where('id', 5332)->update(['attributes' => '["9001"]', 'choice_options' => '[{"attribute_id":"9001","values":["40","41","42","43"]}]']);
return ['ok' => true];
