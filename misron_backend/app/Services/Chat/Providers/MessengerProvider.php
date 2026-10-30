<?php

namespace App\Services\Chat\Providers;

use App\Models\ChatAccount;
use App\Models\ChatConversation;

class MessengerProvider extends MetaProvider
{
    protected $platform = 'facebook';
    protected $object = 'page';

    public function platform(): string { return $this->platform; }
    public function windowHours(): ?int { return config('unified_chat.windows.' . $this->platform); }

    public function capabilities(): array
    {
        return ['text' => true, 'image' => true, 'file' => true, 'templates' => false, 'products' => true];
    }

    public function normalize(array $payload): array
    {
        $events = [];
        if (($payload['object'] ?? null) !== $this->object) {
            return $events;
        }
        foreach ($payload['entry'] ?? [] as $entry) {
            $account = (string) ($entry['id'] ?? '');
            foreach ($entry['messaging'] ?? [] as $m) {
                $ts = isset($m['timestamp']) ? (int) floor($m['timestamp'] / 1000) : time();
                $sender = (string) ($m['sender']['id'] ?? '');
                $recipient = (string) ($m['recipient']['id'] ?? '');

                if (isset($m['message']['mid'])) {
                    $msg = $m['message'];
                    $echo = !empty($msg['is_echo']) || $sender === $account;
                    $attachments = [];
                    foreach ($msg['attachments'] ?? [] as $a) {
                        $attachments[] = ['type' => $a['type'] ?? 'file', 'url' => $a['payload']['url'] ?? null];
                    }
                    $events[] = [
                        'kind' => 'message', 'account_ext' => $account,
                        'contact_ext' => $echo ? $recipient : $sender, 'contact_name' => null, 'contact_phone' => null,
                        'mid' => (string) $msg['mid'], 'text' => $msg['text'] ?? null,
                        'type' => $attachments ? ($attachments[0]['type']) : 'text',
                        'attachments' => $attachments, 'ts' => $ts, 'echo' => $echo,
                    ];
                } elseif (isset($m['delivery'])) {
                    foreach ($m['delivery']['mids'] ?? [] as $mid) {
                        $events[] = ['kind' => 'status', 'account_ext' => $account, 'mid' => (string) $mid, 'status' => 'delivered', 'ts' => $ts];
                    }
                } elseif (isset($m['read'])) {
                    $events[] = ['kind' => 'status', 'account_ext' => $account, 'contact_ext' => $sender, 'watermark' => (int) floor(($m['read']['watermark'] ?? 0) / 1000), 'status' => 'read', 'ts' => $ts];
                }
            }
        }

        return $events;
    }

    protected function sendUrl(ChatAccount $account): string
    {
        return $this->base() . '/' . $account->external_id . '/messages';
    }

    public function send(ChatAccount $account, ChatConversation $conversation, array $message): string
    {
        $payload = ['recipient' => ['id' => $conversation->external_id], 'messaging_type' => 'RESPONSE'];
        if (!empty($message['attachment'])) {
            $type = in_array($message['attachment']['type'], ['image', 'video', 'audio'], true) ? $message['attachment']['type'] : 'file';
            $payload['message'] = ['attachment' => ['type' => $type, 'payload' => ['url' => $message['attachment']['url'], 'is_reusable' => false]]];
        } else {
            $payload['message'] = ['text' => (string) $message['text']];
        }

        $result = $this->check($this->http($this->token($account))->post($this->sendUrl($account), $payload));
        if (empty($result['message_id'])) {
            throw new ProviderException('Provider accepted the request but returned no message id.', ProviderException::RETRY);
        }

        return (string) $result['message_id'];
    }

    public function profile(ChatAccount $account, string $contactId): array
    {
        try {
            $r = $this->http($this->token($account))->get($this->base() . '/' . $contactId, ['fields' => 'name,profile_pic']);

            return $r->successful() ? ['name' => $r->json('name'), 'avatar' => $r->json('profile_pic')] : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
