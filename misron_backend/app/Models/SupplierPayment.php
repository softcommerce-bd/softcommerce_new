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
 
}
