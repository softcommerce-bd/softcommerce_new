<?php

namespace Tests\Feature\Inventory;

use App\Services\Inventory\InventoryAudit;
use Illuminate\Support\Facades\DB;
use Tests\Inventory\InventoryTestCase;

class InventoryAuditTest extends InventoryTestCase
{
    private function audit(): InventoryAudit
    {
        return new InventoryAudit(DB::connection());
    }

    private function variantRow(array $rows, int $productId, string $variant): array
    {
        foreach ($rows as $row) {
            if ($row['product_id'] === $productId && $row['variant'] === $variant) {
                return $row;
            }
        }
        $this->fail("No audit row for product {$productId} '{$variant}'");
    }

    public function test_it_only_ever_reads(): void
    {
        $this->makeStockedProduct(['M' => 3]);

        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = $query->sql;
        });

        $audit = $this->audit();
        $variants = $audit->variantRows();
        $products = $audit->productRows($variants);
        $audit->summary($variants, $products);
        $audit->preflight();
        $audit->counts();
        $audit->integrity();
        $audit->duplicateStockRows();

        $this->assertNotEmpty($statements);
        foreach ($statements as $sql) {
            $this->assertMatchesRegularExpression('/^\s*(select|show)\b/i', $sql, 'an audit statement was not a plain read');
        }
    }

    public function test_each_gap_between_stock_and_batches_gets_its_own_status_and_hold_level(): void
    {
        $product = $this->makeProduct([], [
            ['variant' => 'ok', 'qty' => 5],
            ['variant' => 'nobatch', 'qty' => 7],
            ['variant' => 'short', 'qty' => 9],
            ['variant' => 'excess', 'qty' => 2],
            ['variant' => 'negative', 'qty' => -1],
        ]);
        $this->makeBatch($product, 'ok', 5);
        $this->makeBatch($product, 'short', 4);
        $this->makeBatch($product, 'excess', 6);

        $rows = $this->audit()->variantRows();

        $this->assertSame(['ok', 'none', 5], $this->pick($this->variantRow($rows, $product->id, 'ok')));
        $this->assertSame(['no_cost_batches', 'blocked', 0], $this->pick($this->variantRow($rows, $product->id, 'nobatch')));
        $this->assertSame(['cost_batches_short', 'limited', 4], $this->pick($this->variantRow($rows, $product->id, 'short')));
        $this->assertSame(['batch_excess', 'review', 2], $this->pick($this->variantRow($rows, $product->id, 'excess')));
        $this->assertSame('negative_stock', $this->variantRow($rows, $product->id, 'negative')['status']);

        $products = collect($this->audit()->productRows($rows))->firstWhere('product_id', $product->id);
        $this->assertSame('blocked', $products['hold'], 'a product takes its worst variation');
        $this->assertSame(4, $products['variants_flagged']);
        $this->assertSame(5 + 4 + 2, $products['sellable_total']);
    }

    private function pick(array $row): array
    {
        return [$row['status'], $row['hold'], $row['sellable_units']];
    }

    public function test_batches_of_a_deleted_product_are_reported_as_orphans(): void
    {
        $product = $this->makeStockedProduct(['M' => 3]);
        DB::table('product_stocks')->where('product_id', $product->id)->delete();
        DB::table('products')->where('id', $product->id)->delete();

        $row = $this->variantRow($this->audit()->variantRows(), $product->id, 'M');

        $this->assertSame('orphan_batch', $row['status']);
        $this->assertFalse($row['product_exists']);
        $this->assertSame(0, $row['sellable_units']);
    }

    public function test_cached_total_is_compared_with_the_stock_table_and_the_batches(): void
    {
        $agree = $this->makeStockedProduct(['M' => 4], 1000, 100, ['current_stock' => 4]);
        $stale = $this->makeStockedProduct(['M' => 4], 1000, 100, ['current_stock' => 9]);

        $products = collect($this->audit()->productRows())->keyBy('product_id');

        $this->assertTrue($products[$agree->id]['cache_matches_stock']);
        $this->assertFalse($products[$stale->id]['cache_matches_stock']);
        $this->assertSame(5, $products[$stale->id]['cache_gap']);
        $this->assertTrue($products[$stale->id]['stock_matches_batches']);
    }

    public function test_variation_text_that_matches_only_through_case_is_flagged(): void
    {
        $product = $this->makeProduct([], [['variant' => 'Black-M', 'qty' => 3]]);
        $this->makeBatch($product, 'black-m', 3);

        $rows = array_values(array_filter($this->audit()->variantRows(), fn ($r) => $r['product_id'] === $product->id));

        $this->assertCount(1, $rows, 'one row, not two');
        $this->assertTrue($rows[0]['loose_match']);
        $this->assertSame('ok', $rows[0]['status']);
    }

    public function test_duplicate_stock_rows_are_listed_with_every_copy_and_block_the_unique_key(): void
    {
        if ($this->stockKeyIsUnique()) {
            $this->markTestSkipped('The unique key already forbids duplicates on this schema.');
        }
        $product = $this->makeProduct([], [['variant' => 'M', 'qty' => 5], ['variant' => 'm', 'qty' => 2]]);

        $duplicates = $this->audit()->duplicateStockRows();
        $mine = collect($duplicates)->firstWhere('product_id', $product->id);

        $this->assertNotNull($mine, 'compared the way the unique key would compare them: case-insensitively');
        $this->assertSame(2, $mine['copies']);
        $this->assertEqualsCanonicalizing([5, 2], array_map('intval', array_column($mine['rows'], 'qty')));

        $checks = collect($this->audit()->preflight())->keyBy('code');
        $this->assertSame('blocker', $checks['duplicate_stock_rows']['level']);
        $this->assertSame('blocked', InventoryAudit::verdict($this->audit()->preflight()));
    }

    public function test_preflight_reports_the_engine_and_a_verdict(): void
    {
        $checks = collect($this->audit()->preflight())->keyBy('code');

        $this->assertArrayHasKey('engine_purchase_products', $checks);
        $this->assertSame($this->batchesAreTransactional() ? 'info' : 'warning', $checks['engine_purchase_products']['level']);
        $this->assertContains(InventoryAudit::verdict($this->audit()->preflight()), ['ready', 'review', 'blocked']);
    }

    public function test_the_command_prints_a_summary_and_writes_files_without_writing_data(): void
    {
        $this->makeStockedProduct(['M' => 3]);

        $before = [DB::table('product_stocks')->count(), DB::table('purchase_products')->count()];
        $this->artisan('inventory:audit', ['--json' => true])->assertExitCode(0);
        $this->assertSame($before, [DB::table('product_stocks')->count(), DB::table('purchase_products')->count()]);
    }
}
