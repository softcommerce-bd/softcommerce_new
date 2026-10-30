<?php

namespace App\Services\Funnel\Analytics;

/**
 * Classifies where a visit came from and on what kind of device, from facts the
 * browser sends anyway (campaign parameters, the referring site's host name, the
 * user agent). Only the class is stored, never the user agent or a full URL.
 */
class TrafficSource
{
    /** Sources the dashboard groups by. Anything else is "other" or "referral". */
    private const KNOWN = [
        'facebook' => ['facebook', 'fb', 'ig', 'instagram', 'meta', 'messenger', 'fb.me', 'm.me'],
        'google' => ['google', 'googleads', 'adwords', 'youtube'],
        'tiktok' => ['tiktok'],
        'whatsapp' => ['whatsapp', 'wa'],
        'email' => ['email', 'newsletter', 'mail'],
        'sms' => ['sms'],
    ];

    private const HOSTS = [
        'facebook' => ['facebook.com', 'fb.com', 'instagram.com', 'messenger.com', 'fb.me', 'm.me'],
        'google' => ['google.', 'youtube.com', 'googleadservices.com', 'doubleclick.net'],
        'tiktok' => ['tiktok.com'],
        'whatsapp' => ['whatsapp.com', 'wa.me'],
    ];

    public static function device(?string $userAgent): string
    {
        $ua = strtolower((string) $userAgent);
        if ($ua === '') {
            return 'unknown';
        }
        if (preg_match('/ipad|tablet|kindle|silk|playbook|(android(?!.*mobile))/', $ua)) {
            return 'tablet';
        }
        if (preg_match('/mobi|iphone|ipod|android|blackberry|opera mini|iemobile|windows phone/', $ua)) {
            return 'mobile';
        }
        if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview/', $ua)) {
            return 'bot';
        }

        return 'desktop';
    }

    /**
     * @param  string|null $utmSource   utm_source as sent
     * @param  string|null $referrerHost host name of document.referrer, if any
     * @param  string|null $clickKind   'fbclid' or 'gclid' when the visit carried an ad click id
     */
    public static function source(?string $utmSource, ?string $referrerHost, ?string $clickKind = null): string
    {
        $utm = strtolower(trim((string) $utmSource));
        if ($utm !== '') {
            foreach (self::KNOWN as $name => $aliases) {
                foreach ($aliases as $alias) {
                    if ($utm === $alias || strpos($utm, $alias) === 0) {
                        return $name;
                    }
                }
            }

            return 'other';
        }

        if ($clickKind === 'fbclid') {
            return 'facebook';
        }
        if ($clickKind === 'gclid') {
            return 'google';
        }

        $host = strtolower(trim((string) $referrerHost));
        if ($host === '') {
            return 'direct';
        }
        foreach (self::HOSTS as $name => $needles) {
            foreach ($needles as $needle) {
                if (strpos($host, $needle) !== false) {
                    return $name;
                }
            }
        }

        return 'referral';
    }

    /** A host name as the browser reported it, or null when it is not one. */
    public static function host($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = strtolower(trim($value));
        if ($value === '' || strlen($value) > 150 || !preg_match('/^[a-z0-9.-]+(:[0-9]{1,5})?$/', $value)) {
            return null;
        }

        return $value;
    }

    /** A short label (campaign name, medium): printable, trimmed, cut to length. */
    public static function label($value, int $max): ?string
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $value = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $value) ?? '');
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    /** An opaque id the browser made (visitor, session, event): letters, digits, dashes. */
    public static function id($value, int $max = 64): ?string
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9_\-.]{8,' . $max . '}$/', $value) ? $value : null;
    }
}
