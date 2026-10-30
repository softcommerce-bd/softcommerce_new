<?php

namespace App\Console\Commands;

use App\Services\Funnel\Templates\TemplateLibrary;
use Illuminate\Console\Command;

/**
 * Installs or refreshes the built-in sales funnel templates.
 */
class FunnelTemplatesCommand extends Command
{
    protected $signature = 'funnel:templates';

    protected $description = 'Install or refresh the built-in sales funnel templates (Bengali and English)';

    public function handle(TemplateLibrary $library): int
    {
        $result = $library->sync();

        $this->info("Templates installed: {$result['created']} created, {$result['updated']} updated.");

        return self::SUCCESS;
    }
}
