<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BalanceTransfer extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
    ];

//     protected static function booted()
// {
//     static::created(function ($transfer) {
//         if ($transfer->status === 'completed') {
//             $transfer->fromAccount->decrement('current_balance', $transfer->amount);
//             $transfer->toAccount->increment('current_balance', $transfer->amount);
//         }
//     });
// }

    // Relationships
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

    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    // Auto-generate voucher number
    public static function generateVoucherNo($type)
    {
        $prefixes = [
            'withdraw' => 'WD',
            'deposit'  => 'DP',
            'transfer' => 'TR',
            'cashout'  => 'CO',
            'auto_deposit' => 'AD', 
        ];
        $prefix = $prefixes[$type] ?? 'TR';
        $date = now()->format('ym');
        $last = self::withTrashed()
                    ->where('voucher_no', 'like', $prefix . '-' . $date . '%')
                    ->orderBy('id', 'desc')
                    ->first();

        if ($last) {
            $lastNumber = (int) substr($last->voucher_no, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }
        return $prefix . '-' . $date . $newNumber;
    }

    // Scopes for filtering
    public function scopeFilter($query, $filters)
    {
        if (!empty($filters['from_account'])) {
            $query->whereHas('fromAccount', function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['from_account'] . '%');
            });
        }
        if (!empty($filters['to_account'])) {
            $query->whereHas('toAccount', function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['to_account'] . '%');
            });
        }
        if (!empty($filters['voucher_no'])) {
            $query->where('voucher_no', 'like', '%' . $filters['voucher_no'] . '%');
        }
        if (!empty($filters['transaction_type'])) {
            $query->where('transaction_type', $filters['transaction_type']);
        }
        if (!empty($filters['from_date'])) {
            $query->whereDate('date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->whereDate('date', '<=', $filters['to_date']);
        }
        return $query;
    }
}