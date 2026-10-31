<?php

namespace App\Http\Middleware;

use Closure;

/**
 * The accounts area used to be open to every staff login. Reading needs one of the read permissions, any change needs a write permission.
 * Super Admin passes through Gate::before. Narrow permissions that already guard a sub-screen (supplier payments, courier settlement)
 * keep working: holding them opens the area to that extent, and their controllers still apply their own checks.
 */
class AccountingAccess
{
    private const READ = ['view_financial_reports', 'manage_accounting', 'view_supplier_payments', 'make_supplier_payment', 'view_courier_settlements', 'settle_courier_orders'];
    private const WRITE = ['manage_accounting', 'make_supplier_payment', 'settle_courier_orders'];

    public function handle($request, Closure $next)
    {
        $user = $request->user();
        $needed = in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) ? self::READ : self::WRITE;
        abort_unless($user && $user->canAny($needed), 403, 'You do not have permission to use the accounts area.');

        return $next($request);
    }
}
