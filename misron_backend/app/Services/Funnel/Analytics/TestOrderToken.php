<?php

namespace App\Services\Funnel\Analytics;

/**
 * The token behind the editor's "Place a test order" link. An order placed
 * with a valid token is a real order (it reserves stock and shows in the Orders
 * list, marked as a test) but it is left out of the funnel analytics and is
 * never reported to Meta or Google.
 *
 * Signed with the application key; it names the funnel and an expiry time, so
 * it cannot be used for another funnel or after it runs out. Holding one only
 * lets someone mark their own order as a test, nothing more.
 */
class TestOrderToken
{
    public static function make(int $funnelId, ?int $hours = null): string
    {
        $expires = time() + 3600 * ($hours ?? (int) config('funnel.analytics.test_link_hours', 24));

        return $expires . '.' . self::sign($funnelId, $expires);
    }

    public static function valid(?string $token, int $funnelId): bool
    {
        if (!is_string($token) || !preg_match('/^(\d{10})\.([a-f0-9]{64})$/', $token, $m)) {
            return false;
        }
        if ((int) $m[1] < time()) {
            return false;
        }

        return hash_equals(self::sign($funnelId, (int) $m[1]), $m[2]);
    }

    /** Whether an order placed with this phone number and token is a test order. */
    public static function isTestOrder(?string $token, int $funnelId, string $phone): bool
    {
        return self::valid($token, $funnelId) || in_array($phone, config('funnel.analytics.test_phones', []), true);
    }

    private static function sign(int $funnelId, int $expires): string
    {
        return hash_hmac('sha256', 'funnel-test-order|' . $funnelId . '|' . $expires, (string) config('app.key'));
    }
}
