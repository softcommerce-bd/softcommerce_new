<?php

/*
|--------------------------------------------------------------------------
| Create the test database
|--------------------------------------------------------------------------
|
| The schema of this project lives in SQL dumps (sqlupdates/), not in Laravel
| migrations, so tests cannot build it from scratch. This script copies the
| table structure of the development database into a separate database
| (default: <DB_DATABASE>_test), copies a few non-personal reference tables,
| and leaves every other table empty. No customer, order or user rows are
| copied.
|
| Usage, from the project root:
|   php tests/create_test_database.php            # creates misron_test
|   php tests/create_test_database.php --fresh    # drops and recreates it
|
| Then apply the module migrations to it:
|   DB_DATABASE=misron_test php artisan migrate --path=database/migrations/sales_funnel --force
|
| Rehearsing the stock-table changes on real rows (needs --fresh):
|   TEST_DB_DATABASE=misron_inventory_test php tests/create_test_database.php --fresh --inventory-data
| also copies the rows of the stock tables (products, stock,
| purchase batches, purchases). They hold no names, addresses or
| phone numbers. The source database is only read.
|
*/

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$source = config('database.connections.mysql.database');
$target = getenv('TEST_DB_DATABASE') ?: $source . '_test';
$fresh = in_array('--fresh', $argv, true);
$inventoryData = in_array('--inventory-data', $argv, true);

if (substr($target, -5) !== '_test' || $target === $source) {
    fwrite(STDERR, "Refusing to write to '{$target}': the test database name must end in _test.\n");
    exit(1);
}
if ($inventoryData && !$fresh) {
    fwrite(STDERR, "--inventory-data needs --fresh, so rows are never mixed into an existing test database.\n");
    exit(1);
}

// Reference data only: nothing here describes a person or an order.
$copyRows = ['languages', 'business_settings', 'currencies', 'addons', 'permissions', 'roles', 'role_has_permissions'];

// Stock-domain rows for rehearsing schema changes. No customer data: order
// headers (which carry addresses) and users are not in this list.
if ($inventoryData) {
    $copyRows = array_merge($copyRows, ['products', 'product_stocks', 'purchase_products', 'purchases']);
}

// Created by the module's own migrations.
$skip = ['funnels', 'funnel_versions', 'funnel_templates', 'funnel_saved_sections', 'funnel_audit_logs'];

$pdo = DB::connection()->getPdo();
$quotedTarget = '`' . str_replace('`', '``', $target) . '`';

if ($fresh) {
    $pdo->exec("DROP DATABASE IF EXISTS {$quotedTarget}");
}
$pdo->exec("CREATE DATABASE IF NOT EXISTS {$quotedTarget} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$existing = array_map('current', $pdo->query("SHOW TABLES FROM {$quotedTarget}")->fetchAll(PDO::FETCH_NUM));
$tables = array_map('current', $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_NUM));

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$created = 0;
foreach ($tables as $table) {
    if (in_array($table, $skip, true) || in_array($table, $existing, true)) {
        continue;
    }
    $create = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`')->fetch(PDO::FETCH_NUM)[1];
    // Start every copied table empty, whatever auto-increment the source is at.
    $create = preg_replace('/ AUTO_INCREMENT=\d+/', '', $create);
    $pdo->exec("USE {$quotedTarget}");
    $pdo->exec($create);
    $pdo->exec('USE `' . str_replace('`', '``', $source) . '`');
    $created++;

    if (in_array($table, $copyRows, true)) {
        $pdo->exec("INSERT INTO {$quotedTarget}.`{$table}` SELECT * FROM `{$table}`");
    }
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "Test database {$target}: {$created} tables created, " . count($existing) . " already present.\n";
