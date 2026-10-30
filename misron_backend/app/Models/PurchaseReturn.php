<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturn extends Model
{
    use HasFactory;
    protected $guarded = [];

    function supplier(){
        return $this->belongsTo(Supplier::class,'supplier_id','id');
    }
    public function productReturn()
    {
        return $this->hasMany(purchaseReturnProduct::class, 'purchase_return_id', 'id');
    }
}

