<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

     function supplier(){
        return $this->belongsTo(Supplier::class,'supplier_id','id');
    }
    function products(){
        return $this->hasMany(PurchaseProduct::class,'purchase_id','id');
    }
    function details(){
        return $this->hasMany(PurchaseOrderDetail::class,'purchase_order_id','id');
    }
}
