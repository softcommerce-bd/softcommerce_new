<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomeDetail extends Model
{
    protected $table = 'income_details';

    protected $fillable = [
        'income_id',
        'income_account_id',
        'amount',
        'note'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function income()
    {
        return $this->belongsTo(Income::class);
    }

    public function incomeAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'income_account_id');
    }
}