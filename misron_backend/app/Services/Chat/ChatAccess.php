<?php

namespace App\Services\Chat;

use App\Models\ChatAccount;
use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The single authorization point for Unified Chat. Every controller, broadcast
 * channel and job asks this class; the front end's permission checks are only UX.
 *
 * A staff member sees a conversation only when ALL of these hold:
 *  1. the role grants view_unified_chat (Super Admin passes through Gate::before);
 *  2. the connected account is one they were granted (Super Admin: every account);
 *  3. view_all_chats, or view_assigned_chats and the conversation is assigned to them.
 */
class ChatAccess
{
    public function isSuper(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function can(User $user, string $permission): bool
    {
        return $user->can($permission);
    }

    /** @return int[] connected-account ids the user may work with */
    public function accountIds(User $user): array
    {
        if (!$this->can($user, 'view_unified_chat')) {
            return [];
        }
        if ($this->isSuper($user)) {
            return ChatAccount::pluck('id')->all();
        }

        return ChatAccount::whereHas('staff', fn ($q) => $q->where('users.id', $user->id))->pluck('id')->all();
    }

    public function canUseAccount(User $user, ChatAccount $account): bool
    {
        return in_array($account->id, $this->accountIds($user), true);
    }

    public function accounts(User $user): Builder
    {
        return ChatAccount::whereIn('id', $this->accountIds($user));
    }

    /** Conversations the user may see; the base of every list, count and report. */
    public function conversations(User $user): Builder
    {
        $query = ChatConversation::query()->whereIn('chat_account_id', $this->accountIds($user));

        if ($this->can($user, 'view_all_chats')) {
            return $query;
        }
        if ($this->can($user, 'view_assigned_chats')) {
            return $query->where('assigned_to', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function canSee(User $user, ChatConversation $conversation): bool
    {
        return $this->conversations($user)->whereKey($conversation->id)->exists();
    }

    /** Resolve a conversation by id for this user, or null — never reveals whether it exists for someone else. */
    public function find(User $user, $id, string $permission = null): ?ChatConversation
    {
        if ($permission !== null && !$this->can($user, $permission)) {
            return null;
        }

        return $this->conversations($user)->whereKey((int) $id)->first();
    }

    /** May this staff member be given conversations of this account? */
    public function canBeAssigned(User $candidate, ChatConversation $conversation): bool
    {
        if (!in_array($conversation->chat_account_id, $this->accountIds($candidate), true)) {
            return false;
        }

        return $this->can($candidate, 'view_all_chats') || $this->can($candidate, 'view_assigned_chats');
    }

    /** Whether a user may listen on the private channel of a conversation or account feed. */
    public function canListenToAccount(User $user, int $accountId): bool
    {
        return in_array($accountId, $this->accountIds($user), true);
    }
}
