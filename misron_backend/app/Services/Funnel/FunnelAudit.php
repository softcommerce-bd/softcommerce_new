<?php

namespace App\Services\Funnel;

use App\Models\Funnel;
use App\Models\FunnelAuditLog;
use App\Models\User;

/**
 * Writes the funnel audit trail. Autosaves are not logged; every change of
 * state (create, publish, rollback, delete, …) is.
 */
class FunnelAudit
{
    public function log(?Funnel $funnel, string $action, ?User $user, array $meta = []): void
    {
        FunnelAuditLog::create([
            'funnel_id' => $funnel ? $funnel->id : null,
            'user_id' => $user ? $user->id : null,
            'action' => $action,
            'meta' => $meta === [] ? null : $meta,
            'ip' => request() ? request()->ip() : null,
        ]);
    }
}
