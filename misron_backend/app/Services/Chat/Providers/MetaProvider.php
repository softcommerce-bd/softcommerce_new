<?php

namespace App\Services\Chat\Providers;

use App\Models\ChatAccount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** Shared plumbing for the Meta platforms (Messenger, Instagram, WhatsApp). */
abstract class MetaProvider implements ChatProvider
{
    public function verifyWebhook(string $rawBody, array $headers): bool
    {
        $secret = (string) config('unified_chat.meta.app_secret');
        $given = $headers['x-hub-signature-256'] ?? '';
        if (is_array($given)) {
            $given = $given[0] ?? '';
        }
        if ($secret === '' || $given === '') {
            return false;
        }

        return hash_equals('sha256=' . hash_hmac('sha256', $rawBody, $secret), $given);
    }

    protected function base(): string
    {
        return 'https://graph.facebook.com/' . config('unified_chat.graph_version');
    }

    protected function token(ChatAccount $account): string
    {
        $token = $account->secrets()['access_token'] ?? '';
        if ($token === '') {
            throw new ProviderException('No access token stored for this account. Reconnect it.', ProviderException::AUTH);
        }

        return $token;
    }

    /** Map a Graph API response to a result or a typed exception. */
    protected function check(Response $response): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $error = $response->json('error') ?? [];
        $code = (int) ($error['code'] ?? 0);
        $sub = (int) ($error['error_subcode'] ?? 0);
        $message = (string) ($error['message'] ?? ('HTTP ' . $response->status()));

        if ($code === 190 || $response->status() === 401) {
            throw new ProviderException('Authorization expired or revoked. Reconnect the account.', ProviderException::AUTH);
        }
        if (in_array($code, [4, 17, 32, 613, 80006, 80007, 130429, 131056], true) || $response->status() === 429 || $response->serverError()) {
            throw new ProviderException('Provider is rate limiting or unavailable: ' . $message, ProviderException::RETRY);
        }
        if (in_array($code, [10, 200, 299], true) && !in_array($sub, [2018278], true)) {
            throw new ProviderException('Permission missing or not approved for this app: ' . $message, ProviderException::AUTH);
        }
        if ($sub === 2018278 || $code === 131047 || $code === 131051) {
            throw new ProviderException('Outside the provider messaging window.', ProviderException::UNAVAILABLE);
        }

        // Never echo tokens: Graph messages do not contain them, but strip anything token-shaped anyway.
        throw new ProviderException(preg_replace('/(access_token=)[^&\s]+/', '$1***', $message), ProviderException::REJECTED);
    }

    protected function http(string $token, bool $bearer = true)
    {
        $client = Http::timeout(15)->acceptJson()->retry(1, 200, fn ($e) => $e instanceof \Illuminate\Http\Client\ConnectionException, false);

        return $bearer ? $client->withToken($token) : $client;
    }

    public function profile(ChatAccount $account, string $contactId): array
    {
        return [];
    }
}
