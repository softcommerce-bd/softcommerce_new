<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpensePaymentDetail extends Model
{
    protected $table = 'expense_payment_details';

    protected $fillable = [
        'expense_payment_id',
        'expense_account_id',  // Changed from expense_category_id to expense_account_id
        'amount',
        'note'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the expense payment
     */
    public function expensePayment()
    {
        return $this->belongsTo(ExpensePayment::class, 'expense_payment_id');
    }

    /**
     * Get the expense account (from chart_of_accounts where type = 'expense')
     */
    public function expenseAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    /**
     * Get formatted amount
     */
    public function getFormattedAmountAttribute()
    {
        return number_format($this->amount, 2) . ' ৳';
    }
}