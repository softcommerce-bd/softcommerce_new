<?php

namespace App\Services\Chat\Providers;

use App\Models\ChatAccount;

/**
 * Instagram Professional Messaging. Same webhook envelope as Messenger with
 * object "instagram". Two authorization flows exist; the account records which
 * one issued its token (credentials.flow) and the matching host is used:
 *  - instagram_login: graph.instagram.com, token belongs to the Instagram account
 *  - facebook_login:  graph.facebook.com, Page token of the linked Page
 * The two flows' permissions are never mixed on one account.
 */
class InstagramProvider extends MessengerProvider
{
    protected $platform = 'instagram';
    protected $object = 'instagram';

    public function capabilities(): array
    {
        return ['text' => true, 'image' => true, 'file' => false, 'templates' => false, 'products' => true];
    }

    protected function sendUrl(ChatAccount $account): string
    {
        $flow = $account->secrets()['flow'] ?? 'instagram_login';
        $host = $flow === 'facebook_login' ? 'graph.facebook.com' : 'graph.instagram.com';
        $id = $flow === 'facebook_login' ? 'me' : $account->external_id;

        return 'https://' . $host . '/' . config('unified_chat.graph_version') . '/' . $id . '/messages';
    }
}
