<?php

namespace Tests\Concurrency;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Base for tests that need real overlapping database connections.
 *
 * These tests COMMIT their data (workers are separate processes and cannot see
 * an open transaction), so they only run against a *_test database and remove
 * what they made in tearDown. Run them with:
 *
 *   vendor/bin/phpunit -c phpunit.concurrency.xml
 *
 * against misron_inventory_test (the schema after the stock-safety change) or
 * misron_test (the original MyISAM schema).
 */
abstract class ConcurrencyTestCase extends TestCase
{
    /** Tables the checkout writes to, and the highest id each had before the test. */
    private const TRACKED = ['products', 'product_stocks', 'purchase_products', 'purchases', 'orders', 'combined_orders', 'order_details', 'carts', 'users', 'blocked_ips', 'coupon_usages', 'drafts', 'cities', 'funnels', 'funnel_versions', 'funnel_audit_logs', 'funnel_checkout_attempts'];

    /** @var array<string, int> */
    private array $highWater = [];

    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::connection()->getDatabaseName();
        if (substr($database, -5) !== '_test') {
            $this->fail("Concurrency tests must run against a *_test database, not '{$database}'.");
        }

        foreach (self::TRACKED as $table) {
            $this->highWater[$table] = (int) DB::table($table)->max('id');
        }

        $this->workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'misron-conc-' . Str::random(8);
        mkdir($this->workDir);
        \App\Services\Inventory\StockLedger::forgetEngineCache();
    }

    protected function tearDown(): void
    {
        foreach (array_reverse(self::TRACKED) as $table) {
            DB::table($table)->where('id', '>', $this->highWater[$table] ?? PHP_INT_MAX)->delete();
        }
        foreach (glob($this->workDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->workDir);

        parent::tearDown();
    }

    // ---- data ---------------------------------------------------------------------

    /** A product with one stock row and one batch per variation. @return int product id */
    protected function seedProduct(array $variants = ['M' => 10], float $price = 1000, float $cost = 400, array $batchSizes = []): int
    {
        $id = DB::table('products')->insertGetId([
            'name' => 'Race ' . Str::random(5), 'added_by' => 'admin', 'user_id' => 1, 'category_id' => 1,
            'unit_price' => $price, 'published' => 1, 'approved' => 1, 'digital' => 0, 'variant_product' => 1,
            'slug' => 'race-' . Str::lower(Str::random(8)), 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($variants as $variant => $qty) {
            DB::table('product_stocks')->insert(['product_id' => $id, 'variant' => $variant, 'price' => $price, 'qty' => $qty, 'created_at' => now(), 'updated_at' => now()]);

            $sizes = $batchSizes[$variant] ?? [$qty];
            $purchaseId = DB::table('purchases')->insertGetId(['supplier_id' => 1, 'reference_no' => 'R-' . Str::random(8), 'purchase_date' => date('Y-m-d'), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($sizes as $i => $size) {
                DB::table('purchase_products')->insert([
                    'product_id' => $id, 'purchase_id' => $purchaseId, 'product_name' => 'Race', 'variant' => $variant,
                    'purchase_quantity' => (string) $size, 'remaining_qty' => $size, 'buying_unit_cost' => (string) ($cost + $i * 10),
                    'total_cost' => (string) ($size * ($cost + $i * 10)), 'product_tax' => '0', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        return $id;
    }

    /**
     * A published funnel with one offer and a checkout form, committed so that other
     * processes can see it. @return array{0: string, 1: int, 2: int} slug, product id, district id
     */
    protected function seedFunnel(array $stock = ['M' => 10]): array
    {
        $adminId = DB::table('users')->insertGetId([
            'name' => 'Race Admin', 'email' => Str::random(10) . '@race.test', 'password' => bcrypt('x'), 'user_type' => 'admin',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $admin = \App\Models\User::find($adminId);
        $product = $this->seedProduct($stock, 1000, 400);
        $cityId = DB::table('cities')->insertGetId(['name' => 'Race City', 'state_id' => 1, 'cost' => 60, 'status' => 1]);
        $slug = 'race-' . Str::lower(Str::random(8));

        $service = app(\App\Services\Funnel\FunnelService::class);
        $funnel = $service->create(['title' => 'Race', 'slug' => $slug], $admin);
        $page = ['schemaVersion' => 1, 'page' => ['sections' => [
            ['id' => 'offers_001', 'type' => 'product_offer_selector', 'props' => ['selectionMode' => 'multiple'], 'productBindings' => [[
                'key' => 'offer_one', 'items' => [['productId' => $product, 'variant' => array_key_first($stock), 'quantity' => 1, 'lockVariant' => true]],
                'offer' => ['type' => 'none'],
            ]]],
            ['id' => 'checkout_001', 'type' => 'checkout_form', 'props' => [], 'children' => [['id' => 'buy_001', 'type' => 'purchase_button', 'props' => ['text' => 'Order now']]]],
        ]]];
        [$funnel] = $service->saveDraft($funnel, $page, 1, $admin);
        $service->publish($funnel, $admin);

        return [$slug, $product, $cityId];
    }

    protected function checkoutJob(string $slug, int $cityId, array $override = [], array $customer = []): array
    {
        return ['action' => 'http', 'uri' => "/api/v2/funnels/{$slug}/checkout", 'body' => array_replace([
            'idempotency_key' => 'race-key-' . Str::random(20),
            'selections' => [['key' => 'offer_one', 'quantity' => 1]],
            'customer' => array_replace(['name' => 'Race Buyer', 'phone' => '01712345678', 'address' => 'Road 1, Dhaka', 'district_id' => $cityId], $customer),
            'payment_type' => 'cash_on_delivery',
        ], $override)];
    }

    /** Rows this test added to a tracked table. */
    protected function created(string $table): int
    {
        return DB::table($table)->where('id', '>', $this->highWater[$table])->count();
    }

    protected function stock(int $productId, string $variant): int
    {
        return (int) DB::table('product_stocks')->where('product_id', $productId)->where('variant', $variant)->value('qty');
    }

    protected function batchSum(int $productId, string $variant): int
    {
        return (int) DB::table('purchase_products')->where('product_id', $productId)->where('variant', $variant)->sum('remaining_qty');
    }

    // ---- processes ------------------------------------------------------------------

    /**
     * Starts one process per job, waits until every one is booted and connected,
     * releases them all at the same instant and collects their answers.
     *
     * @param  array<int, array<string, mixed>>  $jobs
     * @return array<int, array<string, mixed>>
     */
    protected function race(array $jobs, int $timeoutSeconds = 90): array
    {
        $go = $this->workDir . DIRECTORY_SEPARATOR . 'go';
        $running = [];

        foreach (array_values($jobs) as $i => $job) {
            $job += ['id' => $i, 'go' => $go];
            $job['ready'] = $this->workDir . DIRECTORY_SEPARATOR . "ready-{$i}";
            $job['out'] = $this->workDir . DIRECTORY_SEPARATOR . "out-{$i}.json";
            $jobFile = $this->workDir . DIRECTORY_SEPARATOR . "job-{$i}.json";
            file_put_contents($jobFile, json_encode($job));

            $env = array_merge(getenv(), ['DB_DATABASE' => DB::connection()->getDatabaseName(), 'APP_ENV' => 'testing', 'FUNNEL_CHECKOUT_ENABLED' => 'true']);
            $process = proc_open(
                [PHP_BINARY, __DIR__ . DIRECTORY_SEPARATOR . 'worker.php', $jobFile],
                [0 => ['pipe', 'r'], 1 => ['file', $this->workDir . DIRECTORY_SEPARATOR . "stdout-{$i}.txt", 'w'], 2 => ['file', $this->workDir . DIRECTORY_SEPARATOR . "stderr-{$i}.txt", 'w']],
                $pipes,
                null,
                $env
            );
            $this->assertIsResource($process, 'could not start a worker');
            fclose($pipes[0]);
            $running[$i] = ['process' => $process, 'job' => $job];
        }

        $deadline = microtime(true) + $timeoutSeconds;
        while (true) {
            $ready = count(array_filter($running, fn ($r) => file_exists($r['job']['ready']) || file_exists($r['job']['out'])));
            if ($ready === count($running)) {
                break;
            }
            if (microtime(true) > $deadline) {
                $this->fail('Workers did not become ready: ' . $this->diagnostics());
            }
            usleep(10000);
        }

        touch($go);

        $results = [];
        foreach ($running as $i => $r) {
            while (proc_get_status($r['process'])['running']) {
                if (microtime(true) > $deadline) {
                    proc_terminate($r['process']);
                    $this->fail("Worker {$i} did not finish: " . $this->diagnostics());
                }
                usleep(5000);
            }
            proc_close($r['process']);

            $out = $r['job']['out'];
            if (!empty($r['job']['may_die']) && !file_exists($out)) {
                $results[$i] = ['died' => true]; // a worker that is meant to be killed writes nothing
                continue;
            }
            $this->assertFileExists($out, "Worker {$i} wrote no result. " . $this->diagnostics());
            $results[$i] = json_decode(file_get_contents($out), true);
        }

        return $results;
    }

    private function diagnostics(): string
    {
        $text = '';
        foreach (glob($this->workDir . DIRECTORY_SEPARATOR . 'stderr-*.txt') ?: [] as $file) {
            $content = trim((string) file_get_contents($file));
            $text .= $content === '' ? '' : basename($file) . ': ' . substr($content, 0, 400) . ' ';
        }

        return $text ?: '(no worker output)';
    }

    /** Convenience: results grouped by outcome. */
    protected function tally(array $results): array
    {
        $ok = count(array_filter($results, fn ($r) => !empty($r['ok'])));
        $codes = [];
        foreach ($results as $r) {
            if (empty($r['ok'])) {
                $codes[] = $r['code'] ?? ($r['error'][0] ?? '?');
            }
        }

        return ['ok' => $ok, 'refused' => count($results) - $ok, 'codes' => array_count_values($codes)];
    }

    protected function takeJob(array $lines, array $extra = []): array
    {
        return array_merge(['action' => 'take', 'lines' => $lines], $extra);
    }

    protected function line(int $productId, string $variant, int $quantity, $key = null): array
    {
        return ['key' => $key ?? "{$productId}|{$variant}", 'product_id' => $productId, 'variant' => $variant, 'quantity' => $quantity];
    }
}
