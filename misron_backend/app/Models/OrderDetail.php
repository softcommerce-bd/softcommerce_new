<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    protected static function booted()
    {
        // New lines follow the confirmed-order model while it is switched on; everything else stays 'legacy'.
        static::creating(function (OrderDetail $line) {
            if (!array_key_exists('stock_mode', $line->getAttributes()) && \App\Services\Inventory\InventoryMode::confirmedOnly()) {
                $line->stock_mode = 'v2';
            }
        });
    }


    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function pickup_point()
    {
        return $this->belongsTo(PickupPoint::class);
    }

    public function refund_request()
    {
        return $this->hasOne(RefundRequest::class);
    }

    public function affiliate_log()
    {
        return $this->hasMany(AffiliateLog::class);
    }
}
