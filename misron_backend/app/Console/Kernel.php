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

        // DISABLED (T-5 Phase 0/C): `accounts:import-customer-payments` has never existed in this repository (it appears only in the first import commit's
        // Kernel; `git log -S` finds no command class). Scheduling an undefined command makes `schedule:run` fail every hour. Its intended business
        // behaviour cannot be established from code or history (the manual screen CustomerPaymentController::autoImport is a different, unscheduled path
        // whose redesign is business decision 10). Re-enable only after that decision defines what the job must do. See docs/T5_PHASE0_BUSINESS_DECISIONS.md.
        // $schedule->command('accounts:import-customer-payments')->hourly();
        // crash recovery for the legacy-ledger outbox: completes any committed posting whose journal entry is still missing (no-op while the table is empty)
        $schedule->command('ledger:process-outbox --alert')->everyMinute()->withoutOverlapping(5);

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

        // Unified Chat: retry stuck webhook events, auto-close idle chats, retention.
        $schedule->command('chat:maintain')->everyFiveMinutes()->withoutOverlapping();

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
