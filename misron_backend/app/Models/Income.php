<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Income extends Model
{
    use SoftDeletes;

    protected $table = 'incomes';

    protected $fillable = [
        'voucher_no',
        'date',
        'received_account_id',
        'total_amount',
        'note',
        'cost_center_id',
        'customer_id',
        'created_by',
        'status'
    ];

    protected $casts = [
        'date' => 'date',
        'total_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    public static function getStatuses()
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    // app/Models/Income.php
public function updateAccountBalance()
{
    // Debit the received account (asset increases)
    ChartOfAccount::where('id', $this->received_account_id)
        ->increment('current_balance', $this->total_amount);

    // Credit each income account (income increases)
    foreach ($this->details as $detail) {
        ChartOfAccount::where('id', $detail->income_account_id)
            ->increment('current_balance', $detail->amount);
    }
}

public function reverseAccountBalance()
{
    // Reverse the debit on received account
    ChartOfAccount::where('id', $this->received_account_id)
        ->decrement('current_balance', $this->total_amount);

    // Reverse the credit on income accounts
    foreach ($this->details as $detail) {
        ChartOfAccount::where('id', $detail->income_account_id)
            ->decrement('current_balance', $detail->amount);
    }
}

    public function receivedAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'received_account_id');
    }

    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function details()
    {
        return $this->hasMany(IncomeDetail::class, 'income_id');
    }

    public static function generateVoucherNo()
    {
        $prefix = 'INC';
        $year = date('y');
        // first income of a year: there is no row to lock, only an empty index gap, and concurrent creators deadlock on it;
        // run the creators of this series one after the other until the transaction ends (no format change)
        \App\Support\NumberSerializer::hold('income:' . $prefix . '-' . $year);
        $lastVoucher = self::withTrashed()
            ->where('voucher_no', 'like', $prefix . '-' . $year . '%')
            ->orderBy('id', 'desc')
            ->lockForUpdate() // inside the creating transaction: a concurrent creation waits for this one and then reads the next free number
            ->first();
        
        if ($lastVoucher) {
            $lastNumber = intval(substr($lastVoucher->voucher_no, -6));
            $newNumber = str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '000001';
        }
        
        return $prefix . '-' . $year . $newNumber;
    }


}