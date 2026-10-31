<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Stock queries lock "the stock row" / "the oldest cost layer" of one product variation. Without an index on (product_id, variant) the
 * database answers them with a scan of the primary key and locks every row it passes. The older schema has no such index
 * (the optional sqlupdates/inventory_v1_stock_safety.sql adds it). Added here as NON-unique, only when no equivalent index exists,
 * so existing duplicate rows (if any) can never make this migration fail. Additive; no data is changed.
 */
return new class extends Migration
{
    private function hasLookup(string $table): bool
    {
        return DB::table('information_schema.STATISTICS as a')->join('information_schema.STATISTICS as b', fn ($j) => $j->on('a.INDEX_NAME', '=', 'b.INDEX_NAME')->on('a.TABLE_NAME', '=', 'b.TABLE_NAME')->on('a.TABLE_SCHEMA', '=', 'b.TABLE_SCHEMA'))
            ->where('a.TABLE_SCHEMA', DB::raw('DATABASE()'))->where('a.TABLE_NAME', $table)->where('a.SEQ_IN_INDEX', 1)->where('a.COLUMN_NAME', 'product_id')->where('b.SEQ_IN_INDEX', 2)->where('b.COLUMN_NAME', 'variant')->exists();
    }

    public function up()
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        if (!$this->hasLookup('product_stocks')) {
            DB::statement('ALTER TABLE `product_stocks` ADD KEY `idx_stock_lookup_product_variant` (`product_id`, `variant`(100))');
        }
        if (!$this->hasLookup('purchase_products')) {
            DB::statement('ALTER TABLE `purchase_products` ADD KEY `idx_stock_lookup_product_variant` (`product_id`, `variant`(100))');
        }
    }

    public function down()
    {
        foreach (['product_stocks', 'purchase_products'] as $t) {
            $has = DB::table('information_schema.STATISTICS')->where('TABLE_SCHEMA', DB::raw('DATABASE()'))->where('TABLE_NAME', $t)->where('INDEX_NAME', 'idx_stock_lookup_product_variant')->exists();
            if ($has) {
                DB::statement("ALTER TABLE `{$t}` DROP INDEX `idx_stock_lookup_product_variant`");
            }
        }
    }
};
