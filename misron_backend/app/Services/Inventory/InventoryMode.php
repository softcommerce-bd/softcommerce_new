<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/** The one switch between "deduct at order creation" (legacy) and "confirmed-order-only" (v2). */
class InventoryMode
{
    private static ?bool $engineOk = null;
    private static ?bool $columnsOk = null;
    private static array $lineMode = [];

    public static function reset(): void
    {
        self::$engineOk = self::$columnsOk = null;
        self::$lineMode = [];
    }

    public static function confirmedOnly(): bool
    {
        if (!config('inventory.confirmed_only')) {
            return false;
        }
        $problems = self::preflight();
        if ($problems !== []) {
            // Refuse to run half-installed: behave as before and say why, loudly.
            Log::critical('INVENTORY_CONFIRMED_ONLY is set but the system is not ready: ' . implode('; ', $problems));

            return false;
        }

        return true;
    }

    /** Are the v2 tables and columns present at all (independent of the switch)? */
    public static function installed(): bool
    {
        if (self::$columnsOk === null) {
            self::preflight();
        }

        return (bool) self::$columnsOk;
    }

    /** @return string[] reasons v2 cannot run; empty when ready */
    public static function preflight(): array
    {
        $problems = [];
        if (self::$columnsOk === null) {
            self::$columnsOk = Schema::hasTable('order_demands') && Schema::hasTable('stock_balances') && Schema::hasColumn('order_details', 'stock_mode') && Schema::hasColumn('products', 'stock_policy');
        }
        if (!self::$columnsOk) {
            $problems[] = 'inventory_v2 migrations have not been applied';
        }
        if (self::$engineOk === null) {
            $engines = DB::getDriverName() === 'mysql'
                ? DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::raw('DATABASE()'))->whereIn('TABLE_NAME', ['purchase_products', 'purchases', 'suppliers'])->pluck('ENGINE', 'TABLE_NAME')->all()
                : [];
            $bad = array_keys(array_filter($engines, fn ($e) => strtoupper((string) $e) !== 'INNODB'));
            self::$engineOk = $bad === [];
            self::$lineMode['__bad_engines'] = $bad;
        }
        if (!self::$engineOk) {
            $problems[] = 'tables not InnoDB: ' . implode(', ', self::$lineMode['__bad_engines']) . ' (run sqlupdates/inventory_v2_innodb_conversion.sql on a backed-up copy first)';
        }

        return $problems;
    }

    /** Is this order line governed by the confirmed-order model? Reads the column once per line. */
    public static function lineIsV2(int $detailId): bool
    {
        if (self::$columnsOk === null) {
            self::preflight();
        }
        if (!self::$columnsOk) {
            return false;
        }

        return self::$lineMode[$detailId] ??= (DB::table('order_details')->where('id', $detailId)->value('stock_mode') === 'v2');
    }
}
