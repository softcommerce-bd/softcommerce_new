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
    
    /**
     * Creates the settlement with the next free number. The number is read, not reserved, so two settlements at the same moment can pick the same
     * one; the second then waits for the first to commit, fails on the unique index and (inside its own savepoint) tries again with a fresh read.
     */
    public static function createNumbered(array $attributes): self
    {
        for ($try = 1; ; $try++) {
            try {
                return \Illuminate\Support\Facades\DB::transaction(fn () => self::create($attributes + ['settlement_no' => self::generateSettlementNo()]));
            } catch (\Illuminate\Database\QueryException $e) {
                if ($try >= 5 || ($e->errorInfo[1] ?? null) != 1062 || !str_contains($e->getMessage(), 'settlement_no')) {
                    throw $e;
                }
            }
        }
    }

    // Generate unique settlement number
    public static function generateSettlementNo()
    {
        $prefix = 'STL-' . date('Ymd');
        $lastSettlement = self::where('settlement_no', 'LIKE', $prefix . '%')
            ->orderBy('id', 'desc')
            ->lockForUpdate() // a locking read sees the latest committed row, which a retry after a number clash needs (a plain read keeps the old snapshot)
            ->first();
            
        if (!$lastSettlement) {
            return $prefix . '-0001';
        }
        
        $lastNumber = intval(substr($lastSettlement->settlement_no, -4));
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        
        return $prefix . '-' . $newNumber;
    }
}