<?php

namespace App\Services\Chat;

/**
 * HMAC envelope shared by the gateway and every installation:
 *   signature = hex(hmac_sha256(secret, "{installation_id}.{timestamp}.{body}"))
 * The installation id is inside the signed text, so a signature made for
 * installation A can never validate on installation B even if secrets collided.
 * Timestamp tolerance bounds replay; the event id (unique key) closes it.
 */
class GatewaySignature
{
    public static function sign(string $secret, string $installationId, int $timestamp, string $body): string
    {
        return hash_hmac('sha256', $installationId . '.' . $timestamp . '.' . $body, $secret);
    }

    public static function verify(string $secret, string $installationId, int $timestamp, string $body, string $signature, int $tolerance = 300, ?int $now = null): bool
    {
        if ($secret === '' || $signature === '' || abs(($now ?? time()) - $timestamp) > $tolerance) {
            return false;
        }

        return hash_equals(self::sign($secret, $installationId, $timestamp, $body), $signature);
    }
}
