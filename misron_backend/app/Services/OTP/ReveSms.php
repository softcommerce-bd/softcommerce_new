<?php

namespace App\Services\OTP;

use App\Contracts\SendSms;
use Illuminate\Support\Facades\Http;

class ReveSms implements SendSms
{
    public function send($to, $from, $text, $template_id)
    {
        $response = Http::post(config('services.reve_sms.url'), [
            'apikey' => config('services.reve_sms.apikey'),
            'secretkey' => config('services.reve_sms.secretkey'),
            'callerID' => config('services.reve_sms.callerID'),
            'toUser' => $to,
            'messageContent' => $text,
        ]);

        return $response->json();
    }
}
