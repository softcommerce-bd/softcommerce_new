<?php

namespace App\Services\Chat;

use App\Models\ChatAccount;
use Illuminate\Support\Facades\Http;

/**
 * Fetches customer-sent media for the authorized attachment route. Provider URLs
 * come from webhook payloads, so they are treated as untrusted: https only, host
 * must belong to the provider's own CDN list, no redirects followed, size and
 * type capped. This is the SSRF boundary.
 */
class SafeFetch
{
    private const HOSTS = ['facebook.com', 'fbcdn.net', 'fbsbx.com', 'cdninstagram.com', 'instagram.com', 'whatsapp.net', 'whatsapp.com'];
    private const MIMES = '#^(image/(jpeg|png|gif|webp)|video/mp4|audio/(mpeg|ogg|mp4|aac|amr)|application/pdf)$#';
    private const MAX = 16 * 1024 * 1024;

    public static function hostAllowed(string $url): bool
    {
        $p = parse_url($url);
        if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['port']) && (int) $p['port'] !== 443) {
            return false;
        }
        $host = strtolower($p['host']);
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }
        foreach (self::HOSTS as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{body:string,mime:string}|null */
    public function attachment(?ChatAccount $account, array $a): ?array
    {
        $headers = [];
        $url = $a['url'] ?? null;

        if (!$url && !empty($a['media_id']) && $account && $account->platform === 'whatsapp') {
            $token = $account->secrets()['access_token'] ?? '';
            $meta = Http::withToken($token)->timeout(10)->get('https://graph.facebook.com/' . config('unified_chat.graph_version') . '/' . rawurlencode($a['media_id']));
            $url = $meta->json('url');
            $headers = ['Authorization' => 'Bearer ' . $token];
        }
        if (!$url || !self::hostAllowed($url)) {
            return null;
        }

        $r = Http::withHeaders($headers)->withOptions(['allow_redirects' => false, 'stream' => false])->timeout(15)->get($url);
        $mime = strtolower(trim(explode(';', (string) $r->header('Content-Type'))[0]));
        if (!$r->successful() || !preg_match(self::MIMES, $mime) || strlen($r->body()) > self::MAX) {
            return null;
        }

        return ['body' => $r->body(), 'mime' => $mime];
    }
}
