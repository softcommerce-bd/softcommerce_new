<?php

namespace Tests\Feature\Inventory;

use App\Services\Inventory\InventoryMode;
use Illuminate\Support\Facades\DB;

/** The confirmed-order model refuses to run, and says why, when the stock lookup indexes are missing. */
class StockLookupIndexTest extends InventoryV2TestCase
{
    public function test_preflight_refuses_the_flag_without_the_lookup_index_and_db_health_names_the_problem()
    {
        $this->assertSame([], InventoryMode::preflight(), 'indexes present: ready');
        $hadUnique = DB::table('information_schema.STATISTICS')->where('TABLE_SCHEMA', DB::raw('DATABASE()'))->where('TABLE_NAME', 'product_stocks')->where('INDEX_NAME', 'uq_product_stocks_product_variant')->exists();
        $hadBatch = DB::table('information_schema.STATISTICS')->where('TABLE_SCHEMA', DB::raw('DATABASE()'))->where('TABLE_NAME', 'purchase_products')->where('INDEX_NAME', 'idx_purchase_products_product_variant')->exists();
        try {
            $hadUnique && DB::statement('ALTER TABLE product_stocks DROP INDEX uq_product_stocks_product_variant');
            $hadBatch && DB::statement('ALTER TABLE purchase_products DROP INDEX idx_purchase_products_product_variant');
            InventoryMode::reset();
            $problems = InventoryMode::preflight();
            $this->assertNotEmpty($problems);
            $this->assertStringContainsString('product_stocks', implode(' ', $problems));
            $this->assertFalse(InventoryMode::confirmedOnly(), 'the flag is refused (behaves as before) rather than running with table-wide locks');
            $this->artisan('inventory:db-health')->expectsOutputToContain('no index on (product_id, variant)')->assertExitCode(1);
        } finally {
            $hadUnique && DB::statement('ALTER TABLE product_stocks ADD UNIQUE KEY uq_product_stocks_product_variant (product_id, variant)');
            $hadBatch && DB::statement('ALTER TABLE purchase_products ADD KEY idx_purchase_products_product_variant (product_id, variant(100))');
            InventoryMode::reset();
            // the index DDL commits the test's transaction (MySQL implicit commit), so remove what setUp inserted by hand
            DB::table('suppliers')->where('id', $this->supplierId)->delete();
        }
        $this->assertSame([], InventoryMode::preflight());
    }
}
