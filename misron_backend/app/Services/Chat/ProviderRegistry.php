<?php

namespace App\Services\Chat;

use App\Services\Chat\Providers\ChatProvider;
use App\Services\Chat\Providers\InstagramProvider;
use App\Services\Chat\Providers\MessengerProvider;
use App\Services\Chat\Providers\TikTokProvider;
use App\Services\Chat\Providers\WhatsAppProvider;
use InvalidArgumentException;

class ProviderRegistry
{
    private $providers = [];

    public function __construct()
    {
        foreach ([new MessengerProvider, new InstagramProvider, new WhatsAppProvider, new TikTokProvider] as $p) {
            $this->providers[$p->platform()] = $p;
        }
    }

    public function get(string $platform): ChatProvider
    {
        return $this->providers[$platform] ?? throw new InvalidArgumentException("Unknown chat platform: {$platform}");
    }

    public function platforms(): array
    {
        return array_keys($this->providers);
    }

    /** Test seam. */
    public function swap(ChatProvider $provider): void
    {
        $this->providers[$provider->platform()] = $provider;
    }
}
