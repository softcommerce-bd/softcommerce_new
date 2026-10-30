<?php

namespace App\Services\Inventory;

use Illuminate\Database\ConnectionInterface;

/**
 * Read-only inspection of the stock tables.
 *
 * Nothing in this class writes. It answers three questions:
 *
 *  1. Is the schema ready for the shared stock service (engines, indexes,
 *     duplicate or odd variation keys)?
 *  2. Where do the three stock records disagree?
 *       - product_stocks.qty        what can be sold, per product and variation
 *       - purchase_products         FIFO cost batches (remaining_qty)
 *       - products.current_stock    a cached total some screens still show
 *  3. Which products should not be sold until somebody has checked them?
 *
 * It never corrects anything. Every finding is a row a person decides about.
 */
class InventoryAudit
{
    /** Stock matches the cost batches. */
    public const OK = 'ok';

    /** Units are on the shelf but no purchase batch holds their cost. */
    public const NO_BATCHES = 'no_cost_batches';

    /** Some units have a cost batch, some do not. */
    public const BATCHES_SHORT = 'cost_batches_short';

    /** Cost batches hold more than the sellable quantity. */
    public const BATCH_EXCESS = 'batch_excess';

    public const NEGATIVE = 'negative_stock';

    /** Hold levels, most severe last. */
    public const HOLD_NONE = 'none';
    public const HOLD_REVIEW = 'review';
    public const HOLD_LIMITED = 'limited';
    public const HOLD_BLOCKED = 'blocked';

    private const HOLD_RANK = [self::HOLD_NONE => 0, self::HOLD_REVIEW => 1, self::HOLD_LIMITED => 2, self::HOLD_BLOCKED => 3];

    private ConnectionInterface $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    // ------------------------------------------------------------------
    // Schema
    // ------------------------------------------------------------------

    /**
     * Engines, row format, indexes, foreign keys, triggers and the server
     * settings that decide how the stock tables behave under load.
     */
    public function schema(): array
    {
        $database = $this->databaseName();
        $tables = ['product_stocks', 'purchase_products', 'purchases', 'order_details', 'orders', 'products', 'stock_adjustments', 'stock_adjustment_lines', 'purchase_returns', 'purchase_return_products'];

        $info = $this->db->select(
            'SELECT table_name AS name, engine AS engine, row_format AS row_format, table_rows AS approx_rows, data_length AS data_length, index_length AS index_length, table_collation AS collation, auto_increment AS auto_increment
               FROM information_schema.tables WHERE table_schema = ? AND table_name IN (' . $this->marks($tables) . ') ORDER BY table_name',
            array_merge([$database], $tables)
        );

        $indexes = $this->db->select(
            'SELECT table_name AS name, index_name AS index_name, non_unique AS non_unique, GROUP_CONCAT(CONCAT(column_name, IF(sub_part IS NULL, \'\', CONCAT(\'(\', sub_part, \')\'))) ORDER BY seq_in_index) AS columns_list
               FROM information_schema.statistics WHERE table_schema = ? AND table_name IN (' . $this->marks($tables) . ')
              GROUP BY table_name, index_name, non_unique ORDER BY table_name, index_name',
            array_merge([$database], $tables)
        );

        $columns = $this->db->select(
            'SELECT table_name AS name, column_name AS column_name, column_type AS column_type, is_nullable AS is_nullable, character_set_name AS charset, collation_name AS collation
               FROM information_schema.columns WHERE table_schema = ? AND (
                    (table_name = \'product_stocks\' AND column_name IN (\'product_id\', \'variant\', \'qty\'))
                 OR (table_name = \'purchase_products\' AND column_name IN (\'product_id\', \'variant\', \'purchase_quantity\', \'remaining_qty\', \'buying_unit_cost\')))
              ORDER BY table_name, ordinal_position',
            [$database]
        );

        $foreignKeys = $this->db->select(
            'SELECT table_name AS name, constraint_name AS constraint_name, referenced_table_name AS refers_to
               FROM information_schema.referential_constraints
              WHERE constraint_schema = ? AND (table_name IN (\'product_stocks\', \'purchase_products\', \'purchases\') OR referenced_table_name IN (\'product_stocks\', \'purchase_products\', \'purchases\'))',
            [$database]
        );

        $triggers = $this->db->select('SELECT trigger_name AS name, event_object_table AS on_table FROM information_schema.triggers WHERE trigger_schema = ?', [$database]);
        $views = $this->db->select('SELECT table_name AS name FROM information_schema.views WHERE table_schema = ?', [$database]);
        $routines = $this->db->select('SELECT routine_name AS name FROM information_schema.routines WHERE routine_schema = ?', [$database]);

        // MySQL 8 exposes @@transaction_isolation; MariaDB 10.x only has @@tx_isolation
        $server = [];
        foreach (['transaction_isolation', 'tx_isolation'] as $isolationVar) {
            try {
                $server = (array) ($this->db->selectOne(
                    "SELECT VERSION() AS version, @@version_comment AS comment, @@sql_mode AS sql_mode, @@{$isolationVar} AS isolation,
                            @@innodb_lock_wait_timeout AS lock_wait_timeout, @@innodb_file_per_table AS file_per_table,
                            @@innodb_default_row_format AS default_row_format, @@log_bin AS log_bin"
                ) ?? []);
                break;
            } catch (\Throwable $e) {
                continue;
            }
        }

        return [
            'database' => $database,
            'server' => $server,
            'tables' => array_map(fn ($r) => (array) $r, $info),
            'indexes' => array_map(fn ($r) => (array) $r, $indexes),
            'columns' => array_map(fn ($r) => (array) $r, $columns),
            'foreign_keys' => array_map(fn ($r) => (array) $r, $foreignKeys),
            'triggers' => array_map(fn ($r) => (array) $r, $triggers),
            'views' => array_map(fn ($r) => (array) $r, $views),
            'routines' => array_map(fn ($r) => (array) $r, $routines),
        ];
    }

    /** Storage engine of one table, or null when it does not exist. */
    public function engine(string $table): ?string
    {
        $row = $this->db->selectOne('SELECT engine AS engine FROM information_schema.tables WHERE table_schema = ? AND table_name = ?', [$this->databaseName(), $table]);

        return $row ? $row->engine : null;
    }

    /** Row counts that frame every other number in a report. */
    public function counts(): array
    {
        return (array) $this->db->selectOne(
            'SELECT
                (SELECT COUNT(*) FROM products) AS products,
                (SELECT COUNT(*) FROM products WHERE digital = 1) AS digital_products,
                (SELECT COUNT(*) FROM products WHERE published = 1) AS published_products,
                (SELECT COUNT(*) FROM product_stocks) AS stock_rows,
                (SELECT COUNT(DISTINCT product_id) FROM product_stocks) AS products_with_stock_rows,
                (SELECT COUNT(*) FROM purchase_products) AS batches,
                (SELECT COUNT(*) FROM purchase_products WHERE remaining_qty > 0) AS open_batches,
                (SELECT COUNT(*) FROM purchases) AS purchases,
                (SELECT COUNT(*) FROM orders) AS orders,
                (SELECT COUNT(*) FROM order_details) AS order_lines,
                (SELECT COUNT(*) FROM stock_adjustments) AS stock_adjustments'
        );
    }

    // ------------------------------------------------------------------
    // Variation keys and data quality
    // ------------------------------------------------------------------

    /**
     * How variation values look, for both tables: NULL, empty, padded with
     * spaces, or ordinary text. The unique index treats NULL as distinct from
     * everything, so NULL and empty values must be understood before it is added.
     */
    public function variantShapes(): array
    {
        $shape = 'CASE WHEN variant IS NULL THEN \'null\' WHEN variant = \'\' THEN \'empty\' WHEN CHAR_LENGTH(variant) <> CHAR_LENGTH(TRIM(variant)) THEN \'padded\' ELSE \'text\' END';

        return [
            'product_stocks' => array_map(fn ($r) => (array) $r, $this->db->select("SELECT {$shape} AS shape, COUNT(*) AS rows_n, COUNT(DISTINCT product_id) AS products FROM product_stocks GROUP BY 1")),
            'purchase_products' => array_map(fn ($r) => (array) $r, $this->db->select("SELECT {$shape} AS shape, COUNT(*) AS rows_n, COUNT(DISTINCT product_id) AS products, SUM(remaining_qty > 0) AS open_rows FROM purchase_products GROUP BY 1")),
        ];
    }

    /**
     * (product_id, variant) pairs that appear more than once in product_stocks,
     * compared with the column's own collation, which is what a unique index
     * would use. Every row of every group is returned so a person can decide
     * which quantity is right. Nothing is merged here.
     */
    public function duplicateStockRows(): array
    {
        $groups = $this->db->select(
            'SELECT product_id, variant, COUNT(*) AS copies FROM product_stocks GROUP BY product_id, variant HAVING COUNT(*) > 1 ORDER BY product_id, variant'
        );

        $out = [];
        foreach ($groups as $group) {
            $rows = $this->db->select('SELECT id, product_id, variant, sku, qty, price, created_at, updated_at FROM product_stocks WHERE product_id = ? AND variant = ? ORDER BY id', [$group->product_id, $group->variant]);
            $out[] = ['product_id' => (int) $group->product_id, 'variant' => $group->variant, 'copies' => (int) $group->copies, 'rows' => array_map(fn ($r) => (array) $r, $rows)];
        }

        return $out;
    }

    /** Stock rows, batches and order lines that point at nothing or hold impossible numbers. */
    public function integrity(): array
    {
        $rows = fn (string $sql, array $bind = []) => array_map(fn ($r) => (array) $r, $this->db->select($sql, $bind));

        return [
            'negative_stock' => $rows('SELECT id, product_id, variant, qty FROM product_stocks WHERE qty < 0 ORDER BY product_id, id'),
            'negative_batches' => $rows('SELECT id, product_id, variant, remaining_qty FROM purchase_products WHERE remaining_qty < 0 ORDER BY id'),
            'batch_remaining_over_purchased' => $rows('SELECT id, product_id, variant, purchase_quantity, remaining_qty FROM purchase_products WHERE remaining_qty > CAST(purchase_quantity AS SIGNED) ORDER BY id'),
            'stock_rows_without_product' => $rows('SELECT s.id, s.product_id, s.variant, s.qty FROM product_stocks s LEFT JOIN products p ON p.id = s.product_id WHERE p.id IS NULL ORDER BY s.id'),
            'products_without_stock_rows' => $rows('SELECT p.id, p.name, p.published, p.digital FROM products p LEFT JOIN product_stocks s ON s.product_id = p.id WHERE s.id IS NULL AND p.digital <> 1 ORDER BY p.id'),
            'batches_without_purchase' => $rows('SELECT b.id, b.purchase_id, b.product_id, b.remaining_qty FROM purchase_products b LEFT JOIN purchases u ON u.id = b.purchase_id WHERE u.id IS NULL ORDER BY b.id'),
            'batches_non_numeric' => $rows('SELECT id, product_id, purchase_quantity, remaining_qty, buying_unit_cost FROM purchase_products
                WHERE purchase_quantity IS NULL OR purchase_quantity NOT REGEXP \'^[0-9]+$\' OR remaining_qty IS NULL
                   OR buying_unit_cost IS NULL OR buying_unit_cost NOT REGEXP \'^[0-9]+(\\\\.[0-9]+)?$\' ORDER BY id LIMIT 200'),
            'cancelled_orders_with_live_lines' => $rows('SELECT o.id AS order_id, o.code, o.delivery_status AS order_status, COUNT(*) AS lines_not_cancelled, SUM(d.quantity) AS units
                FROM orders o JOIN order_details d ON d.order_id = o.id
               WHERE o.delivery_status = \'cancelled\' AND d.delivery_status <> \'cancelled\' GROUP BY o.id, o.code, o.delivery_status ORDER BY o.id'),
            'order_lines_without_stock_row' => $rows('SELECT d.id, d.order_id, d.product_id, d.variation, d.quantity FROM order_details d
                LEFT JOIN product_stocks s ON s.product_id = d.product_id AND s.variant = d.variation WHERE s.id IS NULL ORDER BY d.id LIMIT 200'),
        ];
    }

    /**
     * Whether the DDL that Phase 4 proposes can run without error, with the
     * reason when it cannot. Zero dates are checked because converting a
     * MyISAM table to InnoDB under strict mode rejects them.
     */
    public function zeroDates(): array
    {
        $count = fn (string $table) => (int) $this->db->selectOne("SELECT COUNT(*) AS n FROM {$table} WHERE created_at IS NOT NULL AND CAST(created_at AS CHAR) LIKE '0000-00-00%'")->n;

        return ['purchase_products' => $count('purchase_products'), 'purchases' => $count('purchases')];
    }

    // ------------------------------------------------------------------
    // Reconciliation
    // ------------------------------------------------------------------

    /**
     * One row per (product, variation) seen in either table, with the three
     * numbers side by side and a status describing the gap.
     *
     * Variation text is matched ignoring case and trailing spaces, the way the
     * columns' own collations compare it. Rows that only match that way are
     * flagged `loose_match`, because they work today only through the collation.
     */
    public function variantRows(): array
    {
        $stocks = $this->db->select('SELECT product_id, variant, SUM(qty) AS qty, COUNT(*) AS copies FROM product_stocks GROUP BY product_id, variant');
        $batches = $this->db->select('SELECT product_id, variant, SUM(remaining_qty) AS remaining, SUM(CAST(purchase_quantity AS SIGNED)) AS bought, COUNT(*) AS batch_count FROM purchase_products GROUP BY product_id, variant');
        $orders = $this->db->select(
            'SELECT d.product_id, d.variation AS variant,
                    SUM(CASE WHEN o.delivery_status <> \'cancelled\' AND d.delivery_status <> \'cancelled\' THEN d.quantity ELSE 0 END) AS live_units,
                    SUM(CASE WHEN o.delivery_status = \'cancelled\' OR d.delivery_status = \'cancelled\' THEN d.quantity ELSE 0 END) AS cancelled_units
               FROM order_details d LEFT JOIN orders o ON o.id = d.order_id GROUP BY d.product_id, d.variation'
        );
        $products = [];
        foreach ($this->db->select('SELECT id FROM products') as $p) {
            $products[(int) $p->id] = true;
        }

        $rows = [];
        $touch = function (int $productId, ?string $variant) use (&$rows) {
            $key = $productId . '|' . $this->normalise($variant);
            if (!isset($rows[$key])) {
                $rows[$key] = [
                    'product_id' => $productId, 'variant' => $variant ?? '', 'stock_qty' => null, 'stock_rows' => 0,
                    'fifo_remaining' => 0, 'fifo_bought' => 0, 'batches' => 0, 'ordered_live' => 0, 'ordered_cancelled' => 0,
                    'loose_match' => false, '_spellings' => [],
                ];
            }

            return $key;
        };

        foreach ($stocks as $r) {
            $key = $touch((int) $r->product_id, $r->variant);
            $rows[$key]['stock_qty'] = (int) $r->qty;
            $rows[$key]['stock_rows'] = (int) $r->copies;
            $rows[$key]['variant'] = (string) $r->variant;
            $rows[$key]['_spellings']['stock'] = (string) $r->variant;
        }
        foreach ($batches as $r) {
            $key = $touch((int) $r->product_id, $r->variant);
            $rows[$key]['fifo_remaining'] += (int) $r->remaining;
            $rows[$key]['fifo_bought'] += (int) $r->bought;
            $rows[$key]['batches'] += (int) $r->batch_count;
            $rows[$key]['_spellings']['batch'] = (string) $r->variant;
            if ($rows[$key]['variant'] === '' && $r->variant !== null) {
                $rows[$key]['variant'] = (string) $r->variant;
            }
        }
        foreach ($orders as $r) {
            $key = $touch((int) $r->product_id, $r->variant);
            $rows[$key]['ordered_live'] += (int) $r->live_units;
            $rows[$key]['ordered_cancelled'] += (int) $r->cancelled_units;
        }

        $out = [];
        foreach ($rows as $row) {
            $spellings = $row['_spellings'];
            $row['loose_match'] = isset($spellings['stock'], $spellings['batch']) && $spellings['stock'] !== $spellings['batch'];
            unset($row['_spellings']);

            $row['product_exists'] = isset($products[$row['product_id']]);
            $row['in_stock_table'] = $row['stock_qty'] !== null;
            $stock = (int) ($row['stock_qty'] ?? 0);
            $fifo = $row['fifo_remaining'];

            if (!$row['product_exists']) {
                $row['status'] = 'orphan_batch';
                $row['hold'] = self::HOLD_REVIEW;
            } elseif (!$row['in_stock_table']) {
                $row['status'] = 'batch_without_stock_row';
                $row['hold'] = self::HOLD_REVIEW;
            } elseif ($stock < 0) {
                $row['status'] = self::NEGATIVE;
                $row['hold'] = self::HOLD_BLOCKED;
            } elseif ($stock === $fifo) {
                $row['status'] = self::OK;
                $row['hold'] = self::HOLD_NONE;
            } elseif ($stock > 0 && $fifo === 0) {
                $row['status'] = self::NO_BATCHES;
                $row['hold'] = self::HOLD_BLOCKED;
            } elseif ($stock > $fifo) {
                $row['status'] = self::BATCHES_SHORT;
                $row['hold'] = self::HOLD_LIMITED;
            } else {
                $row['status'] = self::BATCH_EXCESS;
                $row['hold'] = self::HOLD_REVIEW;
            }

            $row['stock_qty'] = $stock;
            // What the shared stock service can actually sell: both records must have the units.
            $row['sellable_units'] = $row['product_exists'] && $row['in_stock_table'] ? max(0, min($stock, $fifo)) : 0;
            $row['gap'] = $stock - $fifo;
            $out[] = $row;
        }

        usort($out, fn ($a, $b) => [$a['product_id'], $a['variant']] <=> [$b['product_id'], $b['variant']]);

        return $out;
    }

    /**
     * One row per product: the cached total, the stock-table total and the batch
     * total, plus the worst hold level among its variations.
     */
    public function productRows(?array $variantRows = null): array
    {
        $variantRows = $variantRows ?? $this->variantRows();

        $meta = [];
        foreach ($this->db->select('SELECT id, name, published, digital, variant_product, current_stock FROM products') as $p) {
            $meta[(int) $p->id] = (array) $p;
        }

        $products = [];
        foreach ($variantRows as $row) {
            $id = $row['product_id'];
            if (!isset($products[$id])) {
                $m = $meta[$id] ?? null;
                $products[$id] = [
                    'product_id' => $id, 'name' => $m['name'] ?? null, 'published' => $m ? (int) $m['published'] : null,
                    'digital' => $m ? (int) $m['digital'] : null, 'current_stock' => $m ? (int) $m['current_stock'] : null,
                    'stock_total' => 0, 'fifo_total' => 0, 'sellable_total' => 0, 'variants' => 0, 'variants_flagged' => 0,
                    'ordered_live' => 0, 'ordered_cancelled' => 0, 'hold' => self::HOLD_NONE, 'statuses' => [],
                ];
            }
            $p = &$products[$id];
            $p['stock_total'] += $row['stock_qty'];
            $p['fifo_total'] += $row['fifo_remaining'];
            $p['sellable_total'] += $row['sellable_units'];
            $p['ordered_live'] += $row['ordered_live'];
            $p['ordered_cancelled'] += $row['ordered_cancelled'];
            $p['variants']++;
            if ($row['status'] !== self::OK) {
                $p['variants_flagged']++;
                $p['statuses'][$row['status']] = ($p['statuses'][$row['status']] ?? 0) + 1;
            }
            if (self::HOLD_RANK[$row['hold']] > self::HOLD_RANK[$p['hold']]) {
                $p['hold'] = $row['hold'];
            }
            unset($p);
        }

        // Catalogue products with no stock row and no batch are listed too.
        foreach ($meta as $id => $m) {
            if (!isset($products[$id]) && (int) $m['digital'] !== 1) {
                $products[$id] = [
                    'product_id' => $id, 'name' => $m['name'], 'published' => (int) $m['published'], 'digital' => 0,
                    'current_stock' => (int) $m['current_stock'], 'stock_total' => 0, 'fifo_total' => 0, 'sellable_total' => 0,
                    'variants' => 0, 'variants_flagged' => 0, 'ordered_live' => 0, 'ordered_cancelled' => 0,
                    'hold' => self::HOLD_BLOCKED, 'statuses' => ['no_stock_rows' => 1],
                ];
            }
        }

        foreach ($products as &$p) {
            $p['cache_matches_stock'] = $p['current_stock'] === null ? null : $p['current_stock'] === $p['stock_total'];
            $p['cache_matches_batches'] = $p['current_stock'] === null ? null : $p['current_stock'] === $p['fifo_total'];
            $p['stock_matches_batches'] = $p['stock_total'] === $p['fifo_total'];
            $p['cache_gap'] = $p['current_stock'] === null ? null : $p['current_stock'] - $p['stock_total'];
        }
        unset($p);

        ksort($products);

        return array_values($products);
    }

    /**
     * Roll-up numbers for the report headline. Pass the rows already fetched to
     * avoid reading the tables twice.
     */
    public function summary(array $variantRows, array $productRows): array
    {
        $byStatus = [];
        foreach ($variantRows as $r) {
            $byStatus[$r['status']] = ($byStatus[$r['status']] ?? 0) + 1;
        }
        ksort($byStatus);

        $byHold = [self::HOLD_NONE => 0, self::HOLD_REVIEW => 0, self::HOLD_LIMITED => 0, self::HOLD_BLOCKED => 0];
        foreach ($productRows as $p) {
            $byHold[$p['hold']]++;
        }

        $considered = array_filter($productRows, fn ($p) => $p['digital'] !== 1 && $p['name'] !== null);

        return [
            'variants_total' => count($variantRows),
            'variants_by_status' => $byStatus,
            'products_total' => count($considered),
            'products_by_hold' => $byHold,
            'products_cache_differs_from_stock' => count(array_filter($considered, fn ($p) => $p['cache_matches_stock'] === false)),
            'products_cache_differs_from_batches' => count(array_filter($considered, fn ($p) => $p['cache_matches_batches'] === false)),
            'products_stock_differs_from_batches' => count(array_filter($considered, fn ($p) => !$p['stock_matches_batches'])),
            'products_all_three_agree' => count(array_filter($considered, fn ($p) => $p['cache_matches_stock'] && $p['stock_matches_batches'])),
            'units_in_stock' => array_sum(array_column($variantRows, 'stock_qty')),
            'units_with_cost_batch' => array_sum(array_column($variantRows, 'sellable_units')),
            'units_without_cost_batch' => array_sum(array_map(fn ($r) => $r['product_exists'] ? max(0, $r['stock_qty'] - $r['fifo_remaining']) : 0, $variantRows)),
            'units_in_orphan_batches' => array_sum(array_map(fn ($r) => !$r['product_exists'] ? $r['fifo_remaining'] : 0, $variantRows)),
        ];
    }

    // ------------------------------------------------------------------
    // Migration readiness
    // ------------------------------------------------------------------

    /**
     * Go / no-go checks for converting purchase_products to InnoDB and adding
     * the two indexes. Each item has a level: `blocker` (do not run the change),
     * `warning` (run only after a person has looked) or `info`.
     */
    public function preflight(?array $schema = null): array
    {
        $schema = $schema ?? $this->schema();
        $checks = [];
        $add = function (string $code, string $level, string $title, string $detail, $evidence = null) use (&$checks) {
            $checks[] = ['code' => $code, 'level' => $level, 'title' => $title, 'detail' => $detail, 'evidence' => $evidence];
        };

        $server = $schema['server'];
        $tables = [];
        foreach ($schema['tables'] as $t) {
            $tables[$t['name']] = $t;
        }
        $isMaria = stripos(($server['version'] ?? '') . ' ' . ($server['comment'] ?? ''), 'mariadb') !== false;
        $add('server', 'info', 'Database server', ($server['version'] ?? '?') . ' (' . ($server['comment'] ?? '') . '), isolation ' . ($server['isolation'] ?? '?') . ', lock wait ' . ($server['lock_wait_timeout'] ?? '?') . 's', $server);

        foreach (['purchase_products', 'purchases', 'product_stocks'] as $name) {
            $t = $tables[$name] ?? null;
            if (!$t) {
                $add("table_{$name}", 'blocker', "Table {$name} is missing", 'The stock service needs this table.');
                continue;
            }
            $add("engine_{$name}", $t['engine'] === 'InnoDB' ? 'info' : ($name === 'purchase_products' ? 'warning' : 'info'), "{$name} uses {$t['engine']}",
                $t['engine'] === 'InnoDB' ? 'Transactions and row locks work.' : ($name === 'purchase_products'
                    ? 'No transactions or row locks: a failed order cannot give batch units back. Conversion to InnoDB is proposed.'
                    : 'Left as it is; no change proposed.'));
        }

        if (isset($tables['purchase_products']) && $tables['purchase_products']['engine'] !== 'InnoDB') {
            $rowFormat = strtolower((string) ($tables['purchase_products']['row_format'] ?? ''));
            if (in_array($rowFormat, ['compact', 'redundant'], true) || ($isMaria && version_compare((string) $server['version'], '10.2', '<'))) {
                $add('key_length', 'warning', 'Older row format', 'Index columns are limited to 767 bytes. The proposed purchase_products index uses a 100 character prefix for that reason.');
            }
        }

        $zero = $this->zeroDates();
        if (array_sum($zero) > 0) {
            $add('zero_dates', 'blocker', 'Zero dates found', 'Converting a MyISAM table to InnoDB fails under strict SQL mode when a date column holds 0000-00-00.', $zero);
        } else {
            $add('zero_dates', 'info', 'No zero dates', 'Date columns can be converted.');
        }

        if ($schema['foreign_keys'] !== [] || $schema['triggers'] !== [] || $schema['views'] !== [] || $schema['routines'] !== []) {
            $add('dependents', 'warning', 'Foreign keys, triggers, views or routines exist', 'Check that none of them reads or writes the stock tables.', ['foreign_keys' => $schema['foreign_keys'], 'triggers' => $schema['triggers'], 'views' => $schema['views'], 'routines' => $schema['routines']]);
        } else {
            $add('dependents', 'info', 'No foreign keys, triggers, views or routines on the stock tables', 'Nothing else in the database depends on the engine of purchase_products.');
        }

        $dupes = $this->duplicateStockRows();
        $add('duplicate_stock_rows', $dupes ? 'blocker' : 'info', $dupes ? count($dupes) . ' duplicated (product, variation) pairs' : 'No duplicated (product, variation) pairs',
            $dupes ? 'A unique index cannot be created while these exist. Each pair needs a person to decide which quantity is right; nothing is merged automatically.' : 'The unique index can be created.', $dupes ?: null);

        $shapes = $this->variantShapes();
        foreach ($shapes['product_stocks'] as $shape) {
            if ($shape['shape'] === 'null') {
                $add('stock_null_variant', 'blocker', 'NULL variation values in product_stocks', 'A unique index treats every NULL as different, so it would not protect these rows.', $shape);
            } elseif ($shape['shape'] === 'padded') {
                $add('stock_padded_variant', 'warning', 'Variation values with leading or trailing spaces', 'Matching ignores trailing spaces but not leading ones, so lookups can miss these rows.', $shape);
            } elseif ($shape['shape'] === 'empty') {
                $add('stock_empty_variant', 'info', 'Empty-string variations (plain, non-variant products)', 'Stored as an empty string, never NULL. One per product is valid.', $shape);
            }
        }
        foreach ($shapes['purchase_products'] as $shape) {
            if ($shape['shape'] === 'null') {
                $add('batch_null_variant', 'warning', 'NULL variation values in purchase_products', 'The current routines use "variant IS NULL" for plain products and "variant = x" for variation products. Rows whose stock row uses an empty string will not match.', $shape);
            }
        }

        $integrity = $this->integrity();
        foreach (['negative_stock' => ['warning', 'Negative stock quantities'], 'negative_batches' => ['warning', 'Negative batch quantities'], 'batch_remaining_over_purchased' => ['warning', 'Batches holding more than was bought'],
            'stock_rows_without_product' => ['warning', 'Stock rows whose product no longer exists'], 'batches_without_purchase' => ['warning', 'Batches whose purchase record is missing'],
            'batches_non_numeric' => ['blocker', 'Batches with non-numeric quantity or cost']] as $key => [$level, $title]) {
            $n = count($integrity[$key]);
            $add($key, $n ? $level : 'info', $n ? "{$title}: {$n}" : "None: {$title}", $n ? 'Review these rows; they are not changed by the audit.' : 'Clean.', $n ? array_slice($integrity[$key], 0, 20) : null);
        }

        return $checks;
    }

    /** Highest level among the checks: blocker > warning > info. */
    public static function verdict(array $checks): string
    {
        $levels = array_column($checks, 'level');
        if (in_array('blocker', $levels, true)) {
            return 'blocked';
        }

        return in_array('warning', $levels, true) ? 'review' : 'ready';
    }

    // ------------------------------------------------------------------
    // helpers
    // ------------------------------------------------------------------

    /** Key used to match variation text the way the columns' collations do. */
    private function normalise(?string $variant): string
    {
        return mb_strtolower(rtrim((string) $variant), 'UTF-8');
    }

    private function databaseName(): string
    {
        return (string) $this->db->getDatabaseName();
    }

    private function marks(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }
}
