<?php

namespace App\Http\Middleware;

use Closure;

/**
 * The old order-placement code (storefront API, POS, funnel) runs its own database transaction and, when the database picks it as a
 * deadlock victim, rolls back completely and answers with an error. This middleware runs the same request again (at most 3 attempts in
 * total, after a short random pause) ONLY when the answer says it was a deadlock. Anything else, success included, is returned as is,
 * so a refused order (out of stock, validation) is never retried. Safe because the rolled-back attempt left nothing behind.
 */
class RetryDeadlockedCheckout
{
    public const MAX_ATTEMPTS = 3;

    public function handle($request, Closure $next)
    {
        $response = null;
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $response = $next($request);
            if (!$this->wasDeadlock($response) || $attempt === self::MAX_ATTEMPTS) {
                return $response;
            }
            usleep(random_int(40, 160) * 1000);
        }

        return $response;
    }

    private function wasDeadlock($response): bool
    {
        $body = method_exists($response, 'getContent') ? (string) $response->getContent() : '';
        if ($body === '') {
            return false;
        }

        return str_contains($body, 'Deadlock found') || str_contains($body, 'SQLSTATE[40001]') || str_contains($body, 'Serialization failure') || str_contains($body, '"inventory_busy"') || str_contains($body, 'inventory_busy');
    }
}
