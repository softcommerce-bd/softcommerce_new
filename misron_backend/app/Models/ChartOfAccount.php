<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use SoftDeletes;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'code',
        'name',
        'type',
        'sub_type',
        'parent_id',
        'opening_balance',
        'current_balance',
        'bank_name',
        'account_number',
        'mobile_number',
        'status',
        'description',
        'cashout_charge',
        'is_deleted'
    ];

    // Legacy code changes balances with ->save() / ->increment(); both reach ChartOfAccountBuilder, where the legacy-ledger bridge records the change.
    public function newEloquentBuilder($query)
    {
        return new \App\Models\Builders\ChartOfAccountBuilder($query);
    }

    protected $casts = [
        'cashout_charge' => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
    ];

    //     public static function getAccountsReceivable()
    // {
    //     return static::where('is_deleted', '0')
    //         ->where('type', 'asset')
    //         ->where('sub_type', 'receivable')
    //         ->where('name', 'Accounts Receivable')
    //         ->first();
    // }

    public static function getAccountsPayable()
    {
        return static::where('type', 'liability')
            ->where('sub_type', 'payable')
            ->where('name', 'Accounts Payable')
            ->first();
    }
    public static function getPettyCashAccount()
    {
        return static::where('type', 'asset')
            ->where('sub_type', 'cash')
            ->where('name', 'Petty cash')
            ->first();
    }

    public static function getOpeningSupplierBalanceAccount()
    {
        return static::where('type', 'liability')
        ->where('sub_type', 'equity')
        ->where('name', 'Opening Supplier Balance')
        ->first();
    }

    public static function getAccountsReceivable()
    {
        return static::where('is_deleted', '0')
            ->where('type', 'asset')
            ->where('sub_type', 'receivable')
            ->where('name', 'Accounts Receivable')
            ->first();
    }

    public static function getSalesAccount()
    {
        return static::where('is_deleted', '0')
            ->where('type', 'income')
            ->where('sub_type', 'sales')
            ->where('name', 'Sales')
            ->first();
    }
    public static function getInventoryAccount()
    {
        return static::where('is_deleted', '0')
            ->where('type', 'asset')
            ->where('sub_type', 'inventory')
            ->where('name', 'Inventory')
            ->first();
    }

    public static function getCourierIncomeAccount()
    {
        return static::where('is_deleted', '0')
            ->where('type', 'income')
            ->where('sub_type', 'income')
            ->where('name', 'Courier Income')
            ->first();
    }

    public static function getBadDebtExpenseAccount()
    {
        return static::where('is_deleted', '0')
            ->where('type', 'expense')
            ->where('sub_type', 'expense')
            ->where('name', 'Bad Debt Expense')
            ->first();
    }

    public static function getCourierFeeExpenseAccount()
    {
        return static::where('is_deleted', '0')
            ->where('type', 'expense')
            ->where('sub_type', 'expense')
            ->where('name', 'Courier Fee Expense')
            ->first();
    }

    public static function getCourierSettlementExpenseAccount()
    {
        return static::where('is_deleted', '0')
            ->where('type', 'expense')
            ->where('sub_type', 'expense')
            ->where('name', 'Courier Settlement Expense')
            ->first();
    }
    public static function getBadDebitExpense()
    {
        return static::where('is_deleted', '0')
            ->where('type', 'expense')
            ->where('sub_type', 'expense')
            ->where('name', 'Bad Debt Expense')
            ->first();
    }

    // Relationships
    public function parent()
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id');
    }

    public function voucherDetails()
    {
        return $this->hasMany(VoucherDetail::class, 'account_id');
    }

    public function statements()
    {
        return $this->hasMany(AccountStatementLog::class, 'account_id');
    }

    public function supplierPayments()
    {
        return $this->hasMany(SupplierPayment::class, 'payment_account_id');
    }

    public function customerPayments()
    {
        return $this->hasMany(CustomerPayment::class, 'payment_account_id');
    }

    public function bankTransactionsFrom()
    {
        return $this->hasMany(BankTransaction::class, 'from_account_id');
    }

    public function bankTransactionsTo()
    {
        return $this->hasMany(BankTransaction::class, 'to_account_id');
    }

    public function expensePayments()
    {
        return $this->hasMany(ExpensePayment::class, 'payment_account_id');
    }

    public function incomes()
    {
        return $this->hasMany(Income::class, 'received_account_id');
    }

    // Helper Methods
    public function updateBalance($amount, $dc)
    {
        if ($dc === 'debit') {
            $this->current_balance += $amount;
        } else {
            $this->current_balance -= $amount;
        }
        $this->save();
    }

    // public function addStatement($data)
    // {
    //     return $this->statements()->create($data);
    // }

    public function getFormattedCashoutChargeAttribute()
    {
        return $this->cashout_charge . '%';
    }

    /**
     * Get balance transfers as from account
     */
    public function fromBalanceTransfers()
    {
        return $this->hasMany(BalanceTransfer::class, 'from_account_id');
    }

    /**
     * Get balance transfers as to account
     */
    public function toBalanceTransfers()
    {
        return $this->hasMany(BalanceTransfer::class, 'to_account_id');
    }

    /**
     * Get formatted cashout charge
     */

    /**
     * Check if account supports cashout
     */
    public function getCanCashoutAttribute()
    {
        return $this->sub_type === 'bank' || $this->sub_type === 'mobile_bank';
    }

    /**
     * Calculate cashout charge for amount
     */
    public function calculateCashoutCharge($amount)
    {
        if ($this->cashout_charge > 0 && $this->can_cashout) {
            return ($amount * $this->cashout_charge) / 100;
        }
        return 0;
    }

    /**
     * Get account type label
     */
    public function getTypeLabelAttribute()
    {
        $types = [
            'asset' => 'Asset',
            'liability' => 'Liability',
            'equity' => 'Equity',
            'income' => 'Income',
            'expense' => 'Expense',
        ];
        return $types[$this->type] ?? ucfirst($this->type);
    }

    /**
     * Get sub type label
     */
    public function getSubTypeLabelAttribute()
    {
        $subTypes = [
            'cash' => 'Cash',
            'bank' => 'Bank',
            'mobile_bank' => 'Mobile Banking',
            'receivable' => 'Accounts Receivable',
            'inventory' => 'Inventory',
            'fixed_asset' => 'Fixed Asset',
            'payable' => 'Accounts Payable',
            'tax' => 'Tax',
            'salary' => 'Salary',
        ];
        return $subTypes[$this->sub_type] ?? ucfirst(str_replace('_', ' ', $this->sub_type));
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute()
    {
        return $this->status == 'active' ? 'Active' : 'Inactive';
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute()
    {
        return $this->status == 'active' ? 'badge-success' : 'badge-danger';
    }

    /**
     * Check if account is cash/bank type
     */
    public function getIsCashBankAttribute()
    {
        return in_array($this->sub_type, ['cash', 'bank', 'mobile_bank']);
    }

    /**
     * Add statement log entry
     */
    public function addStatement($data)
    {
        return $this->statements()->create(array_merge($data, [
            'account_id' => $this->id,
        ]));
    }
}
