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
