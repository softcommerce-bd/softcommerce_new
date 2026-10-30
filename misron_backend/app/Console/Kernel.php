<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->call(function () {
            \Log::info('Laravel scheduler working: ' . now());
        })->everyMinute();

        $schedule->command('accounts:import-customer-payments')->hourly();

        $schedule->command('courier:sync-status')
            ->hourly()  // Changed to hourly as requested
            ->withoutOverlapping();

        // Scheduled publishing and unpublishing of sales funnel pages.
        $schedule->command('funnel:reconcile')
            ->everyMinute()
            ->withoutOverlapping();

        // Unpaid online-payment orders: settled with the gateway, or cancelled with their
        // stock returned once the gateway confirms no payment went through. Does nothing
        // until ONLINE_ORDER_EXPIRY_ENABLED=true.
        $schedule->command('orders:expire-unpaid')
            ->everyFiveMinutes()
            ->withoutOverlapping();

        // Old funnel analytics events (orders are never removed).
        $schedule->command('funnel:prune-events')
            ->dailyAt('03:30')
            ->withoutOverlapping();

        $schedule->command('queue:work --once')
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->command('accounts:auto-deposit')
            ->dailyAt('00:01')
            ->timezone('Asia/Dhaka')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
