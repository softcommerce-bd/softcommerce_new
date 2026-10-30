<?php

namespace App\Services\Chat\Providers;

use App\Models\ChatAccount;
use App\Models\ChatConversation;

interface ChatProvider
{
    public function platform(): string;

    /** Hours after the customer's last message during which free-form replies are allowed (null = no window). */
    public function windowHours(): ?int;

    /** What the UI may offer for this platform. */
    public function capabilities(): array;

    /**
     * Validate a raw provider webhook (direct mode). Gateway-forwarded events are
     * already verified by the gateway and by the installation signature.
     */
    public function verifyWebhook(string $rawBody, array $headers): bool;

    /**
     * Turn a provider payload into normalized events:
     *  ['kind' => 'message', 'account_ext', 'contact_ext', 'contact_name', 'contact_phone', 'mid', 'text', 'type', 'attachments', 'ts', 'echo']
     *  ['kind' => 'status',  'account_ext', 'mid' | 'contact_ext'+'watermark', 'status', 'ts', 'error']
     */
    public function normalize(array $payload): array;

    /**
     * Send one message. $message: ['text' => ?string, 'attachment' => ?['type','url','mime']].
     *
     * @return string the provider's message id
     * @throws ProviderException
     */
    public function send(ChatAccount $account, ChatConversation $conversation, array $message): string;

    /** Best-effort display data for a new contact: ['name' => , 'avatar' => ]. */
    public function profile(ChatAccount $account, string $contactId): array;

    /**
     * Live verification with the stored credentials. Read-only provider calls.
     *
     * @return array<int, array{name:string, ok:bool, detail:string}>
     */
    public function health(ChatAccount $account): array;
}
