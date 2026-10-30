<?php

namespace App\Console\Commands;

use App\Services\Funnel\Analytics\EventRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;

/**
 * Removes funnel analytics events older than funnel.analytics.event_retention_days.
 * Only visitor activity is removed; orders, revenue and attribution stay.
 */
class FunnelPruneEventsCommand extends Command
{
    protected $signature = 'funnel:prune-events';

    protected $description = 'Delete funnel analytics events older than the retention period';

    public function handle(): int
    {
        if (!EventRecorder::ready()) {
            $this->info('No funnel_events table; nothing to prune.');

            return self::SUCCESS;
        }

        $days = max(30, (int) config('funnel.analytics.event_retention_days', 400));
        $before = now()->subDays($days);
        $deleted = 0;
        do {
            $batch = DB::table('funnel_events')->where('created_at', '<', $before)->limit(5000)->delete();
            $deleted += $batch;
        } while ($batch === 5000);

        $this->info("{$deleted} event(s) older than {$days} days removed.");

        return self::SUCCESS;
    }
}
