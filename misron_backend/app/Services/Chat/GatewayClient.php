<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * This installation's side of the Softtech Messaging Integration Gateway.
 * Every call is signed with the installation secret (see GatewaySignature); the
 * provider app secrets and OAuth code exchange stay on the gateway.
 */
class GatewayClient
{
    private function cfg(): array
    {
        $c = config('unified_chat.gateway');
        if (!$c['url'] || !$c['installation_id'] || !$c['secret']) {
            throw new RuntimeException('The messaging gateway is not configured for this installation (CHAT_GATEWAY_URL, CHAT_INSTALLATION_ID, CHAT_INSTALLATION_SECRET).');
        }

        return $c;
    }

    public function configured(): bool
    {
        $c = config('unified_chat.gateway');

        return (bool) ($c['url'] && $c['installation_id'] && $c['secret']);
    }

    /** Browser redirect that starts an official OAuth / Embedded Signup flow on the gateway. */
    public function startUrl(string $platform, string $state, string $returnUrl): string
    {
        $c = $this->cfg();
        $ts = time();
        $query = ['installation_id' => $c['installation_id'], 'platform' => $platform, 'state' => $state, 'return' => $returnUrl, 'ts' => $ts];
        $query['sig'] = GatewaySignature::sign($c['secret'], $c['installation_id'], $ts, http_build_query($query));

        return rtrim($c['url'], '/') . '/oauth/start?' . http_build_query($query);
    }

    /** One-time grant → connected accounts with their tokens. Server-to-server only. */
    public function redeem(string $grant): array
    {
        return $this->post('/v1/grants/redeem', ['grant' => $grant])['accounts'] ?? [];
    }

    /** Claim an account the installation already holds a token for (WhatsApp). The gateway checks ownership with the provider. */
    public function register(string $platform, string $externalId, string $token): void
    {
        $this->post('/v1/accounts/register', ['platform' => $platform, 'external_id' => $externalId, 'access_token' => $token]);
    }

    /** Tell the gateway to stop routing an account's events here. */
    public function unlink(string $platform, string $externalId): void
    {
        $this->post('/v1/accounts/unlink', ['platform' => $platform, 'external_id' => $externalId]);
    }

    private function post(string $path, array $body): array
    {
        $c = $this->cfg();
        $raw = json_encode($body);
        $ts = time();
        $r = Http::timeout(15)->withHeaders([
            'X-Softtech-Installation' => $c['installation_id'], 'X-Softtech-Timestamp' => (string) $ts,
            'X-Softtech-Signature' => GatewaySignature::sign($c['secret'], $c['installation_id'], $ts, $raw), 'Content-Type' => 'application/json',
        ])->withBody($raw, 'application/json')->post(rtrim($c['url'], '/') . $path);
        if (!$r->successful()) {
            throw new RuntimeException('Gateway request failed (HTTP ' . $r->status() . ').');
        }

        return $r->json() ?? [];
    }
}
