<?php

namespace Tests\Feature\Inventory;

use Tests\TestCase;

/**
 * The data tools refuse everything they were not explicitly pointed at. None of these tests changes data.
 */
class InventoryToolsTest extends TestCase
{
    public function test_reset_needs_a_database_name(): void
    {
        $this->artisan('inventory:reset-dummy-data')->expectsOutputToContain('Say which database')->assertExitCode(1);
    }

    public function test_reset_refuses_a_database_that_is_not_a_test_database(): void
    {
        $this->artisan('inventory:reset-dummy-data', ['--database' => 'misron', '--execute' => true, '--confirm' => 'misron'])
            ->expectsOutputToContain('not a test database')->assertExitCode(1);
    }

    public function test_reset_rejects_an_unknown_scope(): void
    {
        $this->artisan('inventory:reset-dummy-data', ['--database' => 'misron_test', '--scope' => 'everything'])->assertExitCode(1);
    }

    public function test_reset_without_execute_changes_nothing(): void
    {
        $this->artisan('inventory:reset-dummy-data', ['--database' => 'misron_test'])
            ->expectsOutputToContain('Dry run: nothing was changed')->assertExitCode(0);
    }

    public function test_reset_needs_the_database_name_repeated_to_delete(): void
    {
        $this->artisan('inventory:reset-dummy-data', ['--database' => 'misron_test', '--execute' => true, '--confirm' => 'wrong'])
            ->expectsOutputToContain('repeat the database name')->assertExitCode(1);
    }

    public function test_sample_data_refuses_a_database_that_is_not_a_test_database(): void
    {
        $this->artisan('inventory:sample-data', ['--database' => 'misron'])->expectsOutputToContain('must end in _test')->assertExitCode(1);
        $this->artisan('inventory:sample-data')->assertExitCode(1);
    }
}
