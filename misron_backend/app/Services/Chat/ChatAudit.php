<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\DB;

class ChatAudit
{
    public static function log(?int $userId, string $action, ?string $subjectType = null, ?int $subjectId = null, array $context = []): void
    {
        // Credentials must never reach the audit trail.
        unset($context['access_token'], $context['credentials'], $context['secret']);

        DB::table('chat_audit_logs')->insert([
            'user_id' => $userId, 'action' => $action, 'subject_type' => $subjectType, 'subject_id' => $subjectId,
            'context' => $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
            'ip' => request()->ip(), 'created_at' => now(),
        ]);
    }
}
