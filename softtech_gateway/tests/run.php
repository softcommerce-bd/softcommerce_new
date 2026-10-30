<?php
// Dependency-free test runner: php tests/run.php
putenv('GATEWAY_DSN=sqlite::memory:');
putenv('GATEWAY_KEY=' . base64_encode(str_repeat('k', 32)));
putenv('META_APP_SECRET=appsecret');
putenv('META_WEBHOOK_VERIFY_TOKEN=vt');
putenv('GATEWAY_PUBLIC_URL=https://gw.test');
require __DIR__ . '/../src/bootstrap.php';

use Gateway\App;
use Gateway\Deliverer;
use Gateway\Signature;

$pass = 0; $fail = 0;
function t(string $name, callable $fn): void { global $pass, $fail; try { $fn(); $pass++; echo "  ok   $name\n"; } catch (Throwable $e) { $fail++; echo "  FAIL $name: {$e->getMessage()}\n"; } }
function eq($a, $b, string $m = '') { if ($a !== $b) throw new Exception(($m ?: 'not equal') . ' — got ' . var_export($a, true) . ', want ' . var_export($b, true)); }

$sent = []; $status = 200;
$mk = function () use (&$sent, &$status) {
    $db = new PDO('sqlite::memory:');
    $store = new Gateway\Store($db, str_repeat('k', 32)); $store->migrate();
    $del = new Deliverer($store, function ($url, $headers, $body) use (&$sent, &$status) { $sent[] = compact('url', 'headers', 'body'); return $status; });
    $cfg = ['meta_app_secret' => 'appsecret', 'verify_token' => 'vt', 'graph_version' => 'v21.0', 'public_url' => 'https://gw.test', 'meta_app_id' => '1',
        'meta_client' => fn ($code) => [['platform' => 'facebook', 'external_id' => 'PAGE_A', 'name' => 'A', 'access_token' => 'tok', 'flow' => 'facebook_login']],
        'verify_account' => fn ($p, $id, $tok) => $tok === 'good'];
    return [$store, $del, new App($store, $del, $cfg)];
};
$msgPayload = fn (string $page) => ['object' => 'page', 'entry' => [['id' => $page, 'messaging' => [['sender' => ['id' => 'U1'], 'recipient' => ['id' => $page], 'timestamp' => 1, 'message' => ['mid' => 'm1', 'text' => 'hi']]]]]];
$hook = function (App $app, string $platform, array $p, string $secret = 'appsecret') { $raw = json_encode($p); return $app->handle('POST', "/webhook/$platform", [], ['X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', $raw, $secret)], $raw); };
$signed = function (App $app, string $path, string $id, string $secret, array $body, ?int $ts = null) { $raw = json_encode($body); $ts ??= time(); return $app->handle('POST', $path, [], ['X-Softtech-Installation' => $id, 'X-Softtech-Timestamp' => (string) $ts, 'X-Softtech-Signature' => Signature::sign($secret, $id, $ts, $raw)], $raw); };

echo "gateway\n";
t('rejects webhook with bad Meta signature', function () use ($mk, $hook, $msgPayload) { [, , $app] = $mk(); eq($hook($app, 'facebook', $msgPayload('P'), 'wrong')[0], 401); });
t('routes only to the owning installation (isolation A vs B)', function () use ($mk, $hook, $msgPayload, &$sent) {
    [$s, , $app] = $mk(); $sent = [];
    $a = $s->createInstallation('A', 'a.test', 'https://a.test/api/chat/gateway/ingest'); $b = $s->createInstallation('B', 'b.test', 'https://b.test/api/chat/gateway/ingest');
    $s->claim('facebook', 'PAGE_A', $a['id']); $s->claim('facebook', 'PAGE_B', $b['id']);
    $hook($app, 'facebook', $msgPayload('PAGE_A'));
    eq(count($sent), 1); eq($sent[0]['url'], 'https://a.test/api/chat/gateway/ingest');
    eq(str_contains($sent[0]['body'], 'PAGE_B'), false);
});
t('one webhook carrying two installations is split, each sees only its own entry', function () use ($mk, $hook, &$sent) {
    [$s, , $app] = $mk(); $sent = [];
    $a = $s->createInstallation('A', 'a.test', 'https://a.test/i'); $b = $s->createInstallation('B', 'b.test', 'https://b.test/i');
    $s->claim('facebook', 'PA', $a['id']); $s->claim('facebook', 'PB', $b['id']);
    $p = ['object' => 'page', 'entry' => [['id' => 'PA', 'messaging' => [['x' => 1]]], ['id' => 'PB', 'messaging' => [['x' => 2]]], ['id' => 'PUNKNOWN', 'messaging' => [['x' => 3]]]]];
    $hook($app, 'facebook', $p);
    eq(count($sent), 2);
    foreach ($sent as $d) { $body = json_decode($d['body'], true); eq(count($body['payload']['entry']), 1); }
    eq(str_contains(implode('', array_column($sent, 'body')), 'PUNKNOWN'), false, 'unknown account must be dropped');
});
t('whatsapp changes split by phone_number_id', function () use ($mk, $hook, &$sent) {
    [$s, , $app] = $mk(); $sent = [];
    $a = $s->createInstallation('A', 'a.test', 'https://a.test/i'); $b = $s->createInstallation('B', 'b.test', 'https://b.test/i');
    $s->claim('whatsapp', '111', $a['id']); $s->claim('whatsapp', '222', $b['id']);
    $ch = fn ($pn) => ['field' => 'messages', 'value' => ['metadata' => ['phone_number_id' => $pn], 'messages' => [['id' => "w$pn"]]]];
    $hook($app, 'whatsapp', ['object' => 'whatsapp_business_account', 'entry' => [['id' => 'WABA', 'changes' => [$ch('111'), $ch('222')]]]]);
    eq(count($sent), 2);
    foreach ($sent as $d) { $b2 = json_decode($d['body'], true); eq(count($b2['payload']['entry'][0]['changes']), 1); }
});
t('delivery is signed and verifiable by the installation', function () use ($mk, $hook, $msgPayload, &$sent) {
    [$s, , $app] = $mk(); $sent = [];
    $a = $s->createInstallation('A', 'a.test', 'https://a.test/i'); $s->claim('facebook', 'PAGE_A', $a['id']);
    $hook($app, 'facebook', $msgPayload('PAGE_A'));
    $h = []; foreach ($sent[0]['headers'] as $line) { [$k, $v] = explode(': ', $line, 2); $h[$k] = $v; }
    eq(Signature::verify($a['secret'], $a['id'], (int) $h['X-Softtech-Timestamp'], $sent[0]['body'], $h['X-Softtech-Signature']), true);
    eq(Signature::verify('another-secret', $a['id'], (int) $h['X-Softtech-Timestamp'], $sent[0]['body'], $h['X-Softtech-Signature']), false);
});
t('duplicate provider delivery is not forwarded twice', function () use ($mk, $hook, $msgPayload, &$sent) {
    [$s, , $app] = $mk(); $sent = [];
    $a = $s->createInstallation('A', 'a.test', 'https://a.test/i'); $s->claim('facebook', 'PAGE_A', $a['id']);
    $hook($app, 'facebook', $msgPayload('PAGE_A')); $hook($app, 'facebook', $msgPayload('PAGE_A'));
    eq(count($sent), 1);
});
t('failed delivery is kept, retried with backoff, then dead-lettered', function () use ($mk, $hook, $msgPayload, &$sent, &$status) {
    [$s, $d, $app] = $mk(); $sent = []; $status = 503;
    $a = $s->createInstallation('A', 'a.test', 'https://a.test/i'); $s->claim('facebook', 'PAGE_A', $a['id']);
    $hook($app, 'facebook', $msgPayload('PAGE_A'));
    $row = $s->one('SELECT * FROM deliveries'); eq($row['status'], 'pending'); eq((int) $row['attempts'], 1);
    eq((int) $row['next_attempt_at'] >= time() + 29, true, 'first retry waits ~30s');
    for ($i = 0; $i < 12; $i++) { $s->run('UPDATE deliveries SET next_attempt_at=0'); $d->retryDue(); }
    $row = $s->one('SELECT * FROM deliveries'); eq($row['status'], 'dead'); eq((int) $row['attempts'], Deliverer::MAX_ATTEMPTS);
    $status = 200;
});
t('recovers: a pending delivery succeeds when the installation is back', function () use ($mk, $hook, $msgPayload, &$sent, &$status) {
    [$s, $d, $app] = $mk(); $status = 500;
    $a = $s->createInstallation('A', 'a.test', 'https://a.test/i'); $s->claim('facebook', 'PAGE_A', $a['id']);
    $hook($app, 'facebook', $msgPayload('PAGE_A')); $status = 200;
    $s->run('UPDATE deliveries SET next_attempt_at=0'); $d->retryDue();
    eq($s->one('SELECT status FROM deliveries')['status'], 'done');
});
t('revoked installation stops receiving and its routes are removed', function () use ($mk, $hook, $msgPayload, &$sent, &$status) {
    [$s, , $app] = $mk(); $sent = []; $status = 200;
    $a = $s->createInstallation('A', 'a.test', 'https://a.test/i'); $s->claim('facebook', 'PAGE_A', $a['id']);
    $s->revoke($a['id']); $hook($app, 'facebook', $msgPayload('PAGE_A'));
    eq(count($sent), 0); eq($s->owner('facebook', 'PAGE_A'), null);
});
t('an account owned by installation A cannot be claimed by B', function () use ($mk) {
    [$s] = $mk(); $a = $s->createInstallation('A', 'a.test', 'https://a.test/i'); $b = $s->createInstallation('B', 'b.test', 'https://b.test/i');
    eq($s->claim('facebook', 'P', $a['id']), true); eq($s->claim('facebook', 'P', $b['id']), false); eq($s->claim('facebook', 'P', $a['id']), true);
});
t('installation callback must be https', function () use ($mk) { [$s] = $mk(); try { $s->createInstallation('A', 'a.test', 'http://a.test/i'); throw new Exception('accepted http'); } catch (InvalidArgumentException $e) { eq(true, true); } });
t('signed request: rejects bad signature, stale timestamp, and replay', function () use ($mk, $signed) {
    [$s, , $app] = $mk(); $a = $s->createInstallation('A', 'a.test', 'https://a.test/i');
    eq($signed($app, '/v1/accounts/unlink', $a['id'], 'nope', [])[0], 401);
    eq($signed($app, '/v1/accounts/unlink', $a['id'], $a['secret'], [], time() - 4000)[0], 401);
    $raw = json_encode([]); $ts = time(); $h = ['X-Softtech-Installation' => $a['id'], 'X-Softtech-Timestamp' => (string) $ts, 'X-Softtech-Signature' => Signature::sign($a['secret'], $a['id'], $ts, $raw)];
    eq($app->handle('POST', '/v1/accounts/unlink', [], $h, $raw)[0], 200); eq($app->handle('POST', '/v1/accounts/unlink', [], $h, $raw)[0], 409, 'replay');
});
t('secret rotation: old secret works during grace, new works, then old expires', function () use ($mk, $signed) {
    [$s, , $app] = $mk(); $a = $s->createInstallation('A', 'a.test', 'https://a.test/i'); $new = $s->rotate($a['id'], 60);
    eq($signed($app, '/v1/accounts/unlink', $a['id'], $a['secret'], ['n' => 1])[0], 200); eq($signed($app, '/v1/accounts/unlink', $a['id'], $new, ['n' => 2])[0], 200);
    $s->run('UPDATE installations SET prev_expires_at=1'); eq($signed($app, '/v1/accounts/unlink', $a['id'], $a['secret'], ['n' => 3])[0], 401);
});
t('register verifies ownership with provider and refuses a second installation', function () use ($mk, $signed) {
    [$s, , $app] = $mk(); $a = $s->createInstallation('A', 'a.test', 'https://a.test/i'); $b = $s->createInstallation('B', 'b.test', 'https://b.test/i');
    eq($signed($app, '/v1/accounts/register', $a['id'], $a['secret'], ['platform' => 'whatsapp', 'external_id' => '555', 'access_token' => 'bad'])[0], 403);
    eq($signed($app, '/v1/accounts/register', $a['id'], $a['secret'], ['platform' => 'whatsapp', 'external_id' => '555', 'access_token' => 'good'])[0], 200);
    eq($signed($app, '/v1/accounts/register', $b['id'], $b['secret'], ['platform' => 'whatsapp', 'external_id' => '555', 'access_token' => 'good'])[0], 409);
});
t('oauth: only returns to the installation domain; grant is single-use and bound to the installation', function () use ($mk, $signed) {
    [$s, , $app] = $mk(); $a = $s->createInstallation('A', 'a.test', 'https://a.test/i'); $b = $s->createInstallation('B', 'b.test', 'https://b.test/i');
    $start = function ($ret) use ($app, $a) { $q = ['installation_id' => $a['id'], 'platform' => 'facebook', 'state' => 'S1', 'return' => $ret, 'ts' => time()]; $q['sig'] = Signature::sign($a['secret'], $a['id'], $q['ts'], http_build_query($q)); return $app->handle('GET', '/oauth/start', $q, [], ''); };
    eq($start('https://evil.test/cb')[0], 400, 'foreign domain refused');
    $r = $start('https://a.test/cb'); eq($r[0], 302);
    parse_str(parse_url($r[1]['Location'], PHP_URL_QUERY), $m);
    $cb = $app->handle('GET', '/oauth/callback', ['state' => $m['state'], 'code' => 'c'], [], ''); eq($cb[0], 302);
    parse_str(parse_url($cb[1]['Location'], PHP_URL_QUERY), $back); eq($back['state'], 'S1');
    eq($app->handle('GET', '/oauth/callback', ['state' => $m['state'], 'code' => 'c'], [], '')[0], 400, 'state is single use');
    eq($signed($app, '/v1/grants/redeem', $b['id'], $b['secret'], ['grant' => $back['grant']])[0], 400, 'other installation cannot redeem');
    $ok = $signed($app, '/v1/grants/redeem', $a['id'], $a['secret'], ['grant' => $back['grant']]); eq($ok[0], 200); eq(json_decode($ok[2], true)['accounts'][0]['external_id'], 'PAGE_A');
    eq($signed($app, '/v1/grants/redeem', $a['id'], $a['secret'], ['grant' => $back['grant']], time() + 1)[0], 400, 'grant is single use');
    eq($s->owner('facebook', 'PAGE_A'), $a['id']);
});
t('webhook verify handshake', function () use ($mk) { [, , $app] = $mk(); eq($app->handle('GET', '/webhook/facebook', ['hub_mode' => 'subscribe', 'hub_verify_token' => 'vt', 'hub_challenge' => '42'], [], '')[2], '42'); eq($app->handle('GET', '/webhook/facebook', ['hub_mode' => 'subscribe', 'hub_verify_token' => 'x', 'hub_challenge' => '42'], [], '')[0], 403); });


// ── WhatsApp Embedded Signup ──
$waCalls = [];
$fakeWa = new class($waCalls) {
    public array $calls = []; public bool $fail = false; public string $phoneId = '105500123';
    public function __construct(public &$ref) {}
    public function exchange(string $code): string { $this->calls[] = 'exchange'; if ($this->fail) throw new RuntimeException('x'); return 'BIZTOKEN'; }
    public function phone(string $id, string $t): array { $this->calls[] = "phone:$id"; return ['id' => $this->phoneId, 'display_phone_number' => '+880 1700-000000', 'verified_name' => 'Shop BD']; }
    public function subscribe(string $waba, string $t): void { $this->calls[] = "subscribe:$waba"; }
    public function register(string $id, string $t, string $pin): void { $this->calls[] = "register:$id:" . strlen($pin); }
};
$mkWa = function () use ($mk, $fakeWa) { [$s, $d, $app0] = $mk(); $wa = clone $fakeWa; $wa->calls = []; $cfg = ['meta_app_secret' => 'appsecret', 'verify_token' => 'vt', 'graph_version' => 'v25.0', 'public_url' => 'https://gw.test', 'meta_app_id' => '1', 'wa_config_id' => 'CFG123', 'wa_client' => $wa, 'meta_client' => fn () => [], 'verify_account' => fn () => true]; return [$s, new App($s, $d, $cfg), $wa]; };
$startWa = function (App $app, array $inst, string $ret = 'https://a.test/cb') { $q = ['installation_id' => $inst['id'], 'platform' => 'whatsapp', 'state' => 'S9', 'return' => $ret, 'ts' => time()]; $q['sig'] = Signature::sign($inst['secret'], $inst['id'], $q['ts'], http_build_query($q)); return $app->handle('GET', '/oauth/start', $q, [], ''); };
$stateFrom = function (array $startResp) { preg_match('/const S="([0-9a-f]+)"/', $startResp[2], $m); return $m[1] ?? ''; };
$complete = fn (App $app, array $body) => $app->handle('POST', '/signup/whatsapp/complete', [], [], json_encode($body));

echo "whatsapp embedded signup\n";
t('signup page carries the config id and the official parameters, and is not frameable', function () use ($mkWa, $startWa) {
    [$s, $app] = $mkWa(); $a = $s->createInstallation('A', 'a.test', 'https://a.test/i'); $r = $startWa($app, $a);
    eq($r[0], 200); eq($r[1]['X-Frame-Options'], 'DENY');
    foreach (['config_id:CFG', 'response_type:"code"', 'override_default_response_type:true', 'sessionInfoVersion:"3"', 'whatsapp_business_app_onboarding', 'WA_EMBEDDED_SIGNUP', 'FINISH'] as $needle) eq(str_contains($r[2], $needle), true, "page lacks $needle");
});
t('page refuses a return URL outside the installation domain', function () use ($mkWa, $startWa) { [$s, $app] = $mkWa(); $a = $s->createInstallation('A', 'a.test', 'https://a.test/i'); eq($startWa($app, $a, 'https://evil.test/cb')[0], 400); });
t('complete: exchanges, verifies the number, claims, subscribes, registers, returns a one-time grant', function () use ($mkWa, $startWa, $stateFrom, $complete) {
    [$s, $app, $wa] = $mkWa(); $a = $s->createInstallation('A', 'a.test', 'https://a.test/i'); $st = $stateFrom($startWa($app, $a));
    $r = $complete($app, ['state' => $st, 'code' => 'C', 'phone_number_id' => '105500123', 'waba_id' => '900100123', 'business_id' => '77', 'coexistence' => false]);
    eq($r[0], 200); eq($wa->calls, ['exchange', 'phone:105500123', 'subscribe:900100123', 'register:105500123:6']);
    eq($s->owner('whatsapp', '105500123'), $a['id']);
    $loc = json_decode($r[2], true)['redirect']; parse_str(parse_url($loc, PHP_URL_QUERY), $q); eq($q['state'], 'S9');
    $raw = json_encode(['grant' => $q['grant']]); $ts = time();
    $g = $app->handle('POST', '/v1/grants/redeem', [], ['X-Softtech-Installation' => $a['id'], 'X-Softtech-Timestamp' => (string) $ts, 'X-Softtech-Signature' => Signature::sign($a['secret'], $a['id'], $ts, $raw)], $raw);
    $acc = json_decode($g[2], true)['accounts'][0];
    eq([$acc['platform'], $acc['external_id'], $acc['meta']['waba_id'], $acc['meta']['coexistence'], $acc['access_token']], ['whatsapp', '105500123', '900100123', false, 'BIZTOKEN']);
});
t('coexistence: number stays on the Business app, so it is NOT re-registered', function () use ($mkWa, $startWa, $stateFrom, $complete) {
    [$s, $app, $wa] = $mkWa(); $a = $s->createInstallation('A', 'a.test', 'https://a.test/i'); $st = $stateFrom($startWa($app, $a));
    $r = $complete($app, ['state' => $st, 'code' => 'C', 'phone_number_id' => '105500123', 'waba_id' => '900100123', 'coexistence' => true]);
    eq($r[0], 200); eq($wa->calls, ['exchange', 'phone:105500123', 'subscribe:900100123']);
});
t('state is single use', function () use ($mkWa, $startWa, $stateFrom, $complete) {
    [$s, $app] = $mkWa(); $a = $s->createInstallation('A', 'a.test', 'https://a.test/i'); $st = $stateFrom($startWa($app, $a)); $b = ['state' => $st, 'code' => 'C', 'phone_number_id' => '105500123', 'waba_id' => '900100123'];
    eq($complete($app, $b)[0], 200); eq($complete($app, $b)[0], 400);
});
t('unknown or malformed state/ids are refused before any provider call', function () use ($mkWa, $complete) { [, $app, $wa] = $mkWa(); eq($complete($app, ['state' => 'nope', 'code' => 'C', 'phone_number_id' => '1', 'waba_id' => '2'])[0], 400); eq($wa->calls, []); });
t('a token that does not control the claimed number is refused and nothing is routed', function () use ($mkWa, $startWa, $stateFrom, $complete) {
    [$s, $app, $wa] = $mkWa(); $wa->phoneId = 'SOMEONE_ELSES'; $a = $s->createInstallation('A', 'a.test', 'https://a.test/i'); $st = $stateFrom($startWa($app, $a));
    eq($complete($app, ['state' => $st, 'code' => 'C', 'phone_number_id' => '105500123', 'waba_id' => '900100123'])[0], 403); eq($s->owner('whatsapp', '105500123'), null);
});
t('a number owned by another installation cannot be claimed (multi-tenant hijack)', function () use ($mkWa, $startWa, $stateFrom, $complete) {
    [$s, $app, $wa] = $mkWa(); $a = $s->createInstallation('A', 'a.test', 'https://a.test/i'); $b = $s->createInstallation('B', 'b.test', 'https://b.test/i'); $s->claim('whatsapp', '105500123', $a['id']);
    $st = $stateFrom($startWa($app, $b, 'https://b.test/cb')); $r = $complete($app, ['state' => $st, 'code' => 'C', 'phone_number_id' => '105500123', 'waba_id' => '900100123']);
    eq($r[0], 409); eq($s->owner('whatsapp', '105500123'), $a['id']); eq(in_array('subscribe:900100123', $wa->calls), false, 'must not subscribe a WABA it could not claim');
});
t('provider failure mid-flow leaves no route and no grant', function () use ($mkWa, $startWa, $stateFrom, $complete) {
    [$s, $app, $wa] = $mkWa(); $wa->fail = true; $a = $s->createInstallation('A', 'a.test', 'https://a.test/i'); $st = $stateFrom($startWa($app, $a));
    eq($complete($app, ['state' => $st, 'code' => 'C', 'phone_number_id' => '105500123', 'waba_id' => '900100123'])[0], 502); eq($s->owner('whatsapp', '105500123'), null); eq((int) $s->one('SELECT COUNT(*) c FROM grants')['c'], 0);
});
t('multiple numbers for one installation: each signup adds one', function () use ($mkWa, $startWa, $stateFrom, $complete) {
    [$s, $app, $wa] = $mkWa(); $a = $s->createInstallation('A', 'a.test', 'https://a.test/i');
    foreach (['105500123', '105500124', '105500125'] as $n) { $wa->phoneId = $n; $st = $stateFrom($startWa($app, $a)); eq($complete($app, ['state' => $st, 'code' => 'C', 'phone_number_id' => $n, 'waba_id' => '900100123'])[0], 200); }
    foreach (['105500123', '105500124', '105500125'] as $n) eq($s->owner('whatsapp', $n), $a['id']);
});

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
