<?php

namespace Gateway;

/** Same envelope as the installation side (App\Services\Chat\GatewaySignature): hmac_sha256(secret, "{id}.{ts}.{body}"). */
final class Signature
{
    public static function sign(string $secret, string $installationId, int $ts, string $body): string
    {
        return hash_hmac('sha256', $installationId . '.' . $ts . '.' . $body, $secret);
    }

    public static function verify(string $secret, string $installationId, int $ts, string $body, string $sig, int $tolerance = 300, ?int $now = null): bool
    {
        return $secret !== '' && $sig !== '' && abs(($now ?? time()) - $ts) <= $tolerance && hash_equals(self::sign($secret, $installationId, $ts, $body), $sig);
    }

    public static function meta(string $appSecret, string $rawBody, string $header): bool
    {
        return $appSecret !== '' && $header !== '' && hash_equals('sha256=' . hash_hmac('sha256', $rawBody, $appSecret), $header);
    }
}
