<?php

namespace App\Console\Commands;

use App\Services\Inventory\InventoryAudit as Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only report on the stock tables. It opens its own connection in
 * READ ONLY mode, so the database refuses any write even if one were added by mistake.
 *
 *   php artisan inventory:audit                         # summary of the configured database
 *   php artisan inventory:audit --database=misron_inventory_test --out
 *   php artisan inventory:audit --preflight             # go/no-go for the stock-table changes
 */
class InventoryAuditCommand extends Command
{
    protected $signature = 'inventory:audit
        {--database= : Database to inspect (default: the configured one)}
        {--preflight : Print the migration go/no-go checks}
        {--hold : Print products that should not be sold until verified}
        {--out : Write JSON and CSV files to storage/app/inventory-audit}
        {--json : Print the summary as JSON}';

    protected $description = 'Read-only report of stock, purchase batches and cached totals. Changes nothing.';

    public function handle(): int
    {
        $database = $this->option('database') ?: config('database.connections.' . config('database.default') . '.database');

        config(['database.connections.inventory_audit' => array_merge(config('database.connections.' . config('database.default')), ['database' => $database])]);
        DB::purge('inventory_audit');
        $connection = DB::connection('inventory_audit');
        // Refused by the server, not just by this code.
        $connection->getPdo()->exec('SET SESSION TRANSACTION READ ONLY');

        $audit = new Audit($connection);
        $schema = $audit->schema();
        $variants = $audit->variantRows();
        $products = $audit->productRows($variants);
        $summary = $audit->summary($variants, $products);
        $checks = $audit->preflight($schema);

        if ($this->option('out')) {
            $dir = $this->write($database, $audit, $schema, $variants, $products, $summary, $checks);
            $this->info("Files written to {$dir}");
        }

        if ($this->option('json')) {
            $this->line(json_encode(['database' => $database, 'counts' => $audit->counts(), 'summary' => $summary, 'verdict' => Audit::verdict($checks)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->line("Database: {$database} ({$schema['server']['version']}), read-only session");
        $this->table(['What', 'Count'], collect($audit->counts())->map(fn ($v, $k) => [$k, $v])->values()->all());

        $this->line('');
        $this->line('Stock tables by status (per product and variation):');
        $this->table(['Status', 'Rows'], collect($summary['variants_by_status'])->map(fn ($v, $k) => [$k, $v])->values()->all());
        $this->line('Products by hold level: ' . json_encode($summary['products_by_hold']));
        $this->line("Products where the cached total differs from the stock table: {$summary['products_cache_differs_from_stock']} of {$summary['products_total']}");
        $this->line("Products where stock differs from the purchase batches: {$summary['products_stock_differs_from_batches']}");
        $this->line("Units with a cost batch: {$summary['units_with_cost_batch']}; without: {$summary['units_without_cost_batch']}; in orphan batches: {$summary['units_in_orphan_batches']}");

        if ($this->option('preflight')) {
            $this->line('');
            $this->line('Migration readiness: ' . strtoupper(Audit::verdict($checks)));
            foreach ($checks as $check) {
                $this->line(sprintf('  [%s] %s — %s', strtoupper($check['level']), $check['title'], $check['detail']));
            }
        }

        if ($this->option('hold')) {
            $this->line('');
            $rows = collect($products)->filter(fn ($p) => $p['hold'] !== Audit::HOLD_NONE && $p['digital'] !== 1)->map(fn ($p) => [$p['product_id'], mb_substr((string) $p['name'], 0, 36), $p['hold'], $p['stock_total'], $p['fifo_total'], $p['sellable_total']])->values()->all();
            $this->table(['Product', 'Name', 'Hold', 'Stock', 'Batches', 'Sellable'], $rows);
        }

        return self::SUCCESS;
    }

    private function write(string $database, Audit $audit, array $schema, array $variants, array $products, array $summary, array $checks): string
    {
        $dir = storage_path('app/inventory-audit/' . preg_replace('/[^A-Za-z0-9_-]/', '_', $database) . '-' . date('Ymd-His'));
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create {$dir}");
        }

        $json = fn (string $name, $data) => file_put_contents("{$dir}/{$name}.json", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $json('summary', ['database' => $database, 'counts' => $audit->counts(), 'summary' => $summary, 'verdict' => Audit::verdict($checks)]);
        $json('schema', $schema);
        $json('preflight', $checks);
        $json('integrity', $audit->integrity());
        $json('duplicate_stock_rows', $audit->duplicateStockRows());
        $json('variant_shapes', $audit->variantShapes());

        $this->csv("{$dir}/variants.csv", $variants, ['product_id', 'variant', 'status', 'hold', 'stock_qty', 'fifo_remaining', 'fifo_bought', 'sellable_units', 'gap', 'ordered_live', 'ordered_cancelled', 'batches', 'stock_rows', 'loose_match', 'product_exists']);
        $this->csv("{$dir}/products.csv", $products, ['product_id', 'name', 'published', 'hold', 'current_stock', 'stock_total', 'fifo_total', 'sellable_total', 'cache_gap', 'variants', 'variants_flagged', 'ordered_live', 'ordered_cancelled']);
        $this->csv("{$dir}/hold_list.csv", array_values(array_filter($products, fn ($p) => $p['hold'] !== Audit::HOLD_NONE && $p['digital'] !== 1)), ['product_id', 'name', 'published', 'hold', 'stock_total', 'fifo_total', 'sellable_total', 'variants_flagged']);

        return $dir;
    }

    private function csv(string $path, array $rows, array $columns): void
    {
        $handle = fopen($path, 'w');
        fputcsv($handle, $columns);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(function ($column) use ($row) {
                $value = $row[$column] ?? '';
                if (is_bool($value)) {
                    return $value ? 1 : 0;
                }
                // A spreadsheet would run a cell starting with = + - @ as a formula.
                return is_string($value) && $value !== '' && strpbrk($value[0], '=+-@') !== false ? "'" . $value : $value;
            }, $columns));
        }
        fclose($handle);
    }
}
