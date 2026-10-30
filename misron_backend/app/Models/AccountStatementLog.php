<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountStatementLog extends Model
{
    use HasFactory;
    
    protected $guarded = [];

    protected $casts = [
        'transaction_date' => 'date',
    ];

    /**
     * Relationship with ChartOfAccount
     */
    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    /**
     * Polymorphic relationship to get the source model
     */
    public function source()
    {
        return $this->morphTo('source');
    }

    /**
     * Relationship with Voucher
     */
    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    /**
     * Relationship with Order
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}