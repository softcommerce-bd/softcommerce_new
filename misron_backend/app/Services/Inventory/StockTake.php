<?php

namespace App\Services\Inventory;

/**
 * The outcome of StockLedger::take(): what each line cost and which purchase
 * batches supplied it. Also remembers every batch unit consumed, so they can be
 * given back by hand when the order fails and the batches do not roll back.
 */
class StockTake
{
    private bool $batchesTransactional;

    /** @var array<string|int, array{cost: float, avg_cost: float, batches: array}> */
    private array $lines = [];

    /** @var array<int, int> batch id => units consumed */
    private array $consumed = [];

    public function __construct(bool $batchesTransactional)
    {
        $this->batchesTransactional = $batchesTransactional;
    }

    public function batchesTransactional(): bool
    {
        return $this->batchesTransactional;
    }

    /** Called by the ledger as each batch unit is taken. */
    public function recordConsumed(int $batchId, int $units): void
    {
        $this->consumed[$batchId] = ($this->consumed[$batchId] ?? 0) + $units;
    }

    public function setLine($key, array $line): void
    {
        $this->lines[$key] = $line;
    }

    /** @return array{cost: float, avg_cost: float, batches: array} */
    public function line($key): array
    {
        return $this->lines[$key];
    }

    public function has($key): bool
    {
        return isset($this->lines[$key]);
    }

    /** Total cost of goods across all lines. */
    public function totalCost(): float
    {
        return array_sum(array_column($this->lines, 'cost'));
    }

    /** @return array<int, int> */
    public function consumedBatches(): array
    {
        return $this->consumed;
    }

    /** Forgets the consumed batch units once they have been given back. */
    public function forgetConsumed(): void
    {
        $this->consumed = [];
    }

    /**
     * JSON for order_details.fifo_batch_info, so a later cancel returns the
     * units to the very batches they came from.
     */
    public function batchInfo($key): ?string
    {
        $batches = $this->lines[$key]['batches'] ?? [];
        if ($batches === []) {
            return null;
        }

        return json_encode(array_map(fn ($b) => ['id' => $b['purchase_product_id'], 'qty' => $b['qty'], 'cost' => $b['unit_price']], $batches));
    }
}
