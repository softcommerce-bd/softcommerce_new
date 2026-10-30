<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderDetail extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    function supplier(){
        return $this->belongsTo(Supplier::class,'supplier_id','id');
    }    
    function product(){
        return $this->belongsTo(Product::class);
    }
     function purchaseOrder(){
        return $this->belongsTo(PurchaseOrder::class);
    }
}
