<?php

namespace Gateway;

/** HTTP surface. Framework-free: handle() takes a plain request array and returns [status, headers, body]. */
final class App
{
    public function __construct(private Store $store, private Deliverer $deliverer, private array $cfg)
    {
    }

    public function handle(string $method, string $path, array $query, array $headers, string $raw): array
    {
        $h = array_change_key_case($headers, CASE_LOWER);
        try {
            return match (true) {
                $method === 'GET' && preg_match('#^/webhook/(facebook|instagram|whatsapp)$#', $path) => $this->verify($query),
                $method === 'POST' && preg_match('#^/webhook/(facebook|instagram|whatsapp)$#', $path, $m) => $this->webhook($m[1], $h, $raw),
                $method === 'GET' && $path === '/oauth/start' => $this->oauthStart($query),
                $method === 'GET' && $path === '/oauth/callback' => $this->oauthCallback($query),
                $method === 'POST' && $path === '/signup/whatsapp/complete' => $this->whatsappComplete($raw),
                $method === 'POST' && $path === '/v1/grants/redeem' => $this->signed($h, $raw, fn ($inst, $b) => $this->redeem($inst, $b)),
                $method === 'POST' && $path === '/v1/accounts/register' => $this->signed($h, $raw, fn ($inst, $b) => $this->register($inst, $b)),
                $method === 'POST' && $path === '/v1/accounts/unlink' => $this->signed($h, $raw, fn ($inst, $b) => $this->unlink($inst, $b)),
                $method === 'GET' && $path === '/health' => $this->json(200, ['ok' => true]),
                default => $this->json(404, ['error' => 'not_found']),
            };
        } catch (\Throwable $e) {
            error_log('gateway error: ' . get_class($e) . ' ' . $e->getMessage());

            return $this->json(500, ['error' => 'server_error']);
        }
    }

    private function json(int $status, array $body, array $headers = []): array
    {
        return [$status, $headers + ['Content-Type' => 'application/json'], json_encode($body)];
    }

    private function verify(array $q): array
    {
        $ok = ($q['hub_mode'] ?? '') === 'subscribe' && ($this->cfg['verify_token'] ?? '') !== '' && hash_equals($this->cfg['verify_token'], (string) ($q['hub_verify_token'] ?? ''));

        return $ok ? [200, ['Content-Type' => 'text/plain'], (string) ($q['hub_challenge'] ?? '')] : $this->json(403, ['error' => 'forbidden']);
    }

    private function webhook(string $platform, array $h, string $raw): array
    {
        if (!Signature::meta((string) $this->cfg['meta_app_secret'], $raw, $h['x-hub-signature-256'] ?? '')) {
            return $this->json(401, ['error' => 'bad_signature']);
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return $this->json(400, ['error' => 'bad_json']);
        }
        $routed = 0;
        foreach ((new Router($this->store))->split($platform, $payload) as $installation => $part) {
            $this->deliverer->enqueue($installation, $platform, $part);
            $routed++;
        }

        // 200 whenever the event is safely stored (or has no owner): the provider must not hammer us; retries are ours.
        return $this->json(200, ['ok' => true, 'routed' => $routed]);
    }

    private function signed(array $h, string $raw, callable $fn): array
    {
        $id = (string) ($h['x-softtech-installation'] ?? '');
        $ts = (int) ($h['x-softtech-timestamp'] ?? 0);
        $sig = (string) ($h['x-softtech-signature'] ?? '');
        $inst = $this->store->installation($id);
        $valid = false;
        foreach ($inst['secrets'] ?? [] as $secret) {
            $valid = $valid || Signature::verify($secret, $id, $ts, $raw, $sig);
        }
        if (!$inst || !$valid) {
            return $this->json(401, ['error' => 'unauthorized']);
        }
        // Replay protection: a signature is accepted once.
        $hash = hash('sha256', $sig);
        $this->store->run('DELETE FROM seen_requests WHERE expires_at < ?', [time()]);
        try {
            $this->store->run('INSERT INTO seen_requests (sig_hash, expires_at) VALUES (?,?)', [$hash, time() + 900]);
        } catch (\PDOException $e) {
            return $this->json(409, ['error' => 'replay']);
        }

        return $fn($inst, json_decode($raw, true) ?: []);
    }

    // ── OAuth (Facebook/Instagram) through the gateway's one Meta app ──
    private function oauthStart(array $q): array
    {
        $inst = $this->store->installation((string) ($q['installation_id'] ?? ''));
        $sigIn = (string) ($q['sig'] ?? '');
        $signedPart = $q;
        unset($signedPart['sig']);
        $valid = false;
        foreach ($inst['secrets'] ?? [] as $secret) {
            $valid = $valid || Signature::verify($secret, $inst['id'], (int) ($q['ts'] ?? 0), http_build_query($signedPart), $sigIn);
        }
        $return = (string) ($q['return'] ?? '');
        // The browser may only be sent back to the installation's own domain.
        $host = parse_url($return, PHP_URL_HOST);
        if (!$inst || !$valid || !in_array($q['platform'] ?? '', ['facebook', 'instagram', 'whatsapp'], true) || $host !== $inst['domain'] || parse_url($return, PHP_URL_SCHEME) !== 'https') {
            return $this->json(400, ['error' => 'invalid_request']);
        }
        $state = bin2hex(random_bytes(24));
        $this->store->run('INSERT INTO oauth_states (state_hash,installation_id,platform,return_url,expires_at) VALUES (?,?,?,?,?)', [hash('sha256', $state), $inst['id'], $q['platform'], $return . (str_contains($return, '?') ? '&' : '?') . 'state=' . urlencode((string) ($q['state'] ?? '')), time() + 600]);

        if ($q['platform'] === 'whatsapp') {
            return $this->signupPage($state);
        }

        return [302, ['Location' => 'https://www.facebook.com/' . $this->cfg['graph_version'] . '/dialog/oauth?' . http_build_query([
            'client_id' => $this->cfg['meta_app_id'], 'redirect_uri' => $this->cfg['public_url'] . '/oauth/callback', 'state' => $state, 'response_type' => 'code',
            'scope' => 'pages_show_list,pages_messaging,pages_manage_metadata,instagram_basic,instagram_manage_messages,business_management',
        ])], ''];
    }

    private function oauthCallback(array $q): array
    {
        $st = $this->store->one('SELECT * FROM oauth_states WHERE state_hash=? AND expires_at>?', [hash('sha256', (string) ($q['state'] ?? '')), time()]);
        if (!$st) {
            return $this->json(400, ['error' => 'invalid_state']);
        }
        $this->store->run('DELETE FROM oauth_states WHERE state_hash=?', [$st['state_hash']]); // single use
        $back = fn (array $extra) => [302, ['Location' => $st['return_url'] . '&' . http_build_query($extra)], ''];
        if (empty($q['code'])) {
            return $back(['error' => 'denied']);
        }
        $accounts = ($this->cfg['meta_client'])($q['code']); // injectable: exchanges the code, returns page/IG accounts with tokens
        $granted = [];
        foreach ($accounts as $a) {
            if ($this->store->claim($a['platform'], $a['external_id'], $st['installation_id'])) {
                $granted[] = $a; // an account already owned by another installation is silently not granted
            }
        }
        $grant = bin2hex(random_bytes(32));
        $this->store->run('INSERT INTO grants (token_hash,installation_id,accounts_enc,expires_at) VALUES (?,?,?,?)', [hash('sha256', $grant), $st['installation_id'], $this->store->seal(json_encode($granted)), time() + 300]);

        return $back(['grant' => $grant]);
    }

    private function redeem(array $inst, array $b): array
    {
        $g = $this->store->one('SELECT * FROM grants WHERE token_hash=? AND installation_id=? AND used=0 AND expires_at>?', [hash('sha256', (string) ($b['grant'] ?? '')), $inst['id'], time()]);
        // Atomic one-time use.
        if (!$g || $this->store->run('UPDATE grants SET used=1 WHERE token_hash=? AND used=0', [$g['token_hash']]) !== 1) {
            return $this->json(400, ['error' => 'invalid_grant']);
        }

        return $this->json(200, ['accounts' => json_decode($this->store->open($g['accounts_enc']) ?? '[]', true)]);
    }

    /** For accounts the installation already holds a token for (e.g. WhatsApp). The gateway verifies ownership with the provider. */
    private function register(array $inst, array $b): array
    {
        if (!in_array($b['platform'] ?? '', ['facebook', 'instagram', 'whatsapp'], true) || empty($b['external_id']) || empty($b['access_token'])) {
            return $this->json(422, ['error' => 'invalid']);
        }
        if (!($this->cfg['verify_account'])($b['platform'], (string) $b['external_id'], (string) $b['access_token'])) {
            return $this->json(403, ['error' => 'not_owned']);
        }

        return $this->store->claim($b['platform'], (string) $b['external_id'], $inst['id']) ? $this->json(200, ['ok' => true]) : $this->json(409, ['error' => 'owned_by_other_installation']);
    }

    private function unlink(array $inst, array $b): array
    {
        $this->store->run('DELETE FROM routes WHERE platform=? AND external_id=? AND installation_id=?', [$b['platform'] ?? '', (string) ($b['external_id'] ?? ''), $inst['id']]);

        return $this->json(200, ['ok' => true]);
    }

    // ── WhatsApp Embedded Signup (Tech Provider flow) ──

    private function signupPage(string $state): array
    {
        $j = fn ($v) => json_encode($v, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Connect WhatsApp</title>'
            . '<style>body{font:16px system-ui;max-width:520px;margin:10vh auto;padding:0 16px}button{font:inherit;padding:10px 16px;border-radius:8px;border:1px solid #2563eb;background:#2563eb;color:#fff;cursor:pointer}#m{margin-top:14px;color:#444}.e{color:#b91c1c}label{display:block;margin:12px 0}</style></head><body>'
            . '<h2>Connect a WhatsApp number</h2><p>You will sign in with Meta and choose your WhatsApp Business account and number. No password is shared with this site.</p>'
            . '<label><input type="checkbox" id="co"> This number is already used in the WhatsApp Business app on my phone (keep using both)</label>'
            . '<button id="go" type="button">Continue with Meta</button><div id="m" role="status"></div>'
            . '<script>const S=' . $j($state) . ',APP=' . $j($this->cfg['meta_app_id']) . ',CFG=' . $j($this->cfg['wa_config_id'] ?? '') . ',VER=' . $j($this->cfg['graph_version']) . ';'
            . 'let session=null,code=null,coex=false;const m=document.getElementById("m");const say=(t,e)=>{m.textContent=t;m.className=e?"e":""};'
            . 'window.fbAsyncInit=function(){FB.init({appId:APP,autoLogAppEvents:true,xfbml:true,version:VER})};'
            . 'window.addEventListener("message",function(ev){if(!ev.origin||!/(^|\\.)facebook\\.com$/.test(new URL(ev.origin).hostname))return;try{const d=JSON.parse(ev.data);if(d.type==="WA_EMBEDDED_SIGNUP"){if(d.event==="CANCEL"){say("Cancelled.",1);return}if(d.event&&d.event.indexOf("FINISH")===0){session=d.data;done()}else if(d.event==="ERROR"){say("Meta reported an error. Please try again.",1)}}}catch(e){}});'
            . 'function done(){if(!session||!code)return;say("Finishing…");fetch("/signup/whatsapp/complete",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({state:S,code:code,phone_number_id:session.phone_number_id,waba_id:session.waba_id,business_id:session.business_id||null,coexistence:coex})}).then(r=>r.json()).then(j=>{if(j.redirect){location.href=j.redirect}else{say(j.message||"Could not complete.",1)}}).catch(()=>say("Network error.",1))}'
            . 'document.getElementById("go").onclick=function(){coex=document.getElementById("co").checked;const extras={setup:{},sessionInfoVersion:"3"};if(coex)extras.featureType="whatsapp_business_app_onboarding";'
            . 'FB.login(function(r){if(r.authResponse&&r.authResponse.code){code=r.authResponse.code;done()}else{say("Login was cancelled.",1)}},{config_id:CFG,response_type:"code",override_default_response_type:true,extras:extras})};'
            . '</script><script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js"></script></body></html>';

        return [200, ['Content-Type' => 'text/html; charset=utf-8', 'X-Frame-Options' => 'DENY', 'Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'no-store'], $html];
    }

    /** Called by the signup page. Authenticated by the single-use state minted in the signed /oauth/start. */
    private function whatsappComplete(string $raw): array
    {
        $b = json_decode($raw, true) ?: [];
        $st = $this->store->one("SELECT * FROM oauth_states WHERE state_hash=? AND platform='whatsapp' AND expires_at>?", [hash('sha256', (string) ($b['state'] ?? '')), time()]);
        if (!$st || empty($b['code']) || !preg_match('/^\d{5,30}$/', (string) ($b['phone_number_id'] ?? '')) || !preg_match('/^\d{5,30}$/', (string) ($b['waba_id'] ?? ''))) {
            return $this->json(400, ['message' => 'This sign-up link expired. Start again from your SoftCommerce admin.']);
        }
        // Single use, taken before any provider call so a replay cannot run the flow twice.
        if ($this->store->run('DELETE FROM oauth_states WHERE state_hash=?', [$st['state_hash']]) !== 1) {
            return $this->json(400, ['message' => 'This sign-up link was already used.']);
        }
        $wa = $this->cfg['wa_client'];
        $coexistence = !empty($b['coexistence']);
        $phone = (string) $b['phone_number_id'];
        $waba = (string) $b['waba_id'];

        try {
            $token = $wa->exchange((string) $b['code']);
            $info = $wa->phone($phone, $token);   // proves this token controls that number
            if (($info['id'] ?? null) !== $phone) {
                return $this->json(403, ['message' => 'Meta did not confirm access to that phone number.']);
            }
            if (!$this->store->claim('whatsapp', $phone, $st['installation_id'])) {
                return $this->json(409, ['message' => 'This number is already connected to another SoftCommerce installation.']);
            }
            $wa->subscribe($waba, $token);        // deliver this WABA's webhooks to the gateway's app
            $pin = null;
            if (!$coexistence) {                  // a number that stays on the Business app is already registered
                $pin = (string) random_int(100000, 999999);
                $wa->register($phone, $token, $pin);
            }
        } catch (\Throwable $e) {
            error_log('whatsapp signup failed: ' . get_class($e));
            // Do not keep a route for a number whose onboarding did not finish.
            $this->store->run("DELETE FROM routes WHERE platform='whatsapp' AND external_id=? AND installation_id=?", [$phone, $st['installation_id']]);

            return $this->json(502, ['message' => 'Meta could not finish the connection. Please try again.']);
        }

        $account = ['platform' => 'whatsapp', 'external_id' => $phone, 'name' => $info['verified_name'] ?? $phone, 'handle' => $info['display_phone_number'] ?? null,
            'access_token' => $token, 'pin' => $pin, 'meta' => ['waba_id' => $waba, 'business_id' => $b['business_id'] ?? null, 'coexistence' => $coexistence]];
        $grant = bin2hex(random_bytes(32));
        $this->store->run('INSERT INTO grants (token_hash,installation_id,accounts_enc,expires_at) VALUES (?,?,?,?)', [hash('sha256', $grant), $st['installation_id'], $this->store->seal(json_encode([$account])), time() + 300]);

        return $this->json(200, ['redirect' => $st['return_url'] . '&' . http_build_query(['grant' => $grant])]);
    }
}
