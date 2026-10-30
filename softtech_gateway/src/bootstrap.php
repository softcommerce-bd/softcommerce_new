<?php

require __DIR__ . '/Signature.php';
require __DIR__ . '/Store.php';
require __DIR__ . '/Router.php';
require __DIR__ . '/Deliverer.php';
require __DIR__ . '/App.php';

use Gateway\App;
use Gateway\Deliverer;
use Gateway\Store;

$env = fn (string $k, $d = null) => getenv($k) !== false ? getenv($k) : $d;
$key = base64_decode((string) $env('GATEWAY_KEY'), true) ?: '';
$dsn = $env('GATEWAY_DSN', 'sqlite:' . __DIR__ . '/../storage/gateway.sqlite');
if (str_starts_with($dsn, 'sqlite:') && !str_contains($dsn, ':memory:') && !is_dir(__DIR__ . '/../storage')) {
    mkdir(__DIR__ . '/../storage', 0700, true);
}
$store = new Store(new PDO($dsn, $env('GATEWAY_DB_USER'), $env('GATEWAY_DB_PASS')), $key);
$store->migrate();
$deliverer = new Deliverer($store);

$graph = 'https://graph.facebook.com/' . $env('META_GRAPH_VERSION', 'v25.0');
$get = function (string $url, array $query, ?string $bearer = null): array {
    $ch = curl_init($url . '?' . http_build_query($query));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => $bearer ? ['Authorization: Bearer ' . $bearer] : []]);
    $res = json_decode((string) curl_exec($ch), true) ?: [];
    curl_close($ch);

    return $res;
};
$cfg = [
    'meta_app_id' => $env('META_APP_ID'), 'meta_app_secret' => $env('META_APP_SECRET'), 'verify_token' => $env('META_WEBHOOK_VERIFY_TOKEN', ''),
    'graph_version' => $env('META_GRAPH_VERSION', 'v25.0'), 'public_url' => rtrim((string) $env('GATEWAY_PUBLIC_URL'), '/'),
    // Code → long-lived token → Pages (+ linked Instagram) with Page tokens; subscribes each Page to this app's webhook.
    'meta_client' => function (string $code) use ($get, $env, $graph): array {
        $short = $get("$graph/oauth/access_token", ['client_id' => $env('META_APP_ID'), 'client_secret' => $env('META_APP_SECRET'), 'redirect_uri' => rtrim((string) $env('GATEWAY_PUBLIC_URL'), '/') . '/oauth/callback', 'code' => $code])['access_token'] ?? null;
        $long = $short ? ($get("$graph/oauth/access_token", ['grant_type' => 'fb_exchange_token', 'client_id' => $env('META_APP_ID'), 'client_secret' => $env('META_APP_SECRET'), 'fb_exchange_token' => $short])['access_token'] ?? null) : null;
        $out = [];
        foreach (($long ? ($get("$graph/me/accounts", ['fields' => 'id,name,access_token,instagram_business_account{id,username,name}', 'limit' => 100], $long)['data'] ?? []) : []) as $p) {
            $out[] = ['platform' => 'facebook', 'external_id' => $p['id'], 'name' => $p['name'], 'access_token' => $p['access_token'], 'flow' => 'facebook_login'];
            if (!empty($p['instagram_business_account'])) {
                $ig = $p['instagram_business_account'];
                $out[] = ['platform' => 'instagram', 'external_id' => $ig['id'], 'name' => $ig['name'] ?? $ig['username'] ?? $ig['id'], 'handle' => $ig['username'] ?? null, 'access_token' => $p['access_token'], 'flow' => 'facebook_login'];
            }
            $ch = curl_init("$graph/{$p['id']}/subscribed_apps");
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['subscribed_fields' => 'messages,message_echoes,message_deliveries,message_reads']), CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $p['access_token']], CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
            curl_exec($ch);
            curl_close($ch);
        }

        return $out;
    },
    // Proof the installation really controls the account: the provider must accept its token for that id.
    'verify_account' => fn (string $platform, string $id, string $token): bool => (($get("$graph/" . rawurlencode($id), ['fields' => 'id'], $token))['id'] ?? null) === $id,
];
// WhatsApp Embedded Signup calls (Cloud API). Exchange / subscribe / register follow Meta's Cloud API reference;
// they have NOT been exercised against Meta from this codebase (no credentials) — see the readiness report.
$cfg['wa_config_id'] = $env('WA_ES_CONFIG_ID');
$cfg['wa_client'] = new class($graph, $env('META_APP_ID'), $env('META_APP_SECRET')) {
    public function __construct(private string $graph, private ?string $id, private ?string $secret) {}
    private function call(string $method, string $url, array $params = [], ?string $token = null): array
    {
        $ch = curl_init($method === 'GET' ? $url . '?' . http_build_query($params) : $url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => $token ? ['Authorization: Bearer ' . $token] : []]);
        if ($method === 'POST') {
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($params)]);
        }
        $body = json_decode((string) curl_exec($ch), true) ?: [];
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code < 200 || $code >= 300 || isset($body['error'])) {
            throw new \RuntimeException('provider HTTP ' . $code);
        }

        return $body;
    }
    public function exchange(string $code): string { return $this->call('GET', "{$this->graph}/oauth/access_token", ['client_id' => $this->id, 'client_secret' => $this->secret, 'code' => $code])['access_token'] ?? throw new \RuntimeException('no token'); }
    public function phone(string $phoneId, string $token): array { return $this->call('GET', "{$this->graph}/$phoneId", ['fields' => 'id,display_phone_number,verified_name'], $token); }
    public function subscribe(string $waba, string $token): void { $this->call('POST', "{$this->graph}/$waba/subscribed_apps", [], $token); }
    public function register(string $phoneId, string $token, string $pin): void { $this->call('POST', "{$this->graph}/$phoneId/register", ['messaging_product' => 'whatsapp', 'pin' => $pin], $token); }
};
$app = new App($store, $deliverer, $cfg);
