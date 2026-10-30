<?php

namespace App\Services\Funnel\Checkout;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * The customer's proof of an order, kept in their browser: an encrypted token
 * naming the order and the funnel it was placed through, valid for a limited time.
 * Holding it lets them see the order's summary and pay for it; without it the
 * order cannot be looked up.
 */
class Receipt
{
    public const LIFETIME_HOURS = 48;

    public static function make(int $combinedOrderId, int $funnelId): string
    {
        return Crypt::encryptString(json_encode([
            'c' => $combinedOrderId,
            'f' => $funnelId,
            'x' => time() + self::LIFETIME_HOURS * 3600,
        ]));
    }

    /** @return array{combined_order_id: int, funnel_id: int}|null null when forged, expired or for another funnel */
    public static function read($token, int $funnelId): ?array
    {
        if (!is_string($token) || $token === '' || strlen($token) > 1000) {
            return null;
        }

        try {
            $data = json_decode(Crypt::decryptString($token), true);
        } catch (DecryptException $e) {
            return null;
        }

        if (!is_array($data) || ($data['f'] ?? null) !== $funnelId || ($data['x'] ?? 0) < time()) {
            return null;
        }

        return ['combined_order_id' => (int) $data['c'], 'funnel_id' => (int) $data['f']];
    }
}
