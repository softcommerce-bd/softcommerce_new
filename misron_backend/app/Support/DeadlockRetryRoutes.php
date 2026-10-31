<?php

namespace App\Support;

use Illuminate\Routing\Router;

/** Attaches the bounded deadlock retry to the state-changing routes listed in config/deadlock_retry.php (also works with cached routes). */
final class DeadlockRetryRoutes
{
    /** @return string[] "METHOD uri" of every route that received the middleware */
    public static function attach(Router $router): array
    {
        $map = config('deadlock_retry.controllers', []);
        $attached = [];
        foreach ($router->getRoutes()->getRoutes() as $route) {
            $methods = array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']);
            if (!$methods || !is_string($route->getActionName()) || !str_contains($route->getActionName(), '@')) {
                continue;
            }
            [$class, $method] = explode('@', $route->getActionName(), 2);
            $class = ltrim($class, '\\');
            foreach ($map as $controller => $only) {
                if (ltrim($controller, '\\') === $class && ($only === '*' || in_array($method, (array) $only, true))) {
                    $route->computedMiddleware = null; // forget the list computed when the routes were loaded
                    if (!in_array('retry.deadlock', $route->gatherMiddleware(), true)) {
                        $route->middleware('retry.deadlock');
                        $route->computedMiddleware = null;
                    }
                    $attached[] = implode('|', $methods) . ' ' . $route->uri();
                }
            }
        }

        return $attached;
    }
}
