<?php
// app/Models/CourierSettlementDetail.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierSettlementDetail extends Model
{
    protected $table = 'courier_settlement_details';
    
    protected $fillable = [
        'courier_settlement_id',
        'order_detail_id',
        'product_id',
        'product_name',
        'quantity',
        'price',
        'selling_price',
        'buying_price',
        'profit',
        'cost_price',
        'tax',
        'shipping_cost',
        'payment_status',
        'delivery_status',

    ];
    
    public function courierSettlement()
    {
        return $this->belongsTo(CourierSettlement::class);
    }
    
    public function orderDetail()
    {
        return $this->belongsTo(OrderDetail::class);
    }
    
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
        public function order()
    {
        return $this->belongsTo(Order::class);
    }

}