<?php

namespace App\Services\Funnel;

use App\Models\Funnel;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Hands an admin's session over to the visual editor, which runs in the
 * Next.js app on another origin.
 *
 * The admin clicks Edit in the Laravel dashboard and is redirected to the
 * editor with a one-time code that lives for a minute. The editor trades the
 * code for a Sanctum token limited to the funnel editor API. The token itself
 * never appears in a URL, and it stops working after token_ttl_minutes.
 */
class EditorSession
{
    public const TOKEN_NAME = 'funnel-editor';

    public function issueHandoff(User $user, Funnel $funnel): string
    {
        $code = Str::random(48);

        Cache::put($this->cacheKey($code), [
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
        ], now()->addSeconds((int) config('funnel.editor_session.handoff_ttl_seconds')));

        return $code;
    }

    public function editorUrl(Funnel $funnel, string $code): string
    {
        return config('funnel.editor_url') . '/builder/funnels/' . $funnel->id . '?handoff=' . urlencode($code);
    }

    /**
     * @return array{token: string, expires_at: \Illuminate\Support\Carbon, user: User, funnel_id: int}|null
     */
    public function exchange(string $code): ?array
    {
        if ($code === '' || strlen($code) > 128) {
            return null;
        }

        // pull() removes the entry, so a code works exactly once.
        $handoff = Cache::pull($this->cacheKey($code));
        if (!is_array($handoff)) {
            return null;
        }

        $user = User::find($handoff['user_id']);
        if (!$user || !self::isEditorUser($user)) {
            return null;
        }

        $this->revokeExpired($user);

        $token = $user->createToken(self::TOKEN_NAME, [config('funnel.editor_session.token_ability')]);

        return [
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->created_at->copy()->addMinutes($this->ttlMinutes()),
            'user' => $user,
            'funnel_id' => (int) $handoff['funnel_id'],
        ];
    }

    public function isFresh(PersonalAccessToken $token): bool
    {
        return $token->created_at !== null
            && $token->created_at->copy()->addMinutes($this->ttlMinutes())->isFuture();
    }

    public static function isEditorUser(User $user): bool
    {
        return in_array($user->user_type, ['admin', 'staff'], true) && (int) $user->banned === 0;
    }

    private function revokeExpired(User $user): void
    {
        $user->tokens()
            ->where('name', self::TOKEN_NAME)
            ->where('created_at', '<', now()->subMinutes($this->ttlMinutes()))
            ->delete();
    }

    private function ttlMinutes(): int
    {
        return (int) config('funnel.editor_session.token_ttl_minutes');
    }

    private function cacheKey(string $code): string
    {
        return 'funnel_editor_handoff:' . hash('sha256', $code);
    }
}
