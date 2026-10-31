<?php

/*
|--------------------------------------------------------------------------
| Concurrency test worker
|--------------------------------------------------------------------------
|
| One process per simulated shopper. The test (ConcurrencyTestCase) starts
| several of these, each booting the application and opening its own database
| connection, then releases them together by creating a "go" file, so their
| statements really do overlap.
|
|   php tests/Concurrency/worker.php <job.json>
|
| A job says what to do and where to write the answer. Actions:
|   take         take stock through StockLedger inside a transaction
|   give         cancel one order line through StockLedger
|   http         send a request through the application's HTTP kernel
|   legacy_take  the ORIGINAL read-then-write logic, kept only to show the oversell
|   rmw_add      the read-modify-write stock change other writers still use (conflict demo)
|   adjust       StockLedger::adjust(), the atomic replacement for it
|   receipt      PurchaseController::updateProductStockVariant(), as purchases now change the shelf
|   resize       StockLedger::resizeBatch(), as a purchase edit now changes a batch
|   cancel_line  set_order_line_status(line, "cancelled"), as every admin cancel screen now does
|   edit_shelf   OrderController::updateStock(), as the order edit screen now changes the shelf
|
| Refuses to run against a database whose name does not end in _test.
|
*/

require __DIR__ . '/../../vendor/autoload.php';

use App\Models\OrderDetail;
use App\Services\Inventory\StockLedger;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$job = json_decode(file_get_contents($argv[1]), true);

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$database = DB::connection()->getDatabaseName();
if (substr($database, -5) !== '_test') {
    file_put_contents($job['out'], json_encode(['error' => ['refused', "Not a test database: {$database}"]]));
    exit(1);
}
DB::connection()->getPdo(); // connect now, not after the barrier

touch($job['ready']);
$deadline = microtime(true) + 60;
while (!file_exists($job['go'])) {
    if (microtime(true) > $deadline) {
        file_put_contents($job['out'], json_encode(['error' => ['timeout', 'barrier never released']]));
        exit(1);
    }
    usleep(500);
}

$result = ['worker' => $job['id'], 'started' => microtime(true)];
if (!empty($job['lock_wait'])) { // shorten this process's lock wait to prove what happens to a request that cannot get its locks
    DB::statement('SET SESSION innodb_lock_wait_timeout = ' . (int) $job['lock_wait']);
}
if (!empty($job['delay_ms'])) { // let another process get going first
    usleep($job['delay_ms'] * 1000);
}

try {
    $ledger = app(StockLedger::class);

    switch ($job['action']) {
        case 'take':
            DB::beginTransaction();
            $take = null;
            try {
                $take = $ledger->take($job['lines']);
                if (!empty($job['hold_ms'])) {
                    usleep($job['hold_ms'] * 1000); // keep the row locks while "doing the rest of the order"
                }
                if (!empty($job['fail_after'])) {
                    throw new RuntimeException('simulated failure after the stock was taken');
                }
                DB::commit();
                $result['ok'] = true;
                $result['cost'] = $take->totalCost();
                $result['lines'] = array_map(fn ($key) => $take->line($key), array_keys(array_column($job['lines'], null, 'key')));
            } catch (Throwable $e) {
                DB::rollBack();
                if ($take) {
                    $ledger->release($take);
                }
                throw $e;
            }
            break;

        case 'give':
            $result['gave'] = (bool) $ledger->give(OrderDetail::findOrFail($job['order_detail_id']));
            break;

        case 'http':
            $request = Request::create($job['uri'], $job['method'] ?? 'POST', [], [], [], [
                'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            ] + ($job['server'] ?? []), json_encode($job['body'] ?? []));
            $response = $app->make(HttpKernel::class)->handle($request);
            $result['status'] = $response->getStatusCode();
            $result['body'] = json_decode($response->getContent(), true);
            break;

        case 'legacy_take':
            // The storefront's old logic, verbatim in shape: read the quantity, check it, then
            // write back the absolute value computed from that read. The pause stands for the
            // rest of the request (price lookups, FIFO) that happens between those two steps.
            $stock = DB::table('product_stocks')->where('product_id', $job['product_id'])->where('variant', $job['variant'])->first();
            if ($job['quantity'] > $stock->qty) {
                $result['ok'] = false;
                $result['error'] = ['OutOfStock', 'old check refused'];
                break;
            }
            usleep(($job['pause_ms'] ?? 150) * 1000);
            DB::table('product_stocks')->where('id', $stock->id)->update(['qty' => $stock->qty - $job['quantity']]);
            $result['ok'] = true;
            break;

        case 'rmw_add':
            // How PurchaseController::updateProductStockVariant, the POS and the product form
            // change stock today: load the row, add in PHP, save the absolute number.
            $stock = App\Models\ProductStock::where('product_id', $job['product_id'])->where('variant', $job['variant'])->first();
            usleep(($job['pause_ms'] ?? 200) * 1000);
            $stock->qty += $job['delta'];
            $stock->save();
            $result['ok'] = true;
            break;

        case 'adjust':
            $result['ok'] = $ledger->adjust($job['product_id'], $job['variant'], $job['delta']);
            break;

        case 'receipt':
            $controller = new App\Http\Controllers\PurchaseController();
            $method = new ReflectionMethod($controller, 'updateProductStockVariant');
            $method->setAccessible(true);
            DB::beginTransaction();
            try {
                $method->invoke($controller, $job['product_id'], $job['variant'], $job['delta']);
                if (!empty($job['hold_ms'])) {
                    usleep($job['hold_ms'] * 1000);
                }
                DB::commit();
                $result['ok'] = true;
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
            break;

        case 'resize':
            DB::beginTransaction();
            try {
                $result['ok'] = $ledger->resizeBatch($job['batch_id'], $job['delta']);
                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                throw $e;
            }
            break;

        case 'cancel_line':
            set_order_line_status(OrderDetail::findOrFail($job['order_detail_id']), 'cancelled');
            $result['ok'] = true;
            break;

        case 'edit_shelf':
            $controller = new App\Http\Controllers\OrderController();
            $method = new ReflectionMethod($controller, 'updateStock');
            $method->setAccessible(true);
            $method->invoke($controller, $job['product_id'], $job['quantity'], $job['variant']);
            $result['ok'] = true;
            break;

        // ---- inventory v2 / purchasing (run with INVENTORY_CONFIRMED_ONLY=true) ----
        case 'v2_status':
            $order = App\Models\Order::findOrFail($job['order_id']);
            $order->delivery_status = $job['status'];
            $order->save();
            $result['ok'] = true;
            break;

        case 'v2_receive':
            $result['receipt'] = app(App\Services\Purchasing\GoodsReceiptService::class)->receive($job['key'], $job['data']);
            $result['ok'] = true;
            break;

        case 'v2_pay':
            $result['payment'] = app(App\Services\Purchasing\SupplierLedgerService::class)->pay($job['key'], $job['invoice_id'], $job['account_id'], $job['amount']);
            $result['ok'] = true;
            break;

        case 'v2_journal':
            $result['entry'] = app(App\Services\Accounting\JournalService::class)->post($job['key'], '2026-01-01', $job['lines']);
            $result['ok'] = true;
            break;

        case 'lock_pair': // two transactions that take the same two rows in opposite order: a deterministic deadlock
            $attempts = 0;
            $body = function () use ($job, &$attempts) {
                $attempts++;
                DB::table('chart_of_accounts')->where('id', $job['first'])->lockForUpdate()->first();
                usleep(($job['hold_ms'] ?? 300) * 1000);
                DB::table('chart_of_accounts')->where('id', $job['second'])->lockForUpdate()->first();
                DB::table('chart_of_accounts')->where('id', $job['first'])->increment('opening_balance', 0.01);

                return ['done' => true];
            };
            if (!empty($job['retry'])) {
                App\Services\Inventory\Idempotency::once('test_pair', $job['key'], $body); // the service wrapper, which retries a deadlock
            } else {
                DB::transaction($body); // a plain transaction: no retry
            }
            $result['attempts'] = $attempts;
            $result['ok'] = true;
            break;

        case 'http_as': // a real request through the HTTP kernel as a given staff user (form post), e.g. an admin screen
            Illuminate\Support\Facades\Auth::loginUsingId($job['user_id']);
            if (!empty($job['pause_after'])) { // widen a race window on purpose: sleep once, right after the first statement matching the pattern
                $paused = false;
                DB::listen(function ($q) use ($job, &$paused) {
                    if (!$paused && preg_match($job['pause_after']['regex'], $q->sql)) {
                        $paused = true;
                        usleep($job['pause_after']['ms'] * 1000);
                    }
                });
            }
            $request = Request::create($job['uri'], $job['method'] ?? 'POST', $job['body'] ?? [], [], [], ['HTTP_ACCEPT' => 'application/json']);
            $response = $app->make(HttpKernel::class)->handle($request);
            $result['headers'] = array_filter(['X-Deadlock-Retries' => $response->headers->get('X-Deadlock-Retries')]);
            $result['status'] = $response->getStatusCode();
            $result['body'] = json_decode($response->getContent(), true) ?? substr((string) $response->getContent(), 0, 300);
            if ($request->hasSession()) { // flash+redirect screens report their failure through the session
                $result['flash_error'] = substr((string) json_encode($request->session()->get('error')), 0, 300);
            }
            $result['ok'] = true;
            break;

        case 'lock_then_lock': // holds one row, waits, then wants another: the other half of a deadlock with a request that takes them in the opposite order
            $attempt = function () use ($job) {
                DB::table($job['first']['table'])->where('id', $job['first']['id'])->lockForUpdate()->first();
                foreach ($job['touch'] ?? [] as $id) { // modified rows make this transaction the heavier one, so the database picks the OTHER side as the deadlock victim
                    DB::table('chart_of_accounts')->where('id', $id)->update(['description' => 'touched ' . uniqid()]);
                }
                usleep($job['hold_ms'] * 1000);
                DB::table($job['second']['table'])->where('id', $job['second']['id'])->lockForUpdate()->first();
            };
            DB::transaction($attempt);
            $result['ok'] = true;
            break;

        case 'delete_carts': // another tab emptying the cart while a checkout is in flight
            $result['deleted'] = DB::table('carts')->where('user_id', $job['user_id'])->delete();
            $result['ok'] = true;
            break;

        case 'pos_order': // the POS sale, as PosController::order_store calls it, in its own process
            Illuminate\Support\Facades\Auth::loginUsingId($job['user_id']);
            $result['pos'] = App\Utility\PosUtility::orderStore($job['data']);
            $result['ok'] = true;
            break;

        case 'console': // an Artisan command in its own process (exit code and output captured)
            $result['exit'] = Illuminate\Support\Facades\Artisan::call($job['command'], $job['args'] ?? []);
            $result['output'] = substr(Illuminate\Support\Facades\Artisan::output(), 0, 400);
            $result['ok'] = true;
            break;

        case 'chat_place':
            $conv = App\Models\ChatConversation::findOrFail($job['conversation_id']);
            $staff = App\Models\User::findOrFail($job['user_id']);
            $combined = app(App\Services\Chat\ChatOrderService::class)->place($conv, $staff, $job['input'], '127.0.0.1');
            $result['combined_order_id'] = $combined->id;
            $result['ok'] = true;
            break;

        case 'outbox_process':
            $result['outbox'] = app(App\Services\Accounting\LedgerOutboxProcessor::class)->processPending($job['limit'] ?? 200);
            $result['ok'] = true;
            break;

        case 'outbox_kill_at': // dies (SIGKILL: no cleanup, no rollback code) at the named boundary of processing
            App\Services\Accounting\LedgerOutboxProcessor::$boundaryHook = function ($name) use ($job) {
                if ($name === $job['at']) {
                    function_exists('posix_kill') ? posix_kill(getmypid(), 9) : exit(137);
                }
            };
            $result['outbox'] = app(App\Services\Accounting\LedgerOutboxProcessor::class)->processPending($job['limit'] ?? 200);
            $result['ok'] = true;
            break;

        case 'refund_service':
            $result['refund'] = app(App\Services\Accounting\RefundService::class)->record($job['key'], $job['method'], $job['amount'], $job['order_id'] ?? null, $job['order_detail_id'] ?? null, $job['refund_request_id'] ?? null, $job['account_id'] ?? null, $job['external_ref'] ?? null);
            $result['ok'] = true;
            break;

        case 'refund_pay_as': // the admin screen's controller method, as a given administrator
            Illuminate\Support\Facades\Auth::loginUsingId($job['admin_id']);
            $req = Illuminate\Http\Request::create('/admin/refund-request-pay', 'POST', ['refund_id' => $job['refund_id']]);
            $req->setLaravelSession(app('session')->driver());
            app()->instance('request', $req);
            app(App\Http\Controllers\RefundRequestController::class)->refund_pay($req);
            $result['ok'] = true;
            break;

        case 'legacy_post': // what an old screen does with the flag on: change two balances in one transaction
            DB::transaction(function () use ($job) {
                App\Models\ChartOfAccount::where('id', $job['from'])->decrement('current_balance', $job['amount']);
                App\Models\ChartOfAccount::where('id', $job['to'])->increment('current_balance', $job['amount']);
            });
            $result['ok'] = true;
            break;

        default:
            throw new InvalidArgumentException('Unknown action ' . $job['action']);
    }
} catch (Throwable $e) {
    $result['ok'] = false;
    $result['error'] = [get_class($e), $e->getMessage()];
    $result['code'] = method_exists($e, 'errorCode') ? $e->errorCode() : null;
}

$result['finished'] = microtime(true);
file_put_contents($job['out'], json_encode($result));
