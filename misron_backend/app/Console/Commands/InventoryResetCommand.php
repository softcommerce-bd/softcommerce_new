<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clears test orders, purchases and stock records that no longer agree with each other, so a
 * store that was filled with dummy data can start again from clean, consistent inventory.
 *
 * It is a dry run unless --execute is given, and it will not touch a database it was not told
 * about, whose name does not end in "_test", or that looks like it holds real sales, without the
 * deliberate flags below. Never run it against a live shop without a backup and the owner's
 * written say-so.
 *
 *   php artisan inventory:reset-dummy-data --database=misron_e2e_test                 # report only
 *   php artisan inventory:reset-dummy-data --database=misron_e2e_test --execute --confirm=misron_e2e_test
 *   php artisan inventory:reset-dummy-data --database=shop --allow-non-test --execute --confirm=shop
 *
 * Scopes:
 *   transactions  (default) orders, purchases, returns, adjustments, carts, payment/ledger logs.
 *                 Products and customers stay; every stock quantity becomes 0 (there are no cost
 *                 batches left to back it), accounts return to their opening balances.
 *   catalogue     also the products themselves with their stock rows, translations, reviews, ...
 *
 * Never touched: table structures, users (admin and staff accounts included), roles and
 * permissions, settings, languages, currencies, add-ons, categories, brands, attributes, uploads,
 * the chart of accounts itself, and the sales funnel pages, versions, templates, saved sections
 * and visitor events. (The funnel checkout attempts, order attribution and payment-gateway
 * session rows belong to the deleted orders and go with them.)
 */
class InventoryResetCommand extends Command
{
    protected $signature = 'inventory:reset-dummy-data
        {--database= : Database to clean (required)}
        {--scope=transactions : transactions or catalogue}
        {--execute : Actually delete. Without it nothing is changed}
        {--confirm= : Type the database name again to confirm}
        {--allow-non-test : Allow a database whose name does not end in _test}
        {--i-know-there-are-paid-orders : Allow it when online-paid orders exist}';

    protected $description = 'Clear dummy orders, purchases and stock so inventory can start clean (dry run by default)';

    /** Deleted in this order (children first). Tables that do not exist are skipped. */
    private const TRANSACTION_TABLES = [
        'order_details', 'orders', 'combined_orders', 'carts', 'coupon_usages', 'commission_histories',
        'refund_requests', 'checkout_otps', 'funnel_checkout_attempts', 'funnel_order_attributions', 'payment_gateway_attempts',
        'purchase_return_products', 'purchase_returns', 'purchase_products', 'purchases',
        'stock_adjustment_lines', 'stock_adjustments', 'account_statement_logs',
    ];

    private const CATALOGUE_TABLES = [
        'product_stocks', 'product_categories', 'product_translations', 'product_taxes', 'reviews', 'wishlists',
        'flash_deal_products', 'frequently_bought_products', 'last_viewed_products', 'products',
    ];

    private const ONLINE_PAYMENTS = ['paystation', 'bkash', 'nagad', 'sslcommerz', 'aamarpay', 'paypal', 'stripe', 'razorpay', 'paytm', 'iyzico', 'paystack', 'flutterwave', 'instamojo', 'payfast', 'khalti', 'myfatoorah', 'phonepe'];

    public function handle(): int
    {
        $database = (string) $this->option('database');
        $scope = (string) $this->option('scope');
        if ($database === '') {
            $this->error('Say which database with --database=NAME. Nothing was changed.');

            return self::FAILURE;
        }
        if (!in_array($scope, ['transactions', 'catalogue'], true)) {
            $this->error('The scope must be "transactions" or "catalogue".');

            return self::FAILURE;
        }

        config(['database.connections.inventory_reset' => array_merge(config('database.connections.' . config('database.default')), ['database' => $database])]);
        DB::purge('inventory_reset');
        $db = DB::connection('inventory_reset');
        try {
            $db->getPdo();
        } catch (\Throwable $e) {
            $this->error("Cannot open database {$database}: " . $e->getMessage());

            return self::FAILURE;
        }
        $schema = $db->getSchemaBuilder();

        $isTest = substr($database, -5) === '_test';
        if (!$isTest && !$this->option('allow-non-test')) {
            $this->error("{$database} is not a test database (its name does not end in _test). Refusing. Nothing was changed.");

            return self::FAILURE;
        }

        $this->line("Database: {$database}" . ($isTest ? ' (test database)' : ' (NOT a test database)') . ", scope: {$scope}");
        $this->report($db, $schema);

        // Real money is the clearest sign this is not throw-away data.
        $paid = $schema->hasTable('orders')
            ? (int) $db->table('orders')->where('payment_status', 'paid')->whereIn('payment_type', self::ONLINE_PAYMENTS)->count()
            : 0;
        if ($paid > 0 && !$this->option('i-know-there-are-paid-orders')) {
            $this->error("{$paid} order(s) were paid online. That is not dummy data. Refusing. Nothing was changed.");

            return self::FAILURE;
        }

        $tables = array_values(array_filter(
            $scope === 'catalogue' ? array_merge(self::TRANSACTION_TABLES, self::CATALOGUE_TABLES) : self::TRANSACTION_TABLES,
            function ($table) use ($schema) {
                return $schema->hasTable($table);
            }
        ));

        $plan = [];
        foreach ($tables as $table) {
            $plan[] = [$table, number_format($db->table($table)->count())];
        }
        $this->line('');
        $this->line('Rows that would be deleted:');
        $this->table(['Table', 'Rows'], $plan);
        $this->line('Also reset: every stock quantity to 0, products.num_of_sale to 0, account balances to their opening balance, supplier balances to 0.');

        if (!$this->option('execute')) {
            $this->info('Dry run: nothing was changed. Add --execute --confirm=' . $database . ' to clean.');

            return self::SUCCESS;
        }
        if ($this->option('confirm') !== $database) {
            $this->error('To delete, repeat the database name: --confirm=' . $database . '. Nothing was changed.');

            return self::FAILURE;
        }

        $db->statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($tables as $table) {
                $db->table($table)->delete();
            }
            if ($scope === 'transactions' && $schema->hasTable('product_stocks')) {
                $db->table('product_stocks')->update(['qty' => 0]);
            }
            if ($schema->hasTable('products')) {
                $db->table('products')->update(array_merge(['num_of_sale' => 0], $scope === 'transactions' ? ['current_stock' => 0] : []));
            }
            if ($schema->hasTable('chart_of_accounts')) {
                $db->statement('UPDATE chart_of_accounts SET current_balance = opening_balance');
            }
            if ($schema->hasTable('suppliers')) {
                $db->table('suppliers')->update(['due_amount' => 0, 'paid_amount' => 0]);
            }
        } finally {
            $db->statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->info('Done. Enter opening stock through a purchase (or run inventory:sample-data on a test database).');

        return self::SUCCESS;
    }

    /** Facts the operator needs to be sure this is throw-away data. */
    private function report($db, $schema): void
    {
        $facts = [];
        if ($schema->hasTable('orders')) {
            $orders = $db->table('orders');
            $facts[] = ['Orders', number_format($orders->count())];
            $facts[] = ['Oldest / newest order', ($orders->min('created_at') ?: '-') . ' / ' . ($orders->max('created_at') ?: '-')];
            $facts[] = ['Orders paid online', number_format($db->table('orders')->where('payment_status', 'paid')->whereIn('payment_type', self::ONLINE_PAYMENTS)->count())];
        }
        if ($schema->hasTable('users')) {
            foreach ($db->table('users')->select('user_type', DB::raw('count(*) as total'))->groupBy('user_type')->get() as $row) {
                $facts[] = ["Users: {$row->user_type}", number_format($row->total)];
            }
        }
        $this->table(['What the database holds', ''], $facts);
    }
}
