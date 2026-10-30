<?php

namespace App\Services;

use App\Models\OtpConfiguration;

class SendSmsService
{
    // public function sendSMS($to, $from, $text, $template_id)
    // {
    //     $otp = OtpConfiguration::where('value', 1)->first()->type;
    //     $otp_class = __NAMESPACE__ . '\\OTP\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $otp)));

    //     if (class_exists($otp_class)) {
    //         return (new $otp_class)->send($to, $from, $text, $template_id);
    //     } else {
    //         return;
    //     }
    // }
    
     public function sendSMS($mobile_no, $sender = null, $message, $template_id = null)
    {
        // dd($mobile_no, $sender,$message);
        // $url = 'https://www.24bulksmsbd.com/api/smsSendApi';
        $url = 'https://amarsmsbd.xyz/api/smsSendApi';
        
        
        $data = array(
            'customer_id' => 943,
            'api_key' => '738f7d77f30a58e981801827a633b94caaad7cc200e00',
            'message' => $message,
            'mobile_no' => $mobile_no
        );
        

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);     
        $output = curl_exec($curl);
        curl_close($curl);

        // Optional: Log the output or check for success
        if ($output === false) {
            // Handle failure (e.g., log the error)
            \Log::error('SMS sending failed: '.curl_error($curl));
        } else {
            \Log::info('SMS sent successfully: '.$output);
        }

        return $output;
    }
}
