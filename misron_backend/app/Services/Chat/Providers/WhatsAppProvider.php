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
                $firstContact = $v['contacts'][0] ?? [];
                foreach ($v['contacts'] ?? [] as $c) {
                    $names[$c['wa_id'] ?? ($c['user_id'] ?? '')] = $c['profile']['name'] ?? null;
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
                    // Meta may omit the phone number for customers who use usernames (business-scoped user ids).
                    $from = (string) ($m['from'] ?? $m['user_id'] ?? $firstContact['wa_id'] ?? $firstContact['user_id'] ?? '');
                    $events[] = [
                        'kind' => 'message', 'account_ext' => $account, 'contact_ext' => $from,
                        'contact_name' => $names[$from] ?? ($firstContact['profile']['name'] ?? null), 'contact_phone' => ctype_digit($from) ? $from : null,
                        'mid' => (string) ($m['id'] ?? ''), 'text' => $text, 'type' => $type,
                        'attachments' => $attachments, 'ts' => (int) ($m['timestamp'] ?? time()), 'echo' => false,
                    ];
                }
                // Coexistence: messages typed in the WhatsApp Business app on the phone. Documented as the
                // `smb_message_echoes` webhook field; the item key below follows the Cloud API message shape
                // and has only been exercised with mocks (see provider status doc).
                foreach ($v['message_echoes'] ?? [] as $m) {
                    $to = (string) ($m['to'] ?? $firstContact['wa_id'] ?? $firstContact['user_id'] ?? '');
                    $type = $m['type'] ?? 'text';
                    $events[] = [
                        'kind' => 'message', 'account_ext' => $account, 'contact_ext' => $to, 'contact_name' => null,
                        'contact_phone' => ctype_digit($to) ? $to : null, 'mid' => (string) ($m['id'] ?? ''),
                        'text' => $type === 'text' ? ($m['text']['body'] ?? null) : ($m[$type]['caption'] ?? null), 'type' => $type,
                        'attachments' => !empty($m[$type]['id']) ? [['type' => $type, 'media_id' => $m[$type]['id'], 'mime' => $m[$type]['mime_type'] ?? null]] : [],
                        'ts' => (int) ($m['timestamp'] ?? time()), 'echo' => true,
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
        if (!ctype_digit($conversation->external_id)) {
            throw new ProviderException('This customer is identified by a WhatsApp username, not a phone number. Replying to username-only contacts is not supported here yet.', ProviderException::UNAVAILABLE);
        }
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

    // ── templates (customer-service use only) ──────────────────────────────

    /** Approved templates of the account's WhatsApp Business Account. Marketing templates are excluded unless allowed in config. */
    public function templates(ChatAccount $account): array
    {
        $waba = $account->meta['waba_id'] ?? null;
        if (!$waba) {
            throw new ProviderException('The WhatsApp Business Account id is unknown for this number. Reconnect it with Embedded Signup.', ProviderException::UNAVAILABLE);
        }
        $data = $this->check($this->http($this->token($account))->get($this->base() . '/' . $waba . '/message_templates', ['fields' => 'name,language,status,category,components', 'limit' => 100]))['data'] ?? [];
        $allowMarketing = (bool) config('unified_chat.whatsapp.allow_marketing_templates');

        return collect($data)->filter(fn ($t) => ($t['status'] ?? '') === 'APPROVED' && ($allowMarketing || strtoupper($t['category'] ?? '') !== 'MARKETING'))
            ->map(function ($t) {
                $body = collect($t['components'] ?? [])->firstWhere('type', 'BODY')['text'] ?? '';
                preg_match_all('/\{\{(\d+)\}\}/', $body, $m);
                $hasMediaHeader = collect($t['components'] ?? [])->contains(fn ($c) => ($c['type'] ?? '') === 'HEADER' && ($c['format'] ?? 'TEXT') !== 'TEXT');
                $hasButtonVars = collect($t['components'] ?? [])->contains(fn ($c) => ($c['type'] ?? '') === 'BUTTONS' && str_contains(json_encode($c), '{{'));

                return ['name' => $t['name'], 'language' => $t['language'], 'category' => $t['category'] ?? null, 'body' => $body,
                    'params' => count(array_unique($m[1])), 'supported' => !$hasMediaHeader && !$hasButtonVars];
            })->values()->all();
    }

    /** Validated template payload, or an exception. Parameters are positional text values for the BODY component. */
    public function buildTemplate(ChatAccount $account, string $name, string $language, array $params): array
    {
        $tpl = collect($this->templates($account))->first(fn ($t) => $t['name'] === $name && $t['language'] === $language);
        if (!$tpl || !$tpl['supported']) {
            throw new ProviderException('That template is not approved for customer-service use, or needs header/button variables that are not supported here.', ProviderException::UNAVAILABLE);
        }
        if (count($params) !== $tpl['params']) {
            throw new ProviderException("This template needs exactly {$tpl['params']} value(s).", ProviderException::REJECTED);
        }
        $payload = ['name' => $name, 'language' => ['code' => $language]];
        if ($params) {
            $payload['components'] = [['type' => 'body', 'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => mb_substr((string) $p, 0, 1000)], array_values($params))]];
        }
        $text = preg_replace_callback('/\{\{(\d+)\}\}/', fn ($m) => $params[(int) $m[1] - 1] ?? '', $tpl['body']);

        return ['payload' => $payload, 'text' => $text, 'category' => $tpl['category']];
    }

    public function health(ChatAccount $account): array
    {
        $checks = [];
        try {
            $r = $this->check($this->http($this->token($account))->get($this->base() . '/' . $account->external_id, ['fields' => 'display_phone_number,verified_name,quality_rating,code_verification_status,platform_type,throughput']));
            $checks[] = ['name' => 'Access token and phone number', 'ok' => true, 'detail' => ($r['display_phone_number'] ?? $account->external_id) . ' · quality ' . ($r['quality_rating'] ?? 'unknown')];
            if (($r['quality_rating'] ?? '') === 'RED') {
                $checks[] = ['name' => 'Number quality', 'ok' => false, 'detail' => 'Meta rates this number\'s quality as RED: messaging limits may apply.'];
            }
        } catch (ProviderException $e) {
            return [['name' => 'Access token and phone number', 'ok' => false, 'detail' => $e->getMessage()]];
        }
        $waba = $account->meta['waba_id'] ?? null;
        if (!$waba) {
            $checks[] = ['name' => 'WhatsApp Business Account', 'ok' => false, 'detail' => 'Unknown WABA id: message templates and webhook check unavailable. Reconnect with Embedded Signup.'];

            return $checks;
        }
        try {
            $subs = $this->check($this->http($this->token($account))->get($this->base() . '/' . $waba . '/subscribed_apps'))['data'] ?? [];
            $ok = count($subs) > 0;
            $checks[] = ['name' => 'Webhook subscription', 'ok' => $ok, 'detail' => $ok ? 'This WABA delivers events to the subscribed app.' : 'No app is subscribed to this WhatsApp Business Account: no messages will arrive.'];
        } catch (ProviderException $e) {
            $checks[] = ['name' => 'Webhook subscription', 'ok' => false, 'detail' => $e->getMessage()];
        }

        return $checks;
    }
}
