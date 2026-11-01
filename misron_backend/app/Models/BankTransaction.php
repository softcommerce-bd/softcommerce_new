<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * HISTORICAL RECORD ONLY (owner decision B-1.1: the bank-screen actions are retired; balance transfers are the supported workflow).
 * Rows that already exist stay readable (list, detail, reports). The model refuses to create, change or delete a row, so no code path -
 * hidden route, tinker, a future controller - can post a bank transaction or alter/remove its history through it. Mass assignment is off.
 */
class BankTransaction extends Model
{
    use HasFactory;

    protected $guarded = ['*'];

    protected $casts = ['transaction_date' => 'date', 'cheque_date' => 'date', 'clearing_date' => 'date', 'amount' => 'decimal:2'];

    protected static function booted(): void
    {
        $refuse = function () {
            throw new \LogicException('Bank transactions are retired and read-only (decision B-1.1). Use balance transfers.');
        };
        static::creating($refuse);
        static::updating($refuse);
        static::deleting($refuse);
        static::saving($refuse);
    }

    public function fromAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'from_account_id');
    }

    public function toAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'to_account_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }
}
