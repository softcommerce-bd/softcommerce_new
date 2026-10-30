<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReveSmsService
{
    public function send($phone, $message)
    {
        // Use GET request with query parameters as per API documentation
        $response = Http::get(config('services.reve_sms.url'), [
            'apikey' => config('services.reve_sms.apikey'),
            'secretkey' => config('services.reve_sms.secretkey'),
            'callerID' => config('services.reve_sms.callerID'),
            'toUser' => $phone,
            'messageContent' => $message,
        ]);

        $body = $response->body();
        
        Log::info('Reve SMS Response:', [
            'status_code' => $response->status(),
            'body' => $body,
            'phone' => $phone,
            'url' => config('services.reve_sms.url'),
        ]);

        // Check if response is HTML (error case)
        if (str_contains($body, '<!DOCTYPE') || str_contains($body, '<html')) {
            Log::error('Reve SMS returned HTML instead of API response - check URL');
            return [
                'success' => false,
                'error' => 'Invalid API endpoint'
            ];
        }

        // Successful response from Reve API is typically a message ID or status code
        if ($response->successful()) {
            return [
                'success' => true,
                'message_id' => trim($body),
                'response' => $body
            ];
        }

        return [
            'success' => false,
            'error' => $body
        ];
    }
}