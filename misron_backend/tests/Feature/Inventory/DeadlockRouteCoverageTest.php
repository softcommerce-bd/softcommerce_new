<?php

namespace Tests\Feature\Inventory;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** T-5: the routes named in config/deadlock_retry.php carry the retry middleware; the deliberately excluded ones do not. */
class DeadlockRouteCoverageTest extends TestCase
{
    public function test_every_configured_write_route_carries_the_middleware()
    {
        $checked = 0;
        foreach (config('deadlock_retry.controllers') as $controller => $methods) {
            foreach (Route::getRoutes() as $route) {
                if (!str_starts_with($route->getActionName(), $controller . '@') || $route->methods() === ['GET', 'HEAD']) {
                    continue;
                }
                $method = substr($route->getActionName(), strlen($controller) + 1);
                if ($methods !== '*' && !in_array($method, $methods, true)) {
                    continue;
                }
                $checked++;
                $this->assertTrue(in_array('retry.deadlock', $route->gatherMiddleware(), true) || in_array(\App\Http\Middleware\RetryDeadlockedCheckout::class, $route->gatherMiddleware(), true), "{$controller}@{$method} [" . $route->uri() . '] has no deadlock retry');
            }
        }
        $this->assertGreaterThan(30, $checked, 'the configured set is not empty');
    }

    public function test_excluded_routes_do_not_carry_it()
    {
        foreach ([[\App\Http\Controllers\PurchasingController::class, null], [\App\Http\Controllers\PurchaseOrderController::class, 'convert'], [\App\Http\Controllers\CustomerPaymentController::class, 'autoImport']] as [$controller, $method]) {
            foreach (Route::getRoutes() as $route) {
                $action = $route->getActionName();
                if ($method === null ? str_starts_with($action, $controller . '@') : $action === "{$controller}@{$method}") {
                    $this->assertFalse(in_array('retry.deadlock', $route->gatherMiddleware(), true), "$action must not be double-wrapped");
                }
            }
        }
        $this->addToAssertionCount(1);
    }
}
