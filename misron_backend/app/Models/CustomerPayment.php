<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerPayment extends Model
{
    use SoftDeletes;
    
    protected $table = 'customer_payments';
    
    protected $fillable = [
        'customer_id', 'payment_no', 'amount', 'received_amount', 'due_amount',
        'payment_account_id', 'transaction_method_id', 'payment_type',
        'payment_date', 'notes', 'reference_no', 'voucher_id', 'created_by', 'status'
    ];
    
    protected $casts = [
        'amount' => 'decimal:2',
        'received_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
        'payment_date' => 'date',
    ];
    
    // Customer relationship (User)
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
    
    public function paymentAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'payment_account_id');
    }
    
    public function transactionMethod()
    {
        return $this->belongsTo(TransactionMethod::class);
    }
    
    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }
    
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    public function orderMappings()
    {
        return $this->hasMany(OrderPaymentMapping::class);
    }
    
    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_payment_mappings', 'customer_payment_id', 'order_id')
                    ->withPivot('amount_applied');
    }
    
    public static function generatePaymentNo()
    {
        $lastPayment = self::orderBy('id', 'desc')->first();
        if ($lastPayment) {
            $lastNumber = intval(substr($lastPayment->payment_no, 3));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        return 'CUS' . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }
}