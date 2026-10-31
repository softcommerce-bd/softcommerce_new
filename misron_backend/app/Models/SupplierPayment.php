<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierPayment extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function purchases()
    {
        return $this->belongsToMany(Purchase::class, 'supplier_payment_purchases')
            ->withPivot('paid_amount')
            ->withTimestamps();
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

      public function paymentAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'payment_account_id', 'id');
    }
 

    /**
     * Creates the payment with the next PAY- number. Two payments to different suppliers at the same moment can read the same last number;
     * the second waits for the first to commit, fails on the unique index and (in its own savepoint) tries again with a locking read of the latest row.
     */
    public static function createNumbered(array $attributes): self
    {
        for ($try = 1; ; $try++) {
            try {
                return \Illuminate\Support\Facades\DB::transaction(function () use ($attributes) {
                    $last = self::orderBy('id', 'desc')->lockForUpdate()->first();
                    $next = $last ? intval(substr($last->payment_no, 4)) + 1 : 1;

                    return self::create($attributes + ['payment_no' => 'PAY-' . str_pad($next, 6, '0', STR_PAD_LEFT)]);
                });
            } catch (\Illuminate\Database\QueryException $e) {
                if ($try >= 5 || ($e->errorInfo[1] ?? null) != 1062 || !str_contains($e->getMessage(), 'payment_no')) {
                    throw $e;
                }
            }
        }
    }
}
