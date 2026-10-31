<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Side effects that must happen at most once per HTTP request even when the request is run again after a deadlock.
 * once(): create something (a stored upload) the first time and hand back the same result on every later attempt;
 * the optional $undo runs only if the request finally fails because of the deadlock (see RetryDeadlockedCheckout), so no orphan file is left.
 */
final class RequestSideEffects
{
    public static function once(Request $request, string $key, callable $do, ?callable $undo = null)
    {
        $store = $request->attributes->get('side_effects', []);
        if (!array_key_exists($key, $store)) {
            $store[$key] = ['value' => $do(), 'undo' => $undo];
            $request->attributes->set('side_effects', $store);
        }

        return $store[$key]['value'];
    }

    /** Called by the middleware when the request ends as a failed deadlock: undo what was created for the attempts. */
    public static function undoAll(Request $request): void
    {
        foreach ($request->attributes->get('side_effects', []) as $effect) {
            if (is_callable($effect['undo'] ?? null)) {
                try {
                    ($effect['undo'])($effect['value']);
                } catch (\Throwable $e) {
                    \Log::warning('Could not undo a request side effect: ' . $e->getMessage());
                }
            }
        }
        $request->attributes->set('side_effects', []);
    }
}
