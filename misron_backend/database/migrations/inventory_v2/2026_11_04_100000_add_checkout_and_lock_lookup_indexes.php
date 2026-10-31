<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-5: indexes without which a locking read filtered on these columns scans, and therefore LOCKS, every row of the table
 * (measured on the disposable database: with no index on carts.user_id a second customer's checkout timed out with error 1205).
 *
 * Verified schema facts this is built from (Phase 0, docs/T5_PHASE0_SCHEMA_COMPATIBILITY.md section 5.1): carts, combined_orders, addresses,
 * purchases, supplier_payment_purchases and purchase_products have only a primary key; orders has only funnel indexes; account_statement_logs
 * has only (account_id, transaction_date).
 *
 * Properties:
 *  - ADDITIVE and NON-UNIQUE only, so existing duplicate data can never make it fail (unique constraints wait for the data audit);
 *  - REPEAT-SAFE: a table or column that does not exist is skipped; an index is added only when no existing index already starts with the
 *    same leading columns; running it twice changes nothing;
 *  - works on MySQL and MariaDB and on InnoDB and MyISAM tables; long text columns get a 100-character prefix;
 *  - ROLLBACK (down): drops exactly the indexes named idx_t5_* that it created, nothing else.
 *  - Production index build time depends on table size (production size and engine are BLOCKED/unknown): run in a maintenance window
 *    after rehearsal on a restored copy.
 */
return new class extends Migration
{
    /** table => [index suffix => [columns]] */
    private array $indexes = [
        'carts' => ['user' => ['user_id'], 'temp_user' => ['temp_user_id']],
        'combined_orders' => ['user_created' => ['user_id', 'created_at']],
        'orders' => ['code' => ['code'], 'combined' => ['combined_order_id'], 'user' => ['user_id']],
        'addresses' => ['user' => ['user_id']],
        'account_statement_logs' => ['source' => ['source_type', 'source_id'], 'order_account' => ['order_id', 'account_id']],
        'purchases' => ['supplier' => ['supplier_id']],
        'supplier_payment_purchases' => ['payment' => ['supplier_payment_id'], 'purchase' => ['purchase_id']],
        'purchase_products' => ['purchase' => ['purchase_id']],
    ];

    private function existingIndexes(string $table): array
    {
        $rows = DB::table('information_schema.STATISTICS')->where('TABLE_SCHEMA', DB::raw('DATABASE()'))->where('TABLE_NAME', $table)
            ->orderBy('INDEX_NAME')->orderBy('SEQ_IN_INDEX')->get(['INDEX_NAME', 'COLUMN_NAME']);
        $by = [];
        foreach ($rows as $r) {
            $by[$r->INDEX_NAME][] = strtolower($r->COLUMN_NAME);
        }

        return $by;
    }

    private function columnSpec(string $table, string $column): ?string
    {
        $c = DB::table('information_schema.COLUMNS')->where('TABLE_SCHEMA', DB::raw('DATABASE()'))->where('TABLE_NAME', $table)->where('COLUMN_NAME', $column)->first(['DATA_TYPE', 'CHARACTER_MAXIMUM_LENGTH']);
        if (!$c) {
            return null;
        }
        $text = in_array(strtolower($c->DATA_TYPE), ['varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext'], true);
        $needsPrefix = $text && (is_null($c->CHARACTER_MAXIMUM_LENGTH) || (int) $c->CHARACTER_MAXIMUM_LENGTH > 100);

        return '`' . $column . '`' . ($needsPrefix ? '(100)' : '');
    }

    public function up()
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        foreach ($this->indexes as $table => $defs) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $existing = $this->existingIndexes($table);
            foreach ($defs as $suffix => $columns) {
                $specs = array_map(fn ($c) => $this->columnSpec($table, $c), $columns);
                if (in_array(null, $specs, true)) {
                    continue; // a column is missing in this installation
                }
                $covered = false;
                foreach ($existing as $cols) {
                    if (array_slice($cols, 0, count($columns)) === array_map('strtolower', $columns)) {
                        $covered = true;
                        break;
                    }
                }
                $name = 'idx_t5_' . $suffix;
                if ($covered || isset($existing[$name])) {
                    continue;
                }
                DB::statement("ALTER TABLE `{$table}` ADD KEY `{$name}` (" . implode(', ', $specs) . ')');
            }
        }
    }

    public function down()
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        foreach ($this->indexes as $table => $defs) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $existing = $this->existingIndexes($table);
            foreach (array_keys($defs) as $suffix) {
                $name = 'idx_t5_' . $suffix;
                if (isset($existing[$name])) {
                    DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$name}`");
                }
            }
        }
    }
};
