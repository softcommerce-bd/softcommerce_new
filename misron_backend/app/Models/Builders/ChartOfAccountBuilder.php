<?php

namespace App\Models\Builders;

use App\Services\Accounting\LegacyLedgerBridge;
use Illuminate\Database\Eloquent\Builder;

/** Reports balance changes made through query-builder increments/updates so the legacy-ledger bridge can journal them. */
class ChartOfAccountBuilder extends Builder
{
    public function increment($column, $amount = 1, array $extra = [])
    {
        return $this->bridged($column, (float) $amount, fn () => parent::increment($column, $amount, $extra));
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        return $this->bridged($column, -(float) $amount, fn () => parent::decrement($column, $amount, $extra));
    }

    public function update(array $values)
    {
        if (!array_key_exists('current_balance', $values) || !LegacyLedgerBridge::active()) {
            return parent::update($values);
        }
        $before = (clone $this)->toBase()->pluck('current_balance', 'id')->all();
        $n = parent::update($values);
        foreach ((clone $this)->toBase()->pluck('current_balance', 'id') as $id => $now) {
            LegacyLedgerBridge::record((int) $id, (float) $now - (float) ($before[$id] ?? $now));
        }

        return $n;
    }

    private function bridged(string $column, float $delta, callable $run)
    {
        if ($column !== 'current_balance' || !LegacyLedgerBridge::active()) {
            return $run();
        }
        $ids = (clone $this)->toBase()->pluck('id')->all();
        $n = $run();
        foreach ($ids as $id) {
            LegacyLedgerBridge::record((int) $id, $delta);
        }

        return $n;
    }
}
