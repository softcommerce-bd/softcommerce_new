<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** T-5 Phase C: every command the scheduler runs must exist (the hourly accounts:import-customer-payments never did). */
class SchedulerCommandsExistTest extends TestCase
{
    public function test_every_scheduled_command_is_registered()
    {
        $registered = array_keys(Artisan::all());
        $checked = 0;
        foreach (app(Schedule::class)->events() as $event) {
            if (empty($event->command)) {
                continue; // closure events
            }
            $this->assertMatchesRegularExpression("/artisan'?\s+'?([A-Za-z0-9:_-]+)/", $event->command);
            preg_match("/artisan'?\s+'?([A-Za-z0-9:_-]+)/", $event->command, $m);
            $this->assertContains($m[1], $registered, "the scheduler runs `{$m[1]}`, which is not a registered command");
            $checked++;
        }
        $this->assertGreaterThan(5, $checked);
    }
}
