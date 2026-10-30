<?php
// app/Models/CourierSettlement.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierSettlement extends Model
{
    protected $table = 'courier_settlements';
    
    protected $fillable = [
        'settlement_no',
        'order_id',
        'user_id',
        'courier_name',
        'total_amount',
        'courier_fee',
        'given_customer',
        'settlement_fee',
        'settlement_charge_percentage',
        'net_payable',
        'account_id',
        'account_name',
        'status',
        'settlement_date',
        'partial_payment_choice',
        'notes'
    ];
    
    protected $casts = [
        'settlement_date' => 'datetime',
    ];
    
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function settlementDetails()
    {
        return $this->hasMany(CourierSettlementDetail::class);
    }
    
    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
    
    // Generate unique settlement number
    public static function generateSettlementNo()
    {
        $prefix = 'STL-' . date('Ymd');
        $lastSettlement = self::where('settlement_no', 'LIKE', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();
            
        if (!$lastSettlement) {
            return $prefix . '-0001';
        }
        
        $lastNumber = intval(substr($lastSettlement->settlement_no, -4));
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        
        return $prefix . '-' . $newNumber;
    }
}