<?php

namespace App\Http\Middleware;

use App\Services\Funnel\EditorSession;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Gate for the funnel editor API, run after auth:sanctum.
 *
 * Only admin and staff accounts get through, and a bearer token must be an
 * unexpired editor token: a customer token from the storefront login carries
 * the "*" ability and would otherwise pass a plain ability check. A request
 * authenticated by the admin's own Laravel session is accepted as is.
 * Per-action permissions are checked in the controller.
 */
class FunnelEditorAccess
{
    private $session;

    public function __construct(EditorSession $session)
    {
        $this->session = $session;
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !EditorSession::isEditorUser($user)) {
            return $this->deny('forbidden', 'You do not have access to the funnel editor.', 403);
        }

        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            if ($token->name !== EditorSession::TOKEN_NAME || !$token->can(config('funnel.editor_session.token_ability'))) {
                return $this->deny('forbidden', 'This sign-in cannot be used for the funnel editor.', 403);
            }
            if (!$this->session->isFresh($token)) {
                $token->delete();
                return $this->deny('session_expired', 'Your editor session has expired. Open the editor again from the admin panel.', 401);
            }
        }

        return $next($request);
    }

    private function deny(string $code, string $message, int $status)
    {
        return response()->json(['result' => false, 'code' => $code, 'message' => $message], $status);
    }
}
