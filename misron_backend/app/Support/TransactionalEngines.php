<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Are the money/stock tables on a transactional storage engine (InnoDB)?
 *
 * MyISAM has no transactions, no row locks and no rollback. On such a table every T-5 guarantee is void, and a deadlock retry is
 * actively dangerous: the InnoDB part of a request is rolled back but the MyISAM writes made earlier in the same request stay,
 * so a retry repeats them (a second purchase, a second change of the supplier's balance).
 *
 * Two consumers:
 *  - DeadlockRetry / RetryDeadlockedCheckout NEVER retry while any listed table is not InnoDB (always, whatever the policy);
 *  - the 'engine.safe' route middleware (purchasing and supplier-balance routes only) refuses to run the request when the policy is
 *    'enforce' (default). Selling paths (POS, checkout) are not refused: StockLedger already gives MyISAM batch units back by hand.
 * Policy (env FINANCIAL_ENGINE_POLICY): enforce (default) | warn (log, header, run) | off (no gate; retry is still never used on non-InnoDB).
 * If the engines cannot be read (no access to information_schema) the state is "unknown": retries are not used, the gate lets the request through
 * and logs, so a permissions problem cannot stop the shop.
 */
final class TransactionalEngines
{
    /** Tables written inside the retried / locked money and stock transactions. Missing tables are ignored. */
    public const TABLES = [
        'purchases', 'purchase_products', 'suppliers', 'purchase_returns', 'purchase_return_products',
        'purchase_orders', 'purchase_order_details', 'supplier_payments', 'supplier_payment_purchases',
        'chart_of_accounts', 'account_statement_logs', 'balance_transfers', 'expense_payments', 'expense_payment_details',
        'incomes', 'income_details', 'vouchers', 'voucher_details', 'customer_payments', 'order_payment_mappings',
        'stock_adjustments', 'stock_adjustment_lines', 'courier_settlements', 'courier_settlement_details',
        'product_stocks', 'orders', 'order_details', 'idempotency_keys',
    ];

    /** The tables whose engine the purchasing / supplier-balance gate checks. */
    public const PURCHASING = ['purchases', 'purchase_products', 'suppliers', 'purchase_returns', 'purchase_return_products'];

    /** Test hooks. */
    public static ?array $tablesOverride = null;
    public static ?array $purchasingOverride = null;
    public static ?bool $forceSafe = null;

    /** @var array<string, array<string,string>|null> */
    private static array $cache = [];
    private static bool $logged = false;

    public static function policy(): string
    {
        $p = strtolower((string) config('inventory.financial_engine_policy', 'enforce'));

        return in_array($p, ['enforce', 'warn', 'off'], true) ? $p : 'enforce';
    }

    /** @return string[] */
    public static function purchasing(): array
    {
        return self::$purchasingOverride ?? self::PURCHASING;
    }

    /** @return string[] */
    public static function tables(?array $scope = null): array
    {
        return $scope ?? self::$tablesOverride ?? ((array) config('inventory.financial_tables') ?: self::TABLES);
    }

    /**
     * Tables that exist and are not InnoDB, as table => engine; null when the engines cannot be read.
     *
     * @return array<string,string>|null
     */
    public static function offenders(?array $scope = null): ?array
    {
        if (self::$forceSafe === true) {
            return [];
        }
        $tables = self::tables($scope);
        $key = DB::connection()->getName() . '|' . DB::connection()->getDatabaseName() . '|' . implode(',', $tables);
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return self::$cache[$key] = []; // no storage-engine concept (sqlite): nothing to enforce
        }
        try {
            $rows = DB::select(
                "SELECT TABLE_NAME AS t, ENGINE AS e FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME IN (" . implode(',', array_fill(0, count($tables), '?')) . ')',
                array_values($tables)
            );
        } catch (\Throwable $e) {
            Log::warning('TransactionalEngines: engines could not be read: ' . $e->getMessage());

            return self::$cache[$key] = null;
        }
        $bad = [];
        foreach ($rows as $r) {
            if (strtoupper((string) $r->e) !== 'INNODB') {
                $bad[(string) $r->t] = (string) $r->e;
            }
        }
        ksort($bad);

        return self::$cache[$key] = $bad;
    }

    /** True only when the engines were read and every listed table is InnoDB. */
    public static function isSafe(?array $scope = null): bool
    {
        return self::offenders($scope) === [];
    }

    /** Is a deadlock retry allowed? Never when a listed table is not InnoDB or the engines are unknown. */
    public static function retryAllowed(): bool
    {
        $o = self::offenders();
        if ($o !== []) {
            if (!self::$logged) {
                self::$logged = true;
                Log::critical('DEADLOCK RETRY DISABLED: money/stock tables are not InnoDB (' . ($o === null ? 'engines unreadable' : json_encode($o)) . '). A retry would repeat writes that did not roll back. Run `php artisan db:engine-preflight`.');
            }

            return false;
        }

        return true;
    }

    public static function forget(): void
    {
        self::$cache = [];
        self::$logged = false;
    }
}
