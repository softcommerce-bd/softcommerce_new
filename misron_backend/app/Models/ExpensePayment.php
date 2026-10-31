<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpensePayment extends Model
{
    use SoftDeletes;

    protected $table = 'expense_payments';

    protected $fillable = [
        'voucher_no',
        'date',
        'payment_account_id',
        'total_amount',
        'note',
        'cost_center_id',
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

    // Constants for status
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get all statuses
     */
    public static function getStatuses()
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /**
     * Update ChartOfAccount balances after creating/updating an expense.
     * Deducts from payment account (credit) and adds to each expense account (debit).
     */
    public function updateAccountBalance()
    {
        // Deduct from payment account (cash/bank)
        ChartOfAccount::where('id', $this->payment_account_id)
            ->decrement('current_balance', $this->total_amount);

        // Add to expense accounts
        foreach ($this->details as $detail) {
            ChartOfAccount::where('id', $detail->expense_account_id)
                ->increment('current_balance', $detail->amount);
        }
    }

    /**
     * Reverse ChartOfAccount balances (for edit/delete).
     * Adds back to payment account and subtracts from expense accounts.
     */
    public function reverseAccountBalance()
    {
        // Add back to payment account
        ChartOfAccount::where('id', $this->payment_account_id)
            ->increment('current_balance', $this->total_amount);

        // Subtract from expense accounts
        foreach ($this->details as $detail) {
            ChartOfAccount::where('id', $detail->expense_account_id)
                ->decrement('current_balance', $detail->amount);
        }
    }

    /**
     * Get the payment account (ChartOfAccount)
     */
    public function paymentAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'payment_account_id');
    }

    /**
     * Get the cost center
     */
    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    /**
     * Get the user who created the expense payment
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the expense payment details
     */
    public function details()
    {
        return $this->hasMany(ExpensePaymentDetail::class, 'expense_payment_id');
    }

    /**
     * Scope for approved transactions
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for pending transactions
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for date range
     */
    public function scopeDateBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Get formatted total amount
     */
    public function getFormattedTotalAmountAttribute()
    {
        return number_format($this->total_amount, 2) . ' ৳';
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute()
    {
        $statuses = self::getStatuses();
        return $statuses[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute()
    {
        switch ($this->status) {
            case self::STATUS_APPROVED:
                return 'badge-success';
            case self::STATUS_PENDING:
                return 'badge-warning';
            case self::STATUS_REJECTED:
            case self::STATUS_CANCELLED:
                return 'badge-danger';
            default:
                return 'badge-secondary';
        }
    }

    /**
     * Generate voucher number
     */
    public static function generateVoucherNo()
    {
        $prefix = 'EXP';
        $year = date('y');
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
