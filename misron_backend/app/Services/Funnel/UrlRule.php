<?php

namespace App\Services\Funnel;

/**
 * Link targets a funnel page may contain. An allowlist, so javascript:, data:,
 * vbscript: and protocol-relative URLs are rejected without being listed.
 */
class UrlRule
{
    public const MAX_LENGTH = 2000;

    public static function isSafe(string $url): bool
    {
        if ($url === '' || mb_strlen($url) > self::MAX_LENGTH) {
            return false;
        }

        // Whitespace and control characters are how scheme filters get
        // bypassed ("java\tscript:"), so none are accepted anywhere.
        if (preg_match('/[\x00-\x20\x7F]/', $url)) {
            return false;
        }

        if (preg_match('#^https?://#i', $url)) {
            return filter_var($url, FILTER_VALIDATE_URL) !== false
                && parse_url($url, PHP_URL_HOST) !== null
                && parse_url($url, PHP_URL_USER) === null;
        }

        if (preg_match('#^/(?![/\\\\])#', $url)) {
            return strpos($url, '\\') === false;
        }

        if (preg_match('/^#[A-Za-z][A-Za-z0-9_-]{0,63}$/', $url)) {
            return true;
        }

        if (preg_match('/^tel:\+?[0-9()-]{3,20}$/', $url)) {
            return true;
        }

        return (bool) preg_match('/^mailto:[^@\s]+@[^@\s]+\.[^@\s]+$/', $url);
    }

    /** Stricter variant for image sources: absolute http(s) or site-relative. */
    public static function isSafeImage(string $url): bool
    {
        return self::isSafe($url) && (bool) preg_match('#^(https?://|/(?![/\\\\]))#i', $url);
    }
}
