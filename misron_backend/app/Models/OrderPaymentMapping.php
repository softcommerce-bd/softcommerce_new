<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderPaymentMapping extends Model
{
    protected $table = 'order_payment_mappings';
    
    protected $fillable = [
        'order_id', 'customer_payment_id', 'amount_applied'
    ];
    
    protected $casts = [
        'amount_applied' => 'decimal:2',
    ];
    
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    
    public function customerPayment()
    {
        return $this->belongsTo(CustomerPayment::class);
    }
}