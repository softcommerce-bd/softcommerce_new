<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One hosted payment session opened with a gateway for an order. Kept so the
 * store can ask the gateway later whether that payment went through.
 */
class PaymentGatewayAttempt extends Model
{
    public const STARTED = 'started';
    public const PAID = 'paid';
    public const FAILED = 'failed';
    public const UNKNOWN = 'unknown';

    protected $guarded = ['id'];

    protected $casts = [
        'checked_at' => 'datetime',
    ];

    private static ?bool $ready = null;

    /** The table is optional (sales_funnel_v5); payments work without it. */
    public static function ready(): bool
    {
        return self::$ready ??= \Illuminate\Support\Facades\Schema::hasTable('payment_gateway_attempts');
    }

    public static function forgetReadiness(): void
    {
        self::$ready = null;
    }
}
