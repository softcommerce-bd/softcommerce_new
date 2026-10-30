<?php

namespace App\Providers;

use App\Services\Chat\ChatAccess;
use App\Services\Chat\ProviderRegistry;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class UnifiedChatServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(ProviderRegistry::class);
        $this->app->singleton(ChatAccess::class);
    }

    public function boot()
    {
        // Webhooks: stateless, signature-authenticated, rate limited.
        Route::middleware(['api', 'throttle:600,1'])->prefix('api/chat')->group(base_path('routes/unified_chat_public.php'));
        // Admin: the same stack as the rest of the admin panel (session auth + admin guard).
        Route::middleware(['web', 'auth', 'admin', 'prevent-back-history'])->prefix('admin/unified-chat')->group(base_path('routes/unified_chat.php'));

        if (config('broadcasting.default') !== 'null') {
            Broadcast::routes(['middleware' => ['web', 'auth', 'admin']]);
        }
        // Authorized through the same class as the HTTP API, evaluated at subscription time.
        Broadcast::channel('chat.account.{accountId}', fn ($user, $accountId) => app(ChatAccess::class)->canListenToAccount($user, (int) $accountId));
    }
}
