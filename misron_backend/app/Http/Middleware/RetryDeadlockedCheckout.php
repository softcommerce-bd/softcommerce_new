<?php

namespace App\Http\Middleware;

use App\Support\DeadlockRetry;
use App\Support\RequestSideEffects;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Runs the same request again (three attempts in total, after a short random pause) when the controller's database transaction was
 * killed as a deadlock victim, whether it surfaces as an exception or as the controller's own "failed: ...Deadlock found" answer.
 * Anything else, success and ordinary refusals included, is returned as is and never repeated.
 *
 * Safety rules (see App\Support\DeadlockRetry): the connection must be back at the level the request started at (a transaction the
 * controller left open is rolled back, logged as critical and NOT retried), only deadlocks are retried, and uploads/other one-off
 * effects are made once per request through RequestSideEffects and undone if the request finally fails.
 * Applied to routes listed in config/deadlock_retry.php, never to routes whose work already retries inside (Idempotency::once).
 */
class RetryDeadlockedCheckout
{
    public const MAX_ATTEMPTS = DeadlockRetry::MAX;

    public function handle($request, Closure $next)
    {
        $entry = DB::transactionLevel();
        for ($attempt = 1; ; $attempt++) {
            $request->attributes->set('deadlock_attempt', $attempt);
            $exception = null;
            $response = null;
            try {
                $response = $next($request);
            } catch (\Throwable $e) {
                if (!DeadlockRetry::isDeadlock($e)) {
                    throw $e;
                }
                $exception = $e;
            }
            $flashed = [];
            if ($exception === null && !$this->answerSaysDeadlock($response, $request, $flashed)) {
                return $attempt > 1 && method_exists($response, 'header') ? $response->header('X-Deadlock-Retries', (string) ($attempt - 1)) : $response;
            }
            // a deadlock happened. Is it safe to run again?
            if (DB::transactionLevel() > $entry) {
                Log::critical('A deadlocked request left a database transaction open; it was rolled back and NOT retried.', ['uri' => $request->path()]);
                DB::rollBack($entry);
                $this->finish($request, $exception);
                if ($exception) {
                    throw $exception;
                }

                return $response;
            }
            if ($attempt >= DeadlockRetry::MAX || $entry !== DeadlockRetry::$baseLevel) {
                Log::warning("Deadlock persisted after {$attempt} attempt(s); giving up.", ['uri' => $request->path()]);
                $this->finish($request, $exception);
                if ($exception) {
                    throw $exception;
                }

                return $response;
            }
            $this->forgetFlash($request, $flashed); // the failed attempt's "error: Deadlock found" message must not survive into the retried answer
            DeadlockRetry::pause();
        }
    }

    private function finish($request, ?\Throwable $e): void
    {
        RequestSideEffects::undoAll($request); // remove uploads created for attempts that never committed
    }

    /** @param string[] $flashed filled with the session flash keys that carried the deadlock message (redirect + flash answers) */
    private function answerSaysDeadlock($response, $request = null, array &$flashed = []): bool
    {
        // the framework turns an exception thrown in the controller into a response before it reaches this middleware and keeps it on the response
        $thrown = $response->exception ?? null;
        if ($thrown instanceof \Throwable && DeadlockRetry::isDeadlock($thrown)) {
            return true;
        }
        $body = method_exists($response, 'getContent') ? (string) $response->getContent() : '';
        if ($body !== '' && $this->mentionsDeadlock($body)) {
            return true;
        }

        // Many screens catch the exception and answer with a redirect plus a flashed message ("Error: ...Deadlock found..."). The redirect has no
        // body text, so look at what was flashed to the session during this attempt.
        if ($request && $request->hasSession() && $response instanceof \Symfony\Component\HttpFoundation\RedirectResponse) {
            $session = $request->session();
            foreach ((array) $session->get('_flash.new', []) as $key) {
                try {
                    $value = $session->get($key);
                    $text = is_string($value) ? $value : json_encode($value instanceof \Illuminate\Contracts\Support\Arrayable ? $value->toArray() : (is_object($value) && method_exists($value, 'getBags') ? array_map(fn ($b) => $b->all(), $value->getBags()) : $value));
                } catch (\Throwable $e) {
                    continue;
                }
                if (is_string($text) && $this->mentionsDeadlock($text)) {
                    $flashed[] = $key;
                }
            }

            return $flashed !== [];
        }

        return false;
    }

    private function mentionsDeadlock(string $text): bool
    {
        return str_contains($text, 'Deadlock found') || str_contains($text, 'SQLSTATE[40001]') || str_contains($text, 'Serialization failure') || str_contains($text, 'inventory_busy');
    }

    private function forgetFlash($request, array $keys): void
    {
        if ($keys === [] || !$request->hasSession()) {
            return;
        }
        $session = $request->session();
        $session->forget($keys);
        $session->put('_flash.new', array_values(array_diff((array) $session->get('_flash.new', []), $keys)));
    }
}
