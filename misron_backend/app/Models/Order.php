<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Order extends Model
{
    protected $guarded = ['id'];

    // protected static function boot()
    // {
    //     parent::boot();
        
    //     static::saved(function ($order) {
    //         // Check if courier_status changed to 'delivered'
    //         if ($order->wasChanged('courier_status') && 
    //             $order->courier_status === 'delivered' && 
    //             $order->payment_status !== 'paid') {
                
    //             // Update payment status directly (bypass events to prevent loop)
    //             \App\Models\Order::where('id', $order->id)
    //                 ->where('payment_status', '!=', 'paid')
    //                 ->update(['payment_status' => 'paid']);
                
    //             // Refresh the model
    //             $order->refresh();
                
    //             // Optional: Log the change
    //             Log::info('Payment status auto-updated', [
    //                 'order_id' => $order->id,
    //                 'trigger' => 'courier_delivered'
    //             ]);
    //         }
    //     });
    // }

    // protected static function booted()
    // {
    //     static::updating(function ($order) {
    //         dd('hello');

    //         // Check if courier_status changed to delivered
    //         if (
    //             $order->isDirty('courier_status') &&
    //             $order->courier_status === 'delivered'
    //         ) {
    //             $order->payment_status = 'paid';
    //         }
    //     });
    // }
    public function settlement()
    {
        return $this->hasOne(CourierSettlement::class);
    }

    public function settlementAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'settlement_account_id');
    }
    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class)->with('product');
    }

    public function refund_requests()
    {
        return $this->hasMany(RefundRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** The sales funnel page the order was placed through (orders.funnel_id), if any. */
    public function funnel()
    {
        return $this->belongsTo(Funnel::class)->withTrashed();
    }

    /**
     * Whether orders carry the funnel columns (sales_funnel_v2_orders.sql). The order
     * screens use it so they keep working on a database that does not have them yet.
     */
    public static function hasFunnelColumns(): bool
    {
        static $known = null;
        if ($known === null) {
            try {
                $known = \Illuminate\Support\Facades\Schema::hasColumn('orders', 'funnel_id');
            } catch (\Throwable $e) {
                $known = false;
            }
        }

        return $known;
    }

    public function shop()
    {
        return $this->hasOne(Shop::class, 'user_id', 'seller_id');
    }

    public function pickup_point()
    {
        return $this->belongsTo(PickupPoint::class);
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }

    public function affiliate_log()
    {
        return $this->hasMany(AffiliateLog::class);
    }

    public function club_point()
    {
        return $this->hasMany(ClubPoint::class);
    }

    public function delivery_boy()
    {
        return $this->belongsTo(User::class, 'assign_delivery_boy', 'id');
    }

    public function proxy_cart_reference_id()
    {
        return $this->hasMany(ProxyPayment::class)->select('reference_id');
    }

    public function commissionHistory()
    {
        return $this->hasOne(CommissionHistory::class);
    }
    public static function generateUniqueCode()
    {
        do {
            $code = mt_rand(100000, 999999);
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function getCourierStatusLabelAttribute()
    {
        if (!$this->courier_name) {
            return "unassigned";
        }

        return ucfirst($this->courier_name) . ' - ' . ucfirst($this->courier_status);
    }
    // Add this boot method


    // newly added for accounts part 
    public function customerPayments()
    {
        return $this->belongsToMany(CustomerPayment::class, 'order_payment_mappings', 'order_id', 'customer_payment_id')
            ->withPivot('amount_applied');
    }

    public function getTotalPaidAttribute()
    {
        return $this->customerPayments()->sum('order_payment_mappings.amount_applied');
    }

    public function getRemainingAmountAttribute()
    {
        return $this->grand_total - $this->total_paid;
    }

    public function isFullyPaid()
    {
        return $this->remaining_amount <= 0;
    }
}
