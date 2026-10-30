<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoucherDetail extends Model
{
    use HasFactory;
    
    protected $table = 'voucher_details';
    
    protected $fillable = [
        'voucher_id',
        'account_id',
        'debit',
        'credit',
        'narration',
        'supplier_id',
        'customer_id',
        'order_id'
    ];
    
    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];
    
    /**
     * Get the voucher
     */
    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }
    
    /**
     * Get the account
     */
    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
    
    /**
     * Get the supplier
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
    
    /**
     * Get the customer
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}