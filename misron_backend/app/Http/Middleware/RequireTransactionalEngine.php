<?php

namespace App\Http\Middleware;

use App\Support\TransactionalEngines;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Refuses purchasing / supplier-balance requests while the purchasing tables are not InnoDB (policy 'enforce', the default), because their
 * transactions, row locks and retries cannot work there. Reads (GET/HEAD) always pass. See App\Support\TransactionalEngines.
 */
class RequireTransactionalEngine
{
    public function handle($request, Closure $next)
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }
        $policy = TransactionalEngines::policy();
        if ($policy === 'off') {
            return $next($request);
        }
        $bad = TransactionalEngines::offenders(TransactionalEngines::purchasing());
        if ($bad === []) {
            return $next($request);
        }
        if ($bad === null) { // unreadable: do not stop the shop, say so
            Log::warning('Engine gate: purchasing table engines could not be read; request allowed.', ['uri' => $request->path()]);

            return $next($request);
        }
        $list = implode(', ', array_map(fn ($t, $e) => "$t=$e", array_keys($bad), $bad));
        if ($policy === 'warn') {
            Log::warning("Engine gate (warn): purchasing tables not on InnoDB: $list", ['uri' => $request->path()]);
            $response = $next($request);

            return method_exists($response, 'header') ? $response->header('X-Engine-Warning', 'purchasing tables are not InnoDB') : $response;
        }
        Log::critical("Engine gate: request REFUSED, purchasing tables are not on InnoDB: $list", ['uri' => $request->path(), 'user' => optional($request->user())->id]);
        $message = 'This purchasing/supplier operation is paused: the database tables it changes (' . $list . ') do not support transactions yet. Nothing was saved. The administrator must run the InnoDB conversion (php artisan db:engine-preflight).';
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'engine_gate' => true, 'message' => $message], 503);
        }

        return redirect()->back()->with('error', $message)->setStatusCode(303);
    }
}
