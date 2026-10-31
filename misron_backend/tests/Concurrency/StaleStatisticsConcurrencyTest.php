<?php

namespace Tests\Concurrency;

use Illuminate\Support\Facades\DB;

/**
 * Deadlocks under concurrent stock transactions depend on (a) whether the lookup indexes exist and (b) the optimizer statistics.
 * This test builds each condition on purpose, runs the same 24-shopper storm, and records what happens, then proves the
 * documented recovery procedure (indexes present + `inventory:db-health --analyze`) leaves zero deadlocks and consistent stock.
 */
class StaleStatisticsConcurrencyTest extends ConcurrencyTestCase
{
    private const STOCK_UQ = 'uq_product_stocks_product_variant';
    private const BATCH_IDX = 'idx_purchase_products_product_variant';

    private function indexExists(string $table, string $name): bool
    {
        return DB::table('information_schema.STATISTICS')->where('TABLE_SCHEMA', DB::raw('DATABASE()'))->where('TABLE_NAME', $table)->where('INDEX_NAME', $name)->exists();
    }

    private function setIndexes(bool $present): void
    {
        if ($present) {
            if (!$this->indexExists('product_stocks', self::STOCK_UQ)) {
                DB::statement('ALTER TABLE product_stocks ADD UNIQUE KEY ' . self::STOCK_UQ . ' (product_id, variant)');
            }
            if (!$this->indexExists('purchase_products', self::BATCH_IDX)) {
                DB::statement('ALTER TABLE purchase_products ADD KEY ' . self::BATCH_IDX . ' (product_id, variant(100))');
            }
        } else {
            if ($this->indexExists('product_stocks', self::STOCK_UQ)) {
                DB::statement('ALTER TABLE product_stocks DROP INDEX ' . self::STOCK_UQ);
            }
            if ($this->indexExists('purchase_products', self::BATCH_IDX)) {
                DB::statement('ALTER TABLE purchase_products DROP INDEX ' . self::BATCH_IDX);
            }
        }
    }

    /** stale = statistics frozen at "almost empty" (persistent, no auto recalculation), exactly what a freshly emptied/restored table looks like */
    private function setStatistics(bool $stale): void
    {
        foreach (['product_stocks', 'purchase_products'] as $t) {
            DB::statement("ALTER TABLE {$t} STATS_PERSISTENT=1, STATS_AUTO_RECALC=" . ($stale ? '0' : '1'));
        }
        if ($stale) {
            DB::statement('ANALYZE TABLE product_stocks, purchase_products'); // taken now, while the tables are nearly empty
        }
    }

    /** production-sized neighbours: thousands of other products' stock rows and cost layers that a table scan would have to lock too */
    private function addFiller(int $n): void
    {
        $now = now()->toDateTimeString();
        for ($i = 0; $i < $n; $i += 500) {
            $stock = $layers = [];
            for ($j = $i; $j < min($n, $i + 500); $j++) {
                $pid = 900000 + $j;
                $v = $j % 2 ? 'M' : 'L';
                $stock[] = ['product_id' => $pid, 'variant' => $v, 'sku' => 'FILL', 'price' => 1, 'qty' => 5, 'created_at' => $now, 'updated_at' => $now];
                $layers[] = ['purchase_id' => 0, 'product_id' => $pid, 'product_name' => 'filler', 'variant' => $v, 'purchase_quantity' => '5', 'remaining_qty' => 5, 'buying_unit_cost' => '10', 'total_cost' => '50', 'product_tax' => '0', 'created_at' => $now, 'updated_at' => $now];
            }
            DB::table('product_stocks')->insert($stock);
            DB::table('purchase_products')->insert($layers);
        }
    }

    private function removeFiller(): void
    {
        DB::table('product_stocks')->where('sku', 'FILL')->where('product_id', '>=', 900000)->delete();
        DB::table('purchase_products')->where('product_name', 'filler')->where('product_id', '>=', 900000)->delete();
    }

    /** @return array{deadlocks:int, refusals:int, ok:int, other:array} */
    private function storm(int $seed): array
    {
        mt_srand($seed);
        $products = [];
        foreach (range(1, 4) as $n) {
            $products[] = $this->seedProduct(['M' => 6, 'L' => 6], 1000, 300, ['M' => [2, 4], 'L' => [6]]);
        }
        $before = (int) DB::table('product_stocks')->whereIn('product_id', $products)->sum('qty');
        $jobs = $asked = [];
        foreach (range(1, 24) as $n) {
            $lines = [];
            foreach ((array) array_rand(array_flip($products), mt_rand(1, 3)) as $productId) {
                $lines[] = $this->line((int) $productId, mt_rand(0, 1) ? 'M' : 'L', mt_rand(1, 2));
            }
            shuffle($lines);
            $asked[] = $lines;
            $jobs[] = $this->takeJob($lines, ['hold_ms' => mt_rand(0, 25)]);
        }
        $results = $this->race($jobs, 120);
        $out = ['deadlocks' => 0, 'refusals' => 0, 'ok' => 0, 'other' => []];
        $units = 0;
        foreach ($results as $i => $r) {
            if (!empty($r['ok'])) {
                $out['ok']++;
                $units += array_sum(array_column($asked[$i], 'quantity'));
            } elseif (($r['code'] ?? null) === 'out_of_stock') {
                $out['refusals']++;
            } elseif (str_contains(json_encode($r['error'] ?? ''), 'Deadlock') || str_contains(json_encode($r['error'] ?? ''), '1213')) {
                $out['deadlocks']++;
            } else {
                $out['other'][] = $r;
            }
        }
        // whatever happened, numbers must stay consistent: a deadlocked transaction rolled back completely
        $after = (int) DB::table('product_stocks')->whereIn('product_id', $products)->sum('qty');
        $batches = (int) DB::table('purchase_products')->whereIn('product_id', $products)->sum('remaining_qty');
        $this->assertSame($before - $units, $after, 'stock fell by exactly the units of the transactions that succeeded');
        $this->assertSame($before - $units, $batches, 'batches fell by exactly the same units');
        $this->assertSame(0, DB::table('product_stocks')->whereIn('product_id', $products)->where('qty', '<', 0)->count());

        return $out;
    }

    public function test_the_recovery_procedure_leaves_no_deadlocks_and_a_failed_transaction_never_corrupts_stock()
    {
        $log = [];
        try {
            $this->addFiller(3000);
            // BEFORE: the production-like worst case (no lookup indexes, frozen statistics)
            $this->setIndexes(false);
            $this->setStatistics(true);
            foreach ([1, 2, 3] as $n) {
                $log['no_indexes_stale_stats'][] = $this->storm(20261100 + $n);
            }
            // indexes present but statistics still stale
            $this->setIndexes(true);
            $this->setStatistics(true);
            foreach ([1, 2, 3] as $n) {
                $log['indexes_stale_stats'][] = $this->storm(20261110 + $n);
            }
            // RECOVERY: the documented procedure
            $this->artisan('inventory:db-health', ['--analyze' => true])->assertExitCode(0);
            $this->setStatistics(false);
            $after = [];
            foreach ([1, 2, 3, 4, 5] as $n) {
                $after[] = $this->storm(20261120 + $n);
            }
            $log['after_recovery'] = $after;
        } finally {
            $this->removeFiller();
            $this->setIndexes(true);
            $this->setStatistics(false);
            fwrite(STDERR, "\nSTALE-STATISTICS EXPERIMENT " . json_encode(array_map(fn ($runs) => array_map(fn ($r) => ['deadlocks' => $r['deadlocks'], 'ok' => $r['ok'], 'refused' => $r['refusals'], 'other' => count($r['other'])], $runs), $log)) . "\n");
        }
        foreach ($log['after_recovery'] as $run) {
            $this->assertSame(0, $run['deadlocks'], 'no deadlocks once the indexes exist and the statistics are current');
            $this->assertSame([], $run['other'], 'only stock refusals are acceptable: ' . json_encode($run['other']));
        }
    }
}
