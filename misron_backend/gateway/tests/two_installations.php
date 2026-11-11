<?php
// End-to-end isolation test: one gateway, TWO independent SoftCommerce installations (separate Laravel server
// processes, separate databases, separate installation ids/secrets), real HTTP between them. No provider is contacted.
// Run: php tests/two_installations.php [path-to-misron_backend]
putenv('GATEWAY_DSN=sqlite::memory:');
putenv('GATEWAY_KEY=' . base64_encode(str_repeat('g', 32)));
putenv('META_APP_SECRET=appsecret');
require __DIR__ . '/../src/bootstrap.php';

use Gateway\Deliverer;
use Gateway\Signature;

$laravel = realpath($argv[1] ?? __DIR__ . '/../../misron_backend');
$tmp = sys_get_temp_dir() . '/two-inst-' . getmypid();
mkdir($tmp);
$appKey = 'base64:' . base64_encode(random_bytes(32));
$ok = 0; $bad = 0; $procs = [];
$check = function (string $name, bool $cond, string $detail = '') use (&$ok, &$bad) { $cond ? $ok++ : $bad++; echo ($cond ? '  ok   ' : '  FAIL ') . $name . ($cond ? '' : " — $detail") . "\n"; };

function env_for(array $o, string $appKey): array { return $o + ['APP_KEY' => $appKey, 'APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'sqlite', 'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'LOG_CHANNEL' => 'stderr', 'CHAT_MODE' => 'gateway', 'PATH' => getenv('PATH'), 'HOME' => getenv('HOME')]; }
function prep(string $laravel, string $db, array $env): void
{
    $pdo = new PDO("sqlite:$db");
    $pdo->exec('CREATE TABLE categories (id INTEGER PRIMARY KEY, parent_id INTEGER DEFAULT 0, name TEXT)');
    $pdo->exec('CREATE TABLE permissions (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, section TEXT, guard_name TEXT, created_at TEXT, updated_at TEXT)');
    $pdo->exec('CREATE TABLE role_has_permissions (permission_id INT, role_id INT)'); $pdo->exec('CREATE TABLE model_has_permissions (permission_id INT, model_type TEXT, model_id INT)');
    $e = ''; foreach ($env as $k => $v) $e .= $k . '=' . escapeshellarg($v) . ' ';
    exec("cd " . escapeshellarg($laravel) . " && env $e php artisan migrate --path=database/migrations/unified_chat --force 2>&1", $out, $rc);
    if ($rc !== 0) { fwrite(STDERR, implode("\n", $out) . "\n"); exit(2); }
}
function serve(string $laravel, int $port, array $env, array &$procs)
{
    $p = proc_open(['php', '-S', "127.0.0.1:$port", '-t', "$laravel/public", "$laravel/server.php"], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, $laravel, $env);
    for ($i = 0; $i < 50; $i++) { if (@fsockopen('127.0.0.1', $port, $e, $s, 0.2)) break; usleep(100000); }
    $procs[$port] = $p;
}
function stop(int $port, array &$procs) { if (isset($procs[$port])) { $st = proc_get_status($procs[$port]); @exec('pkill -P ' . $st['pid']); proc_terminate($procs[$port]); proc_close($procs[$port]); unset($procs[$port]); usleep(300000); } }
$count = fn (string $db, string $sql) => (int) (new PDO("sqlite:$db"))->query($sql)->fetchColumn();

// ── gateway with a real-HTTP delivery transport (https-only is enforced in production; this local transport is test-only) ──
$store = new Gateway\Store(new PDO('sqlite::memory:'), str_repeat('g', 32)); $store->migrate();
$http = function (string $url, array $headers, string $body): int { $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]); curl_exec($ch); $c = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); return $c; };
$deliverer = new Deliverer($store, $http);
$cfg = ['meta_app_secret' => 'appsecret', 'verify_token' => 'v', 'graph_version' => 'v25.0', 'public_url' => 'https://gw.test', 'meta_app_id' => '1',
    'meta_client' => fn ($c) => [['platform' => 'facebook', 'external_id' => 'PAGE_A', 'name' => 'A page', 'access_token' => 'TOKEN_FOR_A', 'flow' => 'facebook_login']], 'verify_account' => fn () => true];
$app = new Gateway\App($store, $deliverer, $cfg);
$meta = function (array $entries) use ($app) { $raw = json_encode(['object' => 'page', 'entry' => $entries]); return $app->handle('POST', '/webhook/facebook', [], ['X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', $raw, 'appsecret')], $raw); };
$entry = fn (string $page, string $mid, string $text) => ['id' => $page, 'time' => 1, 'messaging' => [['sender' => ['id' => 'CUST_' . $page], 'recipient' => ['id' => $page], 'timestamp' => 1700000000000, 'message' => ['mid' => $mid, 'text' => $text]]]];

// ── two installations ──
$A = $store->createInstallation('Brand A', 'brand-a.test', 'https://brand-a.test/api/chat/gateway/ingest');
$B = $store->createInstallation('Brand B', 'brand-b.test', 'https://brand-b.test/api/chat/gateway/ingest');
$store->run('UPDATE installations SET callback_url=? WHERE id=?', ['http://127.0.0.1:8101/api/chat/gateway/ingest', $A['id']]);
$store->run('UPDATE installations SET callback_url=? WHERE id=?', ['http://127.0.0.1:8102/api/chat/gateway/ingest', $B['id']]);
$dbA = "$tmp/a.sqlite"; $dbB = "$tmp/b.sqlite";
$envA = env_for(['DB_DATABASE' => $dbA, 'CHAT_INSTALLATION_ID' => $A['id'], 'CHAT_INSTALLATION_SECRET' => $A['secret'], 'CHAT_GATEWAY_URL' => 'https://gw.test'], $appKey);
$envB = env_for(['DB_DATABASE' => $dbB, 'CHAT_INSTALLATION_ID' => $B['id'], 'CHAT_INSTALLATION_SECRET' => $B['secret'], 'CHAT_GATEWAY_URL' => 'https://gw.test'], $appKey);
prep($laravel, $dbA, $envA); prep($laravel, $dbB, $envB);
foreach ([[$dbA, 'PAGE_A'], [$dbB, 'PAGE_B']] as [$db, $page]) { (new PDO("sqlite:$db"))->exec("INSERT INTO chat_accounts (platform, external_id, name, status, inbox_enabled, created_at, updated_at) VALUES ('facebook', '$page', '$page', 'connected', 1, datetime('now'), datetime('now'))"); }
$store->claim('facebook', 'PAGE_A', $A['id']); $store->claim('facebook', 'PAGE_B', $B['id']);
serve($laravel, 8101, $envA, $procs); serve($laravel, 8102, $envB, $procs);

echo "two independent installations\n";
$meta([$entry('PAGE_A', 'mid.a1', 'for A only'), $entry('PAGE_B', 'mid.b1', 'for B only'), $entry('PAGE_C', 'mid.c1', 'nobody owns this')]);
$msgs = fn ($db) => (new PDO("sqlite:$db"))->query('select body from chat_messages order by id')->fetchAll(PDO::FETCH_COLUMN);
$check('A received only its own message', $msgs($dbA) === ['for A only'], json_encode($msgs($dbA)));
$check('B received only its own message', $msgs($dbB) === ['for B only'], json_encode($msgs($dbB)));
$check('unowned account event stored nowhere', $count($dbA, "select count(*) from chat_messages where body like '%nobody%'") + $count($dbB, "select count(*) from chat_messages where body like '%nobody%'") === 0);

// forged delivery: B's secret presented to A, and A's id with a wrong secret
$forge = function (int $port, string $id, string $secret) { $body = json_encode(['event_id' => 'forged', 'platform' => 'facebook', 'payload' => ['object' => 'page', 'entry' => []]]); $ts = time(); $ch = curl_init("http://127.0.0.1:$port/api/chat/gateway/ingest"); curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json', "X-Softtech-Installation: $id", "X-Softtech-Timestamp: $ts", 'X-Softtech-Signature: ' . Signature::sign($secret, $id, $ts, $body)]]); curl_exec($ch); return (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); };
$check('installation A rejects a delivery signed with B\'s secret', $forge(8101, $A['id'], $B['secret']) === 401);
$check('installation A rejects B\'s id', $forge(8101, $B['id'], $B['secret']) === 401);
$check('installation B accepts its own valid signature', $forge(8102, $B['id'], $B['secret']) === 200);

// outage + retry, no loss, no duplicate
stop(8102, $procs);
$meta([$entry('PAGE_B', 'mid.b2', 'sent while B was down')]);
$row = $store->one("SELECT status, attempts FROM deliveries WHERE payload LIKE '%mid.b2%'");
$check('event is kept pending while the installation is down', $row && $row['status'] === 'pending' && (int) $row['attempts'] === 1, json_encode($row));
serve($laravel, 8102, $envB, $procs);
$store->run('UPDATE deliveries SET next_attempt_at=0'); $deliverer->retryDue();
$check('retry delivers it after recovery', in_array('sent while B was down', $msgs($dbB), true));
$meta([$entry('PAGE_B', 'mid.b2', 'sent while B was down')]); // provider redelivers the same event
$store->run('UPDATE deliveries SET next_attempt_at=0 WHERE status="pending"'); $deliverer->retryDue();
$check('provider redelivery creates no duplicate', $count($dbB, "select count(*) from chat_messages where body='sent while B was down'") === 1);
$check('delivery marked done', ($store->one("SELECT status FROM deliveries WHERE payload LIKE '%mid.b2%'")['status'] ?? '') === 'done');

// rotation: gateway now signs with the new secret; B still has the old one until updated
$new = $store->rotate($B['id'], 3600);
$meta([$entry('PAGE_B', 'mid.b3', 'after rotation')]);
$check('delivery signed with the new secret is refused by B until B is updated', !in_array('after rotation', $msgs($dbB), true));
stop(8102, $procs); $envB['CHAT_INSTALLATION_SECRET'] = $new; serve($laravel, 8102, $envB, $procs);
$store->run('UPDATE deliveries SET next_attempt_at=0'); $deliverer->retryDue();
$check('after B adopts the new secret the pending event is delivered', in_array('after rotation', $msgs($dbB), true));

// revocation
$store->revoke($A['id']);
$meta([$entry('PAGE_A', 'mid.a2', 'after A revoked')]);
$check('revoked installation receives nothing and its routes are gone', !in_array('after A revoked', $msgs($dbA), true) && $store->owner('facebook', 'PAGE_A') === null);
$check('revoked installation cannot use signed gateway APIs', (function () use ($app, $A) { $raw = '{}'; $ts = time(); return $app->handle('POST', '/v1/accounts/unlink', [], ['X-Softtech-Installation' => $A['id'], 'X-Softtech-Timestamp' => (string) $ts, 'X-Softtech-Signature' => Signature::sign($A['secret'], $A['id'], $ts, $raw)], $raw)[0]; })() === 401);
$check('B is unaffected by A\'s revocation', (function () use ($meta, $msgs, $dbB, $entry) { $meta([$entry('PAGE_B', 'mid.b4', 'B still works')]); return in_array('B still works', $msgs($dbB), true); })());

// OAuth grant routing between the two installations
$C = $store->createInstallation('Brand C', 'brand-c.test', 'https://brand-c.test/i');
$q = ['installation_id' => $B['id'], 'platform' => 'facebook', 'state' => 'ST', 'return' => 'https://brand-b.test/admin/unified-chat/connect/callback', 'ts' => time()]; $q['sig'] = Signature::sign($new, $B['id'], $q['ts'], http_build_query($q));
$r = $app->handle('GET', '/oauth/start', $q, [], ''); parse_str(parse_url($r[1]['Location'], PHP_URL_QUERY), $m);
$cb = $app->handle('GET', '/oauth/callback', ['state' => $m['state'], 'code' => 'x'], [], ''); parse_str(parse_url($cb[1]['Location'], PHP_URL_QUERY), $back);
$sig = fn ($inst, $sec, $body, $ts) => ['X-Softtech-Installation' => $inst, 'X-Softtech-Timestamp' => (string) $ts, 'X-Softtech-Signature' => Signature::sign($sec, $inst, $ts, $body)];
$raw = json_encode(['grant' => $back['grant']]); $ts = time();
$check('OAuth returns the browser to the requesting installation\'s own domain', str_starts_with($cb[1]['Location'], 'https://brand-b.test/'));
$check('another installation cannot redeem the grant', $app->handle('POST', '/v1/grants/redeem', [], $sig($C['id'], $C['secret'], $raw, $ts), $raw)[0] === 400);
$res = $app->handle('POST', '/v1/grants/redeem', [], $sig($B['id'], $new, $raw, $ts + 1), $raw);
$check('the requesting installation redeems it once', $res[0] === 200 && $store->owner('facebook', 'PAGE_A') === $B['id']);

// credential security
$check('installation secrets are encrypted at rest in the gateway DB', $store->one('SELECT secret_enc FROM installations WHERE id=?', [$B['id']])['secret_enc'] !== $new && !str_contains(json_encode($store->db->query('SELECT * FROM installations')->fetchAll()), $A['secret']));
$check('delivered payloads carry no provider tokens', !str_contains(json_encode($store->db->query('SELECT payload FROM deliveries')->fetchAll()), 'TOKEN_FOR_A'));

foreach (array_keys($procs) as $p) stop($p, $procs);
exec('rm -rf ' . escapeshellarg($tmp));
echo "\n$ok passed, $bad failed\n"; exit($bad ? 1 : 0);
