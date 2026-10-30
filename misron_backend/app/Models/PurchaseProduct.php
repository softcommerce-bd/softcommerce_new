<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseProduct extends Model
{
    use HasFactory;
    protected $guarded = [];

      function supplier(){
        return $this->belongsTo(Supplier::class,'supplier_id','id');
    }
    
    function product(){
        return $this->belongsTo(Product::class);
    }

       // Add this
    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchase_id', 'id');
    }

}
