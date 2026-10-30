<?php

namespace App\Console\Commands;

use App\Services\Inventory\InventoryMode;
use App\Services\Inventory\InventoryReconciliation;
use Illuminate\Console\Command;

class InventoryReconcile extends Command
{
    protected $signature = 'inventory:reconcile {--json : machine-readable output}';
    protected $description = 'READ-ONLY stock, cost-layer, supplier-payable and journal reconciliation report. Changes nothing.';

    public function handle(InventoryReconciliation $svc)
    {
        $report = $svc->report();
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
        } else {
            foreach ($report as $row) {
                $this->line(sprintf('[%-8s] %s: %s', strtoupper($row['severity']), $row['label'], $row['count']));
                foreach ($row['samples'] as $s) {
                    $this->line('           - ' . $s);
                }
            }
        }

        return collect($report)->contains(fn ($r) => $r['severity'] === 'critical') ? 1 : 0;
    }
}
