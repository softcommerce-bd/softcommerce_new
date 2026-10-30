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

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
