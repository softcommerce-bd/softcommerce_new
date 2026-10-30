<?php

namespace App\Services\Chat\Providers;

use App\Models\ChatAccount;
use App\Models\ChatConversation;

/** WhatsApp Business Cloud API. account.external_id is the phone_number_id. */
class WhatsAppProvider extends MetaProvider
{
    public function platform(): string { return 'whatsapp'; }
    public function windowHours(): ?int { return config('unified_chat.windows.whatsapp'); }

    public function capabilities(): array
    {
        return ['text' => true, 'image' => true, 'file' => true, 'templates' => true, 'products' => true];
    }

    public function normalize(array $payload): array
    {
        $events = [];
        if (($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return $events;
        }
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? '') !== 'messages') {
                    continue;
                }
                $v = $change['value'] ?? [];
                $account = (string) ($v['metadata']['phone_number_id'] ?? '');
                $names = [];
                foreach ($v['contacts'] ?? [] as $c) {
                    $names[$c['wa_id'] ?? ''] = $c['profile']['name'] ?? null;
                }

                foreach ($v['messages'] ?? [] as $m) {
                    $type = $m['type'] ?? 'text';
                    $attachments = [];
                    $text = null;
                    if ($type === 'text') {
                        $text = $m['text']['body'] ?? null;
                    } elseif (isset($m[$type]) && is_array($m[$type])) {
                        $text = $m[$type]['caption'] ?? null;
                        if (!empty($m[$type]['id'])) {
                            $attachments[] = ['type' => $type, 'media_id' => $m[$type]['id'], 'mime' => $m[$type]['mime_type'] ?? null];
                        }
                    }
                    $from = (string) ($m['from'] ?? '');
                    $events[] = [
                        'kind' => 'message', 'account_ext' => $account, 'contact_ext' => $from,
                        'contact_name' => $names[$from] ?? null, 'contact_phone' => $from,
                        'mid' => (string) ($m['id'] ?? ''), 'text' => $text, 'type' => $type,
                        'attachments' => $attachments, 'ts' => (int) ($m['timestamp'] ?? time()), 'echo' => false,
                    ];
                }
                foreach ($v['statuses'] ?? [] as $s) {
                    $status = ['sent' => 'sent', 'delivered' => 'delivered', 'read' => 'read', 'failed' => 'failed'][$s['status'] ?? ''] ?? null;
                    if ($status) {
                        $events[] = [
                            'kind' => 'status', 'account_ext' => $account, 'mid' => (string) ($s['id'] ?? ''), 'status' => $status,
                            'ts' => (int) ($s['timestamp'] ?? time()), 'error' => $s['errors'][0]['title'] ?? null,
                        ];
                    }
                }
            }
        }

        return $events;
    }

    public function send(ChatAccount $account, ChatConversation $conversation, array $message): string
    {
        $payload = ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $conversation->external_id];
        if (!empty($message['template'])) {
            $payload['type'] = 'template';
            $payload['template'] = $message['template'];
        } elseif (!empty($message['attachment'])) {
            $type = in_array($message['attachment']['type'], ['image', 'video', 'audio'], true) ? $message['attachment']['type'] : 'document';
            $payload['type'] = $type;
            $payload[$type] = ['link' => $message['attachment']['url']] + ($message['text'] ? ['caption' => $message['text']] : []);
        } else {
            $payload['type'] = 'text';
            $payload['text'] = ['body' => (string) $message['text'], 'preview_url' => false];
        }

        $result = $this->check($this->http($this->token($account))->post($this->base() . '/' . $account->external_id . '/messages', $payload));
        $id = $result['messages'][0]['id'] ?? null;
        if (!$id) {
            throw new ProviderException('Provider accepted the request but returned no message id.', ProviderException::RETRY);
        }

        return (string) $id;
    }
}
