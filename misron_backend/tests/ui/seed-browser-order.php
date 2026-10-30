<?php
// Test-only: a confirmed order for the backorder product (creates purchase-requirement demand).
use App\Models\{Order, OrderDetail, User};
$user = User::where('user_type', 'customer')->first() ?? tap(new User, fn ($u) => $u->forceFill(['name' => 'E2E Cust', 'email' => 'cust' . uniqid() . '@e2e.test', 'password' => bcrypt('x'), 'user_type' => 'customer', 'banned' => 0])->save());
$order = new Order();
$order->forceFill(['user_id' => $user->id, 'seller_id' => 1, 'code' => Order::generateUniqueCode(), 'delivery_status' => 'pending', 'payment_status' => 'unpaid', 'payment_type' => 'cash_on_delivery', 'shipping_address' => json_encode(['name' => 'T', 'phone' => '01700000000', 'address' => 'x']), 'grand_total' => 0, 'date' => time(), 'order_from' => 'app'])->save();
$d = new OrderDetail(); $d->order_id = $order->id; $d->seller_id = 1; $d->product_id = 5330; $d->variation = ''; $d->quantity = 6; $d->price = 6000; $d->selling_price = 1000; $d->tax = 0; $d->shipping_cost = 0; $d->buying_price = 0; $d->profit = 0; $d->delivery_status = 'pending'; $d->save();
$order->delivery_status = 'confirmed'; $order->save();
echo $order->code;
