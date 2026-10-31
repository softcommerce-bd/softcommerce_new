<?php

namespace App\Console\Commands;

use App\Services\Accounting\LedgerOutboxProcessor;
use App\Services\Inventory\InventoryMode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LedgerProcessOutbox extends Command
{
    protected $signature = 'ledger:process-outbox {--limit=200 : groups per run} {--status : only show what is waiting} {--alert : exit code 2 and a critical log entry if anything has waited more than 5 minutes (for monitoring)}';
    protected $description = 'Completes legacy postings whose journal entry was not written yet (crash recovery). Idempotent: running it twice posts nothing twice.';

    public function handle(LedgerOutboxProcessor $p)
    {
        if (!Schema::hasTable('ledger_outbox')) {
            $this->line('The ledger outbox is not installed; nothing to do.');

            return 0;
        }
        $waiting = DB::table('ledger_outbox')->whereNull('processed_at');
        $this->line('Waiting: ' . (clone $waiting)->distinct()->count('token') . ' group(s), ' . (clone $waiting)->count() . ' row(s); oldest: ' . ((clone $waiting)->min('created_at') ?? '-'));
        $old = (clone $waiting)->where('created_at', '<', now()->subMinutes(5))->distinct()->count('token');
        if ($old && $this->option('alert')) {
            \Illuminate\Support\Facades\Log::critical("Ledger outbox: {$old} committed posting group(s) have waited more than 5 minutes for their journal entry.");
            $this->error("ALERT: {$old} group(s) waiting more than 5 minutes.");
        }
        if ($this->option('status')) {
            return ($old && $this->option('alert')) ? 2 : 0;
        }
        if (!InventoryMode::confirmedOnly()) {
            $this->line('The flag is off: rows are only written while it is on. Processing what is already queued.');
        }
        $r = $p->processPending((int) $this->option('limit'));
        $this->info("Processed {$r['processed']} group(s), {$r['failed']} failed.");

        $stillOld = DB::table('ledger_outbox')->whereNull('processed_at')->where('created_at', '<', now()->subMinutes(5))->distinct()->count('token');
        if ($stillOld && $this->option('alert')) {
            \Illuminate\Support\Facades\Log::critical("Ledger outbox: {$stillOld} group(s) are still unprocessed after a run and older than 5 minutes.");
            $this->error("ALERT: {$stillOld} group(s) still waiting more than 5 minutes after this run.");

            return 2;
        }

        return $r['failed'] ? 1 : 0;
    }
}
