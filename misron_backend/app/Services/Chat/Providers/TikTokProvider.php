<?php

namespace App\Services\Chat\Providers;

use App\Models\ChatAccount;
use App\Models\ChatConversation;

/**
 * TikTok Business Messaging adapter.
 *
 * STATUS: interface only. TikTok grants Business Messaging API access per
 * developer app after review, and the endpoint contract has not been verified
 * against an authorized account, so no endpoint is invented here. Until
 * TIKTOK_MESSAGING_ENABLED is set AND a verified transport is implemented, the
 * account shows "approval required" and sending is refused.
 *
 * normalize() accepts the gateway's neutral envelope so mock tests and the
 * pipeline (dedupe, storage, UI) can be exercised without live access:
 *   {"events":[{"kind":"message","account_ext":"..","contact_ext":"..","mid":"..","text":".."}]}
 */
class TikTokProvider implements ChatProvider
{
    public function platform(): string { return 'tiktok'; }
    public function windowHours(): ?int { return config('unified_chat.windows.tiktok'); }
    public function capabilities(): array { return ['text' => true, 'image' => false, 'file' => false, 'templates' => false, 'products' => false]; }

    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        // Direct TikTok webhooks are not accepted until the signature scheme is verified with real access.
        return false;
    }

    public function normalize(array $payload): array
    {
        return array_map(fn ($e) => $e + ['contact_name' => null, 'contact_phone' => null, 'type' => 'text', 'attachments' => [], 'ts' => time(), 'echo' => false],
            array_values(array_filter($payload['events'] ?? [], 'is_array')));
    }

    public function send(ChatAccount $account, ChatConversation $conversation, array $message): string
    {
        throw new ProviderException('TikTok Business Messaging approval required: sending is disabled until the API access is granted and verified.', ProviderException::UNAVAILABLE);
    }

    public function profile(ChatAccount $account, string $contactId): array
    {
        return [];
    }

    public function health(ChatAccount $account): array
    {
        return [['name' => 'TikTok Business Messaging access', 'ok' => false, 'detail' => 'Approval required: no live check is possible until TikTok grants API access.']];
    }
}
