<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only unless --analyze. Checks what the stock queries need to take row locks narrowly (lookup indexes) and whether the optimizer's
 * statistics describe the tables as they are now. With stale statistics (or no index) the "lock one stock row / one cost layer" queries
 * can scan and lock many rows and deadlock under concurrent orders.
 */
class InventoryDbHealth extends Command
{
    protected $signature = 'inventory:db-health {--analyze : run ANALYZE TABLE on the stock tables (updates optimizer statistics only; no data is changed)}';
    protected $description = 'Stock-table indexes and optimizer statistics check; --analyze refreshes the statistics.';

    private const TABLES = ['product_stocks', 'purchase_products', 'stock_balances', 'order_demands'];

    public function handle()
    {
        $problems = 0;
        $has = fn (string $t, array $cols) => DB::table('information_schema.STATISTICS')->where('TABLE_SCHEMA', DB::raw('DATABASE()'))->where('TABLE_NAME', $t)->where('SEQ_IN_INDEX', 1)->where('COLUMN_NAME', $cols[0])
            ->whereIn('INDEX_NAME', DB::table('information_schema.STATISTICS')->where('TABLE_SCHEMA', DB::raw('DATABASE()'))->where('TABLE_NAME', $t)->where('SEQ_IN_INDEX', 2)->where('COLUMN_NAME', $cols[1])->pluck('INDEX_NAME'))->exists();
        foreach (['product_stocks' => ['product_id', 'variant'], 'purchase_products' => ['product_id', 'variant']] as $t => $cols) {
            if ($has($t, $cols)) {
                $this->line("[OK     ] {$t}: lookup index on (product_id, variant) exists");
            } else {
                $problems++;
                $this->line("[PROBLEM] {$t}: no index on (product_id, variant). Concurrent orders lock far more rows than they need and deadlock. Add it (sqlupdates/inventory_v1_stock_safety.sql or the inventory_v2 stock-lookup migration).");
            }
        }
        $stale = [];
        foreach (self::TABLES as $t) {
            $info = DB::selectOne('SELECT TABLE_ROWS AS est FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$t]);
            if (!$info) {
                continue;
            }
            $actual = (int) DB::table($t)->count();
            $est = (int) $info->est;
            $drift = $actual === 0 && $est === 0 ? 0 : abs($actual - $est) / max(1, $actual, $est);
            $isStale = $actual > 20 && $drift > 0.5 || ($actual > 0 && $est === 0);
            $this->line(sprintf('[%-7s] %s: optimizer thinks %d rows, there are %d%s', $isStale ? 'STALE' : 'OK', $t, $est, $actual, $isStale ? ' (statistics are stale)' : ''));
            if ($isStale) {
                $stale[] = $t;
            }
        }
        if ($this->option('analyze')) {
            DB::statement('ANALYZE TABLE ' . implode(', ', self::TABLES));
            $this->info('Statistics refreshed for: ' . implode(', ', self::TABLES) . '.');
        } elseif ($stale) {
            $this->line('Run: php artisan inventory:db-health --analyze   (or ANALYZE TABLE ' . implode(', ', $stale) . ')');
        }

        return $problems ? 1 : 0;
    }
}
