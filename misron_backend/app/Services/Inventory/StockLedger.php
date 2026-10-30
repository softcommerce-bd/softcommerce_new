<?php

namespace App\Services\Inventory;

use App\Models\OrderDetail;
use App\Services\Inventory\Exceptions\CostBatchesMissing;
use App\Services\Inventory\Exceptions\InvalidStockRequest;
use App\Services\Inventory\Exceptions\InventoryBusy;
use App\Services\Inventory\Exceptions\OutOfStock;
use App\Services\Inventory\Exceptions\VariantUnavailable;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

/**
 * The one place that moves sellable stock, for the storefront and for funnel
 * checkout alike.
 *
 * Two records move together:
 *   product_stocks.qty                      what can be sold, per product and variation
 *   purchase_products.remaining_qty         FIFO cost batches, oldest first
 *
 * How it stays correct under concurrent orders:
 *
 *  - Every decrease is ONE conditional statement, never "read, subtract in PHP,
 *    save": `UPDATE ... SET qty = qty - n WHERE ... AND qty >= n`. The database
 *    checks and subtracts in a single step, so two orders for the last unit cannot
 *    both succeed and nobody's update is overwritten. A statement that changes no
 *    row is the "not enough stock" answer.
 *  - Lines are processed in product-id / variation order, so two orders that
 *    contain the same products in different cart order lock rows in the same
 *    sequence and cannot deadlock each other.
 *  - Batches are taken the same way, oldest id first. When another order empties
 *    a batch between the read and the update, the conditional update changes
 *    nothing and the line re-reads and moves to the next batch.
 *
 * Transactions: take() must run inside the caller's transaction, and keeps its
 * own savepoint so a refused line leaves nothing half-taken. purchase_products
 * is MyISAM in the original schema and ignores rollbacks; in that case take()
 * puts its consumed batch units back by hand when it fails, and callers do the
 * same through release() if the order fails later. After the table is converted
 * to InnoDB the same code relies on the rollback instead.
 */
class StockLedger
{
    private static ?bool $fifoTransactional = null;

    public static function forgetEngineCache(): void
    {
        self::$fifoTransactional = null;
    }

    /** Do purchase batches roll back with a transaction? */
    public function fifoIsTransactional(): bool
    {
        $forced = config('inventory.fifo_transactional');
        if ($forced !== null) {
            return (bool) $forced;
        }

        if (self::$fifoTransactional === null) {
            $row = DB::selectOne('SELECT engine AS engine FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', ['purchase_products']);
            self::$fifoTransactional = $row !== null && strtoupper((string) $row->engine) === 'INNODB';
        }

        return self::$fifoTransactional;
    }

    // ------------------------------------------------------------------
    // Selling
    // ------------------------------------------------------------------

    /**
     * Takes stock for several lines at once.
     *
     * Each line: ['key' => any, 'product_id' => int, 'variant' => ?string,
     *             'quantity' => int, 'digital' => bool (optional)]
     *
     * Digital lines move nothing. The result holds, per key, the cost of goods
     * and the batches used, in the shape the order code stores.
     *
     * @throws OutOfStock           a stock row has fewer units than asked for
     * @throws VariantUnavailable   no stock row for that product and variation
     * @throws CostBatchesMissing   the units are on the shelf but no batch records their cost
     * @throws InventoryBusy        contention outlasted the retry limit
     * @throws InvalidStockRequest  a malformed line
     */
    public function take(array $lines, array $options = []): StockTake
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('StockLedger::take() must run inside a database transaction.');
        }

        $prepared = $this->prepare($lines);
        $take = new StockTake($this->fifoIsTransactional());

        // Confirmed-order-only model: placing an order deducts nothing and consumes no cost layer. The selling policy
        // decides whether the order may be placed; stock moves when the order is confirmed and dispatched
        // (DemandService). Callers that sell goods on the spot (POS) pass ['immediate' => true] and keep the old behaviour.
        if (InventoryMode::confirmedOnly()) {
            if (empty($options['immediate'])) {
                return $this->deferred($prepared, $take);
            }
            $this->assertFreePhysical($prepared); // an immediate sale must not take units earmarked for confirmed orders
        }

        try {
            // A savepoint: if any line is refused, the lines already taken inside
            // this call are undone even when the caller carries on with its transaction.
            DB::transaction(function () use ($prepared, $take) {
                foreach ($prepared as $line) {
                    $take->setLine($line['key'], $this->takeLine($line, $take));
                }
            });
        } catch (Throwable $e) {
            $this->release($take);
            throw $e;
        }

        return $take;
    }

    /** @param array<int, array> $prepared */
    private function deferred(array $prepared, StockTake $take): StockTake
    {
        $perItem = [];
        foreach ($prepared as $line) {
            if ($line['digital']) {
                $take->setLine($line['key'], ['cost' => 0.0, 'avg_cost' => 0.0, 'batches' => []]);
                continue;
            }
            $k = $line['product_id'] . '|' . $line['variant'];
            $perItem[$k] = ($perItem[$k] ?? 0) + $line['quantity']; // two lines of one item in one order count together
            StockPolicy::assertCanOrder($line['product_id'], $line['variant'], $perItem[$k]);
            $take->setLine($line['key'], ['cost' => 0.0, 'avg_cost' => 0.0, 'batches' => []]);
        }

        return $take;
    }

    /** @param array<int, array> $prepared */
    private function assertFreePhysical(array $prepared): void
    {
        foreach ($prepared as $line) {
            if ($line['digital']) {
                continue;
            }
            $b = StockBalance::lock($line['product_id'], $line['variant']);
            $free = StockBalance::onHandLocked($line['product_id'], $line['variant']) - (int) $b->allocated;
            if ($free < $line['quantity']) {
                throw new OutOfStock("Product {$line['product_id']} '{$line['variant']}': {$free} unreserved, {$line['quantity']} wanted.",
                    ['product_id' => $line['product_id'], 'variant' => $line['variant'], 'requested' => $line['quantity'], 'available' => max(0, $free)]);
            }
        }
    }

    /**
     * Gives consumed batch units back when the batches do not roll back by
     * themselves (MyISAM). A no-op once purchase_products is InnoDB, because the
     * transaction's rollback has already restored them. Safe to call twice.
     */
    public function release(StockTake $take): void
    {
        if ($take->batchesTransactional()) {
            return;
        }

        $now = $this->now();
        foreach ($take->consumedBatches() as $batchId => $units) {
            if ($units > 0) {
                DB::update('UPDATE purchase_products SET remaining_qty = remaining_qty + ?, updated_at = ? WHERE id = ?', [$units, $now, $batchId]);
            }
        }
        $take->forgetConsumed();
    }

    // ------------------------------------------------------------------
    // Cancelling
    // ------------------------------------------------------------------

    /**
     * Cancels one order line and puts its units back, exactly once.
     *
     * The status change and the restock are tied together: the line is flipped
     * to "cancelled" only if it was neither cancelled nor delivered, and units
     * are returned only if that flip changed a row. A second cancel, from an
     * admin click, a customer or a gateway retry, finds nothing to flip and
     * returns false.
     *
     * Units go back to the batches recorded in `fifo_batch_info` when present, or
     * to the most recent batches that have room otherwise (older lines).
     *
     * A delivered line is left alone, unless $includeDelivered says an admin is
     * deliberately moving a delivered order to cancelled (the goods come back).
     */
    public function give(OrderDetail $detail, bool $includeDelivered = false): bool
    {
        $skip = $includeDelivered ? "'cancelled'" : "'cancelled', 'delivered'";

        return (bool) DB::transaction(function () use ($detail, $skip) {
            $changed = DB::update(
                "UPDATE order_details SET delivery_status = 'cancelled', updated_at = ? WHERE id = ? AND LOWER(TRIM(COALESCE(delivery_status, ''))) NOT IN ({$skip})",
                [$this->now(), $detail->id]
            );
            if ($changed === 0) {
                return false;
            }
            $detail->delivery_status = 'cancelled';
            $detail->syncOriginalAttribute('delivery_status');

            // A line created under the confirmed-order model holds demand, not stock: cancelling releases whatever
            // has not shipped. Units that already left are untouched (they come back through a return receipt).
            if (InventoryMode::lineIsV2((int) $detail->id)) {
                app(DemandService::class)->release($detail);
                DB::update('UPDATE products SET num_of_sale = GREATEST(COALESCE(num_of_sale, 0) - ?, 0) WHERE id = ?', [(int) $detail->quantity, $detail->product_id]);

                return true;
            }

            $quantity = (int) $detail->quantity;
            if ($quantity < 1) {
                return true;
            }

            $product = DB::selectOne('SELECT digital AS digital FROM products WHERE id = ?', [$detail->product_id]);
            if ($product !== null && (int) $product->digital === 1) {
                return true;
            }

            $variant = $this->variantKey($detail->variation);
            DB::update(
                'UPDATE product_stocks SET qty = qty + ?, updated_at = ? WHERE product_id = ? AND variant = ? ORDER BY id LIMIT 1',
                [$quantity, $this->now(), $detail->product_id, $variant]
            );
            DB::update('UPDATE products SET num_of_sale = GREATEST(COALESCE(num_of_sale, 0) - ?, 0) WHERE id = ?', [$quantity, $detail->product_id]);

            $this->returnBatches($detail, $quantity, $variant);

            return true;
        });
    }

    // ------------------------------------------------------------------
    // Other writers
    // ------------------------------------------------------------------

    /**
     * Adds or removes units on a stock row in one statement. This is the safe
     * replacement for the "load the row, change qty in PHP, save" pattern that
     * purchases, returns, POS and the product form still use: that pattern writes
     * back an absolute number computed from an earlier read and so erases any
     * sale made in between. A removal that would take the row below zero changes
     * nothing and returns false.
     *
     * Moves product_stocks only; purchase batches are the caller's business.
     */
    public function adjust(int $productId, ?string $variant, int $delta): bool
    {
        if ($delta === 0) {
            return true;
        }

        $sql = 'UPDATE product_stocks SET qty = qty + ?, updated_at = ? WHERE product_id = ? AND variant = ?';
        $bind = [$delta, $this->now(), $productId, $this->variantKey($variant)];
        if ($delta < 0) {
            $sql .= ' AND qty + ? >= 0';
            $bind[] = $delta;
        }

        return DB::update($sql . ' ORDER BY id LIMIT 1', $bind) > 0;
    }

    // ------------------------------------------------------------------
    // Batch-only movements (for screens that move the shelf separately)
    // ------------------------------------------------------------------

    /**
     * Consumes purchase batches first-in-first-out without touching the stock row, for
     * the order edit screen, which moves the shelf with adjust() as it always did.
     * Same locking reads and conditional updates as take(). Must run in a transaction.
     *
     * @return array{total_cost: float, batches: array, take: StockTake} `take` lets the caller give the units
     *         back with release() if its own transaction fails later and the batches do not roll back
     * @throws CostBatchesMissing when the batches hold fewer units than asked for
     */
    public function consumeBatches(int $productId, ?string $variant, int $quantity): array
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('StockLedger::consumeBatches() must run inside a database transaction.');
        }
        if ($quantity < 1) {
            return ['total_cost' => 0.0, 'batches' => [], 'take' => new StockTake($this->fifoIsTransactional())];
        }

        $take = new StockTake($this->fifoIsTransactional());
        try {
            $line = $this->takeBatches([
                'product_id' => $productId,
                'variant' => $this->variantKey($variant),
                'quantity' => $quantity,
            ], $take);
        } catch (Throwable $e) {
            $this->release($take);
            throw $e;
        }

        return ['total_cost' => $line['cost'], 'batches' => $line['batches'], 'take' => $take];
    }

    /**
     * Puts units back into purchase batches without touching the stock row: first the
     * batches the line recorded, then the newest batches that have room. Never beyond what
     * was bought into a batch. Returns how many units found a batch.
     */
    public function returnBatchUnits(int $productId, ?string $variant, int $quantity, ?OrderDetail $detail = null): int
    {
        if ($quantity < 1) {
            return 0;
        }

        return DB::transaction(function () use ($productId, $variant, $quantity, $detail) {
            $probe = $detail ?: (new OrderDetail())->forceFill(['product_id' => $productId, 'quantity' => $quantity]);
            $probe->product_id = $probe->product_id ?: $productId;

            return $this->returnBatches($probe, $quantity, $this->variantKey($variant));
        });
    }

    /**
     * Like adjust(), for a stock row already known by id (purchases locate the row their own way).
     * A removal that would take the row below zero changes nothing and returns false.
     */
    public function adjustRow(int $stockRowId, int $delta): bool
    {
        if ($delta === 0) {
            return true;
        }

        $sql = 'UPDATE product_stocks SET qty = qty + ?, updated_at = ? WHERE id = ?';
        $bind = [$delta, $this->now(), $stockRowId];
        if ($delta < 0) {
            $sql .= ' AND qty + ? >= 0';
            $bind[] = $delta;
        }

        return DB::update($sql, $bind) > 0;
    }

    /**
     * Changes how much was bought into one purchase batch by $diff units (a purchase edited,
     * or part of it returned to the supplier): purchase_quantity and remaining_qty move by the
     * same amount in ONE statement, so a sale made in between is never erased. Returns false,
     * changing nothing, when fewer than zero units would remain (more than is left was sold).
     *
     * @param array<string, mixed> $set other columns to write in the same statement (cost, name, ...)
     */
    public function resizeBatch(int $batchId, int $diff, array $set = []): bool
    {
        $assign = ['purchase_quantity = CAST(CAST(purchase_quantity AS SIGNED) + ? AS CHAR)', 'remaining_qty = remaining_qty + ?', 'updated_at = ?'];
        $bind = [$diff, $diff, $this->now()];
        foreach ($set as $column => $value) {
            if (!preg_match('/^[a-z_]+$/', $column)) {
                throw new LogicException("Bad column name {$column}.");
            }
            $assign[] = "{$column} = ?";
            $bind[] = $value;
        }
        $bind[] = $batchId;
        $bind[] = $diff;

        $changed = DB::update('UPDATE purchase_products SET ' . implode(', ', $assign) . ' WHERE id = ? AND remaining_qty + ? >= 0', $bind);

        return $changed > 0;
    }

    // ------------------------------------------------------------------
    // Reading
    // ------------------------------------------------------------------

    /**
     * What a checkout can really sell: the smaller of the stock row and the
     * cost batches. `stock` is what the storefront shows.
     *
     * @return array{stock: int, batches: int, sellable: int, has_stock_row: bool}
     */
    public function sellable(int $productId, ?string $variant): array
    {
        $variant = $this->variantKey($variant);
        $stock = DB::selectOne('SELECT qty AS qty FROM product_stocks WHERE product_id = ? AND variant = ? ORDER BY id LIMIT 1', [$productId, $variant]);
        [$clause, $bind] = $this->batchMatch($variant);
        $batches = DB::selectOne("SELECT COALESCE(SUM(remaining_qty), 0) AS remaining FROM purchase_products WHERE product_id = ? AND {$clause}", array_merge([$productId], $bind));

        $stockQty = $stock ? (int) $stock->qty : 0;
        $batchQty = (int) $batches->remaining;

        return ['stock' => $stockQty, 'batches' => $batchQty, 'sellable' => max(0, min($stockQty, $batchQty)), 'has_stock_row' => $stock !== null];
    }

    // ------------------------------------------------------------------
    // internals
    // ------------------------------------------------------------------

    /** @return array<int, array{key: mixed, product_id: int, variant: string, quantity: int, digital: bool}> */
    private function prepare(array $lines): array
    {
        $limit = (int) config('inventory.quantity_limit', 10000);
        $prepared = [];

        foreach (array_values($lines) as $index => $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $quantity = $line['quantity'] ?? 0;
            if ($productId < 1 || !is_numeric($quantity) || (int) $quantity != $quantity || (int) $quantity < 1 || (int) $quantity > $limit) {
                throw new InvalidStockRequest('Each line needs a product and a whole quantity between 1 and ' . $limit . '.', ['line' => $index, 'product_id' => $productId, 'quantity' => $quantity]);
            }

            $prepared[] = [
                'key' => array_key_exists('key', $line) ? $line['key'] : $index,
                'product_id' => $productId,
                'variant' => $this->variantKey($line['variant'] ?? null),
                'quantity' => (int) $quantity,
                'digital' => !empty($line['digital']),
                '_order' => $index,
            ];
        }

        // One global order for every caller: this is what rules out deadlocks.
        usort($prepared, fn ($a, $b) => [$a['product_id'], $a['variant'], $a['_order']] <=> [$b['product_id'], $b['variant'], $b['_order']]);

        return $prepared;
    }

    /** @return array{cost: float, avg_cost: float, batches: array} */
    private function takeLine(array $line, StockTake $take): array
    {
        if ($line['digital']) {
            return ['cost' => 0.0, 'avg_cost' => 0.0, 'batches' => []];
        }

        $taken = DB::update(
            'UPDATE product_stocks SET qty = qty - ?, updated_at = ? WHERE product_id = ? AND variant = ? AND qty >= ? ORDER BY id LIMIT 1',
            [$line['quantity'], $this->now(), $line['product_id'], $line['variant'], $line['quantity']]
        );

        if ($taken === 0) {
            // A shared lock makes this a current read, so "available" is what is there now,
            // not what the transaction's snapshot showed when it began.
            $row = DB::selectOne('SELECT qty AS qty FROM product_stocks WHERE product_id = ? AND variant = ? ORDER BY id LIMIT 1 LOCK IN SHARE MODE', [$line['product_id'], $line['variant']]);
            $context = ['product_id' => $line['product_id'], 'variant' => $line['variant'], 'requested' => $line['quantity']];
            if ($row === null) {
                throw new VariantUnavailable("No stock row for product {$line['product_id']} variation '{$line['variant']}'.", $context);
            }
            throw new OutOfStock("Product {$line['product_id']} '{$line['variant']}': {$row->qty} left, {$line['quantity']} wanted.", $context + ['available' => max(0, (int) $row->qty)]);
        }

        return $this->takeBatches($line, $take);
    }

    /** @return array{cost: float, avg_cost: float, batches: array} */
    private function takeBatches(array $line, StockTake $take): array
    {
        [$clause, $bind] = $this->batchMatch($line['variant']);
        $remaining = $line['quantity'];
        $cost = 0.0;
        $used = [];
        $suppliers = [];
        $attempts = 0;
        $maxAttempts = (int) config('inventory.max_batch_attempts', 60);

        while ($remaining > 0) {
            if (++$attempts > $maxAttempts + $line['quantity']) {
                throw new InventoryBusy("Purchase batches for product {$line['product_id']} kept changing.", ['product_id' => $line['product_id'], 'variant' => $line['variant']]);
            }

            // FOR UPDATE makes this a current read. A plain SELECT inside a
            // REPEATABLE READ transaction keeps showing the batch as it was when the
            // transaction's snapshot was taken, so after another order emptied it the
            // retry below would see the same stale row forever. It also holds the row
            // until commit, so the update that follows cannot lose a race.
            $batch = DB::selectOne(
                "SELECT id, purchase_id, remaining_qty, buying_unit_cost FROM purchase_products WHERE product_id = ? AND remaining_qty > 0 AND {$clause} ORDER BY id ASC LIMIT 1 FOR UPDATE",
                array_merge([$line['product_id']], $bind)
            );

            if ($batch === null) {
                throw new CostBatchesMissing(
                    "Product {$line['product_id']} '{$line['variant']}' has stock but its purchase batches are short by {$remaining}.",
                    ['product_id' => $line['product_id'], 'variant' => $line['variant'], 'requested' => $line['quantity'], 'missing' => $remaining]
                );
            }

            $units = min((int) $batch->remaining_qty, $remaining);
            $changed = DB::update(
                'UPDATE purchase_products SET remaining_qty = remaining_qty - ?, updated_at = ? WHERE id = ? AND remaining_qty >= ?',
                [$units, $this->now(), $batch->id, $units]
            );
            if ($changed === 0) {
                continue; // another order took from this batch first: read again
            }

            $take->recordConsumed((int) $batch->id, $units);

            $unitCost = (float) $batch->buying_unit_cost;
            $lineCost = $units * $unitCost;
            $cost += $lineCost;

            $purchaseId = $batch->purchase_id;
            if (!array_key_exists((int) $purchaseId, $suppliers)) {
                $purchase = $purchaseId ? DB::selectOne('SELECT supplier_id AS supplier_id FROM purchases WHERE id = ?', [$purchaseId]) : null;
                $suppliers[(int) $purchaseId] = $purchase ? $purchase->supplier_id : null;
            }

            $used[] = [
                'purchase_product_id' => (int) $batch->id,
                'purchase_id' => $purchaseId,
                'supplier_id' => $suppliers[(int) $purchaseId],
                'qty' => $units,
                'unit_price' => $batch->buying_unit_cost,
                'total_price' => $lineCost,
            ];
            $remaining -= $units;
        }

        return ['cost' => $cost, 'avg_cost' => $cost / $line['quantity'], 'batches' => $used];
    }

    /** @return int units placed in a batch */
    private function returnBatches(OrderDetail $detail, int $quantity, string $variant): int
    {
        $now = $this->now();
        $left = $quantity;

        $recorded = $detail->fifo_batch_info ? json_decode($detail->fifo_batch_info, true) : null;
        if (is_array($recorded)) {
            foreach ($recorded as $entry) {
                $units = min((int) ($entry['qty'] ?? 0), $left);
                if (empty($entry['id']) || $units < 1) {
                    continue;
                }
                $left -= $this->giveBackToBatch((int) $entry['id'], $units, $now);
            }

            // The record can fall short of the line (an order edited after it was placed, or a
            // batch already full): the rest goes where older lines go, below.
            if ($left < 1) {
                return $quantity;
            }
        }

        // Lines with no record: refill the most recent batches that have room,
        // the way the Paystation cancel path always has.
        [$clause, $bind] = $this->batchMatch($variant);
        $batches = DB::select(
            "SELECT id FROM purchase_products WHERE product_id = ? AND {$clause} AND remaining_qty < CAST(purchase_quantity AS SIGNED) ORDER BY id DESC FOR UPDATE",
            array_merge([$detail->product_id], $bind)
        );
        foreach ($batches as $batch) {
            if ($left < 1) {
                break;
            }
            $left -= $this->giveBackToBatch((int) $batch->id, $left, $now);
        }

        return $quantity - max(0, $left);
    }

    /** Returns up to $units to a batch, never beyond what was bought into it. Gives back what it placed. */
    private function giveBackToBatch(int $batchId, int $units, string $now): int
    {
        // Current read with a lock, for the same reason as in takeBatches().
        $row = DB::selectOne('SELECT remaining_qty AS remaining, CAST(purchase_quantity AS SIGNED) AS bought FROM purchase_products WHERE id = ? FOR UPDATE', [$batchId]);
        if ($row === null) {
            return 0;
        }
        $room = (int) $row->bought - (int) $row->remaining;
        $place = min($units, max(0, $room));
        if ($place < 1) {
            return 0;
        }

        $changed = DB::update(
            'UPDATE purchase_products SET remaining_qty = remaining_qty + ?, updated_at = ? WHERE id = ? AND remaining_qty + ? <= CAST(purchase_quantity AS SIGNED)',
            [$place, $now, $batchId, $place]
        );

        return $changed ? $place : 0;
    }

    /** Stock rows store "" for a plain product; carts may carry NULL. */
    private function variantKey($variant): string
    {
        return $variant === null ? '' : (string) $variant;
    }

    /**
     * Batches record a plain product's variation as NULL or "": match both.
     *
     * @return array{0: string, 1: array}
     */
    private function batchMatch(string $variant): array
    {
        return $variant === ''
            ? ["(variant IS NULL OR variant = '')", []]
            : ['variant = ?', [$variant]];
    }

    private function now(): string
    {
        return now()->format('Y-m-d H:i:s');
    }
}
