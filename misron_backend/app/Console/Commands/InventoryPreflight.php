<?php

namespace App\Console\Commands;

use App\Services\Inventory\InventoryMode;
use Illuminate\Console\Command;

class InventoryPreflight extends Command
{
    protected $signature = 'inventory:preflight';
    protected $description = 'Is it safe to set INVENTORY_CONFIRMED_ONLY=true? Read-only.';

    public function handle()
    {
        InventoryMode::reset();
        $problems = InventoryMode::preflight();
        foreach ($problems as $p) {
            $this->error($p);
        }
        $this->line($problems ? 'NOT READY: do not enable INVENTORY_CONFIRMED_ONLY.' : 'Ready: tables are in place and InnoDB. Also run `php artisan inventory:reconcile` and review it first.');

        return $problems ? 1 : 0;
    }
}
