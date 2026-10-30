<?php

namespace App\Console\Commands;

use App\Services\Inventory\InventoryMode;
use App\Services\Inventory\OpeningBalanceService;
use Illuminate\Console\Command;

class InventoryOpeningCost extends Command
{
    protected $signature = 'inventory:opening-cost
        {action : list | template | check | apply}
        {--file= : CSV (template filled in by a person)}
        {--out= : where "template" writes the CSV (default: stdout)}
        {--approved-by= : name of the person who approved the costs (required for apply)}
        {--commit : actually write; without it "apply" is a dry run}';
    protected $description = 'Give a cost to legacy stock that has none. Never invents costs; dry run unless --commit; never changes physical stock.';

    public function handle(OpeningBalanceService $svc)
    {
        $action = $this->argument('action');
        if ($action === 'list') {
            $rows = $svc->uncostedUnits();
            foreach ($rows as $r) {
                $this->line("{$r['product_id']} [{$r['variant']}] {$r['name']}: on hand {$r['on_hand']}, costed {$r['layered']}, UNCOSTED {$r['uncosted']}");
            }
            $this->info(count($rows) . ' product/variation(s) with uncosted units.');

            return 0;
        }
        if ($action === 'template') {
            $csv = $svc->template();
            $this->option('out') ? file_put_contents($this->option('out'), $csv) : $this->getOutput()->write($csv);

            return 0;
        }
        if (!in_array($action, ['check', 'apply'], true)) {
            $this->error('Unknown action.');

            return 2;
        }
        $file = $this->option('file');
        if (!$file || !is_file($file)) {
            $this->error('--file is required and must exist.');

            return 2;
        }
        $csv = file_get_contents($file);
        try {
            $plan = $svc->plan($csv);
            foreach ($plan['errors'] as $e) {
                $this->error($e);
            }
            $this->line(count($plan['rows']) . ' row(s) would be costed, total ' . $plan['total']);
            if ($action === 'check' || !$this->option('commit')) {
                $this->info($plan['errors'] ? 'File has errors.' : ($action === 'apply' ? 'DRY RUN: nothing written. Re-run with --commit --approved-by=NAME.' : 'File is valid.'));

                return $plan['errors'] ? 1 : 0;
            }
            if (!$this->option('approved-by')) {
                $this->error('--approved-by is required with --commit.');

                return 2;
            }
            if (!InventoryMode::installed()) {
                $this->error('The inventory v2 tables are not installed. Nothing written.');

                return 2;
            }
            $out = $svc->apply($csv, $this->option('approved-by'));
            $this->info(($out['replayed'] ?? false) ? 'This exact file was already applied; nothing posted again.' : "Applied: {$out['rows']} layer(s), value {$out['value']}, journal entry {$out['journal_entry_id']}.");

            return 0;
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }
}
