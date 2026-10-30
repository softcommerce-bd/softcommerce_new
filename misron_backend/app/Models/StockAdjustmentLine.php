<?php
// app/Models/StockAdjustmentLine.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAdjustmentLine extends Model
{
    protected $fillable = [
        'stock_adjustment_id',
        'product_id',
        'product_stock_id',
        'quantity',
        'unit_price',
        'subtotal'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productStock()
    {
        return $this->belongsTo(ProductStock::class, 'product_stock_id');
    }

    public function stockAdjustment()
    {
        return $this->belongsTo(StockAdjustment::class);
    }
}