<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookCapiService
{
    protected string $pixelId;
    protected string $accessToken;
    protected ?string $testEventCode;
    protected string $currency;

    // public function __construct()
    // {
    //     $this->pixelId = config('services.facebook.pixel_id');
    //     $this->accessToken = config('services.facebook.access_token');
    //     $this->testEventCode = config('services.facebook.test_event_code');
    //     $this->currency = config('app.currency', env('APP_CURRENCY', 'USD'));
    // }
public function __construct()
{
    $this->pixelId = config('services.facebook.pixel_id', '') ?? '';
    $this->accessToken = config('services.facebook.access_token', '') ?? '';
    $this->testEventCode = config('services.facebook.test_event_code', '') ?? '';
    $this->currency = config('app.currency', env('APP_CURRENCY', 'USD')) ?? 'USD';
}
    /**
     * Send a server event to Facebook CAPI
     */
    public function sendEvent(string $eventName, array $params = [], array $userCtx = [], ?string $eventId = null): bool
    {
        try {
            $payload = [
                'data' => [[
                    'event_name'        => $eventName,
                    'event_time'        => time(),
                    'event_id'          => $eventId, // for deduplication with browser pixel
                    'event_source_url'  => $params['source_url'] ?? url()->current(),
                    'action_source'     => $params['action_source'] ?? 'website',

                    'user_data' => [
                        'client_ip_address' => $userCtx['ip'] ?? request()->ip(),
                        'client_user_agent' => $userCtx['ua'] ?? request()->userAgent(),
                        'em'   => !empty($userCtx['email']) ? [hash('sha256', strtolower(trim($userCtx['email'])))] : null,
                        'ph'   => !empty($userCtx['phone']) ? [hash('sha256', preg_replace('/\D+/', '', $userCtx['phone']))] : null,
                        'external_id' => !empty($userCtx['external_id']) ? hash('sha256', $userCtx['external_id']) : null,
                        'fbp'  => $this->getFbp(),
                        'fbc'  => $this->getFbc(),
                    ],

                    'custom_data' => [
                        'currency'     => $params['currency'] ?? $this->currency,
                        'value'        => $params['value'] ?? null,
                        'contents'     => $params['contents'] ?? null,
                        'content_type' => $params['content_type'] ?? null,
                        'content_ids'  => $params['content_ids'] ?? null,
                        'num_items'    => $params['num_items'] ?? null,
                    ],
                ]],
            ];

            if ($this->testEventCode) {
                $payload['test_event_code'] = $this->testEventCode;
            }

            $url = "https://graph.facebook.com/v19.0/{$this->pixelId}/events?access_token={$this->accessToken}";

            $response = Http::post($url, $payload);

            Log::info('FB CAPI sent', ['event' => $eventName, 'resp' => $response->json()]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('FB CAPI error: ' . $e->getMessage(), ['event' => $eventName]);
            return false;
        }
    }

    protected function getFbp(): ?string
    {
        return request()->cookie('_fbp');
    }

    protected function getFbc(): ?string
    {
        $fbclid = request()->query('fbclid');
        if ($fbclid) {
            return 'fb.1.' . time() . '.' . $fbclid;
        }
        return request()->cookie('_fbc');
    }
}
