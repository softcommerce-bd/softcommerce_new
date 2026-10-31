<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AccountingBaseline extends Command
{
    protected $signature = 'accounting:baseline {--by= : who took it}';
    protected $description = 'Record each account\'s current balance as the starting point for journal reconciliation. Run once, just before enabling the flag. Never overwrites an existing baseline and never changes a balance.';

    public function handle()
    {
        $n = 0;
        foreach (DB::table('chart_of_accounts')->get(['id', 'current_balance']) as $a) {
            $n += DB::table('ledger_baselines')->insertOrIgnore(['account_id' => $a->id, 'balance' => (float) $a->current_balance, 'taken_at' => now(), 'taken_by' => $this->option('by')]);
        }
        $this->info("{$n} new account baseline(s) recorded; existing baselines were left untouched.");

        return 0;
    }
}
