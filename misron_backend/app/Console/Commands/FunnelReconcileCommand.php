<?php

namespace App\Console\Commands;

use App\Services\Funnel\FunnelService;
use Illuminate\Console\Command;

/**
 * Applies scheduled publishing and unpublishing to the stored status.
 *
 * Customers never depend on this command: the public read compares the
 * schedule with the clock itself. It exists so the admin list and the audit
 * trail say "Published" or "Unpublished" at the right time, and so the
 * storefront's cached page is refreshed. It runs every minute from the
 * scheduler (the host's cron job must call `php artisan schedule:run`).
 */
class FunnelReconcileCommand extends Command
{
    protected $signature = 'funnel:reconcile';

    protected $description = 'Publish scheduled sales funnels that are due and unpublish those past their end time';

    public function handle(FunnelService $funnels): int
    {
        $slugs = $funnels->reconcile();

        foreach ($slugs as $slug) {
            $this->line("updated: {$slug}");
        }
        $this->info(count($slugs) . ' funnel(s) updated.');

        return self::SUCCESS;
    }
}
