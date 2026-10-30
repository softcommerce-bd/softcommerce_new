<?php

/*
| Real queue worker + scheduler smoke test for Unified Chat. Uses the database queue and spawns actual
| `php artisan queue:work` processes (the PHPUnit suite runs jobs synchronously, which would hide worker problems).
| Refuses to run unless DB_DATABASE ends in "_test". It only touches chat_* rows and the jobs table.
|
|   DB_CONNECTION=mysql DB_DATABASE=misron_test DB_USERNAME=.. DB_PASSWORD=.. APP_KEY=.. php tests/integration/queue_worker_smoke.php
*/

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
putenv('QUEUE_CONNECTION=database'); $_ENV['QUEUE_CONNECTION'] = $_SERVER['QUEUE_CONNECTION'] = 'database';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Jobs\Chat\SendChatMessage;
use App\Models\ChatAccount;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatWebhookEvent;
use App\Services\Chat\WebhookIngest;
use Illuminate\Support\Facades\DB;

if (substr(DB::connection()->getDatabaseName(), -5) !== '_test') {
    fwrite(STDERR, "Refusing to run: DB_DATABASE must end in _test.\n");
    exit(2);
}
$ok = 0; $bad = 0;
$check = function (string $n, bool $c, string $d = '') use (&$ok, &$bad) { $c ? $ok++ : $bad++; echo ($c ? '  ok   ' : '  FAIL ') . $n . ($c ? '' : " — $d") . "\n"; };
$worker = function (string $extra = '--stop-when-empty') { $cmd = 'cd ' . escapeshellarg(dirname(__DIR__, 2)) . ' && env -u HTTPS_PROXY -u https_proxy -u HTTP_PROXY -u http_proxy QUEUE_CONNECTION=database php artisan queue:work database ' . $extra . ' --tries=1 2>&1'; exec($cmd, $o); return implode("\n", $o); };
$jobsLeft = fn () => (int) DB::table('jobs')->count();

foreach (['chat_messages', 'chat_conversations', 'chat_accounts', 'chat_webhook_events', 'chat_audit_logs'] as $t) DB::table($t)->delete();
DB::table('jobs')->delete();
$acc = new ChatAccount(['platform' => 'facebook', 'external_id' => 'WORKER_PAGE', 'name' => 'Worker page', 'status' => 'connected']);
$acc->credentials = ['access_token' => 'tok']; $acc->save();

echo "queue worker\n";
// 1. a stored webhook event is processed by a real worker
$payload = ['object' => 'page', 'entry' => [['id' => 'WORKER_PAGE', 'time' => 1, 'messaging' => [['sender' => ['id' => 'U1'], 'recipient' => ['id' => 'WORKER_PAGE'], 'timestamp' => (int) (microtime(true) * 1000), 'message' => ['mid' => 'mid.w1', 'text' => 'hello worker']]]]]];
app(WebhookIngest::class)->accept('gateway', 'facebook', 'gw:w1', $payload);
$check('accepting an event only queues it (nothing processed inline)', $jobsLeft() === 1 && ChatMessage::count() === 0);
$worker();
$check('a real worker turns the event into a message', ChatMessage::where('external_id', 'mid.w1')->exists() && $jobsLeft() === 0, $worker('--stop-when-empty'));
$check('the event is marked done', ChatWebhookEvent::first()->status === 'done');

// 2. duplicate delivery is a no-op end to end
$dup = app(WebhookIngest::class)->accept('gateway', 'facebook', 'gw:w1', $payload);
$check('duplicate delivery is rejected at ingest and queues nothing', $dup === false && $jobsLeft() === 0);

// 3. outbound send against an unreachable provider: retried with backoff, then failed, then retryable
$conv = ChatConversation::first(); $conv->update(['last_inbound_at' => now()]);
$m = ChatMessage::create(['chat_conversation_id' => $conv->id, 'chat_account_id' => $acc->id, 'direction' => 'out', 'body' => 'reply', 'status' => 'queued', 'client_ref' => 'wk-1', 'sent_by' => 1]);
SendChatMessage::dispatch($m->id);
$worker();
$m->refresh();
$job = DB::table('jobs')->first();
$check('network failure is retried later, not failed at once', $m->status === 'queued' && $m->retry_count === 1 && $job && $job->available_at > time() + 5, json_encode([$m->status, $m->retry_count, $job->available_at ?? null, $m->error]));
$check('message shows an understandable automatic-retry note', str_contains((string) $m->error, 'Retrying automatically'), (string) $m->error);
for ($i = 0; $i < 6 && $jobsLeft(); $i++) { DB::table('jobs')->update(['available_at' => time() - 1, 'reserved_at' => null]); $worker(); }
$m->refresh();
$check('after the last attempt the message is failed with a retry hint', $m->status === 'failed' && stripos((string) $m->error, 'retry') !== false, json_encode([$m->status, $m->error, $jobsLeft()]));

// 4. a failed message can be re-queued and a worker picks it up again
$m->forceFill(['status' => 'queued', 'error' => null])->save();
SendChatMessage::dispatch($m->id);
$worker();
$check('manual retry runs through the worker again', $m->fresh()->retry_count >= 1 && $m->fresh()->status === 'queued');
DB::table('jobs')->delete();

// 5. scheduled maintenance re-queues events a dead worker never finished
$e = ChatWebhookEvent::create(['source' => 'gateway', 'platform' => 'facebook', 'event_key' => 'gw:stuck', 'status' => 'pending', 'attempts' => 0,
    'payload' => json_encode(['object' => 'page', 'entry' => [['id' => 'WORKER_PAGE', 'time' => 1, 'messaging' => [['sender' => ['id' => 'U2'], 'recipient' => ['id' => 'WORKER_PAGE'], 'timestamp' => (int) (microtime(true) * 1000), 'message' => ['mid' => 'mid.stuck', 'text' => 'stuck event']]]]]])]);
DB::table('chat_webhook_events')->where('id', $e->id)->update(['updated_at' => now()->subMinutes(10)]);
exec('cd ' . escapeshellarg(dirname(__DIR__, 2)) . ' && env QUEUE_CONNECTION=database php artisan chat:maintain 2>&1', $o1);
$check('chat:maintain re-queues the stuck event', $jobsLeft() === 1);
$worker();
$check('and a worker then processes it', ChatMessage::where('external_id', 'mid.stuck')->exists());

// 6. scheduler wiring
exec('cd ' . escapeshellarg(dirname(__DIR__, 2)) . ' && php artisan schedule:list 2>&1', $sl);
$check('scheduler lists chat:maintain every 5 minutes and the per-minute queue worker', (bool) preg_grep('#\*/5 \* \* \* \*\s+php artisan chat:maintain#', $sl) && (bool) preg_grep('#queue:work --once#', $sl), implode("\n", $sl));

foreach (['chat_messages', 'chat_conversations', 'chat_accounts', 'chat_webhook_events', 'chat_audit_logs'] as $t) DB::table($t)->delete();
DB::table('jobs')->delete();
echo "\n$ok passed, $bad failed\n";
exit($bad ? 1 : 0);
