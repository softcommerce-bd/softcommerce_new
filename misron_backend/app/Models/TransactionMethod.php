<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionMethod extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'charge' => 'decimal:2',
    ];

    /**
     * Bank / Mobile bank account where payments are auto‑deposited.
     */
    public function bankAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'bank_account_id');
    }

    public function sourceAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'source_account_id');
    }




    // Scope for auto deposit methods
    public function scopeAutoDeposit($query)
    {
        return $query->where('deposit_type', 'auto')->where('status', 'active');
    }
}
