<?php

namespace App\Utility;

use App\Models\OtpConfiguration;
use App\Utility\MimoUtility;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;

class SendSMSUtility
{
    public static function sendSMS($to, $from, $text, $template_id)
    {
        // dd($text);
        // Check Nexmo SMS sending
        if (OtpConfiguration::where('type', 'nexmo')->first()->value == 1) {
            $api_key = env("NEXMO_KEY"); // API Key for Nexmo
            $api_secret = env("NEXMO_SECRET"); // API Secret for Nexmo

            $params = [
                "api_key" => $api_key,
                "api_secret" => $api_secret,
                "from" => $from,
                "text" => $text,
                "to" => $to
            ];

            $url = "https://rest.nexmo.com/sms/json";
            $params = json_encode($params);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($params),
                'accept:application/json'
            ]);

            $response = curl_exec($ch);
            if ($response === false) {
                Log::error('Nexmo cURL error: ' . curl_error($ch));
            }
            curl_close($ch);

            return $response;
        }

        // Check Twilio SMS sending
        elseif (OtpConfiguration::where('type', 'twillo')->first()->value == 1) {
            $sid = env("TWILIO_SID"); // Twilio Account SID
            $token = env("TWILIO_AUTH_TOKEN"); // Twilio Auth Token
            $type = env("TWILLO_TYPE"); // SMS type

            $client = new Client($sid, $token);
            try {
                $client->messages->create(
                    $type == 1 ? $to : "whatsapp:" . $to, // SMS or WhatsApp
                    [
                        'from' => $type == 1 ? env('VALID_TWILLO_NUMBER') : "whatsapp:" . env('VALID_TWILLO_NUMBER'), // Twilio number
                        'body' => $text
                    ]
                );
            } catch (\Exception $e) {
                Log::error('Twilio SMS error: ' . $e->getMessage());
            }

        }

        // Check SSL Wireless SMS sending
        elseif (OtpConfiguration::where('type', 'ssl_wireless')->first()->value == 1) {
            $token = env("SSL_SMS_API_TOKEN"); // API Token for SSL Wireless
            $sid = env("SSL_SMS_SID"); // API SID for SSL Wireless

            $params = [
                "api_token" => $token,
                "sid" => $sid,
                "msisdn" => $to,
                "sms" => $text,
                "csms_id" => date('dmYhhmi') . rand(10000, 99999) // Unique CSMS ID
            ];

            $url = env("SSL_SMS_URL");
            $params = json_encode($params);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($params),
                'accept:application/json'
            ]);

            $response = curl_exec($ch);
            if ($response === false) {
                Log::error('SSL Wireless cURL error: ' . curl_error($ch));
            }
            curl_close($ch);

            return $response;
        }

        // Check Fast2SMS sending
        elseif (OtpConfiguration::where('type', 'fast2sms')->first()->value == 1) {
            if (strpos($to, '+91') !== false) {
                $to = substr($to, 3); // Remove +91 for Indian numbers
            }

            $fields = [
                "sender_id" => env("SENDER_ID"),
                "message" => $text,
                "template_id" => $template_id,
                "language" => env("LANGUAGE"),
                "route" => env("ROUTE"),
                "numbers" => $to,
            ];

            $auth_key = env('AUTH_KEY');

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://www.fast2sms.com/dev/bulkV2",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode($fields),
                CURLOPT_HTTPHEADER => [
                    "authorization: $auth_key",
                    "accept: */*",
                    "cache-control: no-cache",
                    "content-type: application/json"
                ],
            ]);

            $response = curl_exec($curl);
            if ($response === false) {
                Log::error('Fast2SMS cURL error: ' . curl_error($curl));
            }
            curl_close($curl);

            return $response;
        }

        // Check Mimo SMS sending
        elseif (OtpConfiguration::where('type', 'mimo')->first()->value == 1) {
            $token = MimoUtility::getToken();
            MimoUtility::sendMessage($text, $to, $token);
            MimoUtility::logout($token);
        }

        // Check MIMSMS sending
        elseif (OtpConfiguration::where('type', 'mimsms')->first()->value == 1) {
            $url = env('MIM_BASE_URL') . "/smsapi";
            $data = [
                "api_key" => env('MIM_API_KEY'),
                "type" => "text",
                "contacts" => $to,
                "senderid" => env('MIM_SENDER_ID'),
                "msg" => $text,
            ];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            if ($response === false) {
                Log::error('MIMSMS cURL error: ' . curl_error($ch));
            }
            curl_close($ch);
            return $response;
        }

        // Check Msegat sending
        elseif (OtpConfiguration::where('type', 'msegat')->first()->value == 1) {
            $url = "https://www.msegat.com/gw/sendsms.php";
            $data = [
                "apiKey" => env('MSEGAT_API_KEY'),
                "numbers" => $to,
                "userName" => env('MSEGAT_USERNAME'),
                "userSender" => env('MSEGAT_USER_SENDER'),
                "msg" => $text
            ];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            if ($response === false) {
                Log::error('Msegat cURL error: ' . curl_error($ch));
            }
            curl_close($ch);
            return $response;
        }

        // Check Sparrow sending
        elseif (OtpConfiguration::where('type', 'sparrow')->first()->value == 1) {
            $url = "http://api.sparrowsms.com/v2/sms/";

            $args = http_build_query([
                "token" => env('SPARROW_TOKEN'),
                "from" => env('MESSGAE_FROM'),
                "to" => $to,
                "text" => $text
            ]);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $response = curl_exec($ch);
            if ($response === false) {
                Log::error('Sparrow cURL error: ' . curl_error($ch));
            }
            curl_close($ch);

            return $response;
        }

        // Check Zender SMS or WhatsApp sending
        elseif (OtpConfiguration::where('type', 'zender')->first()->value == 1) {
            if (empty(env('ZENDER_SERVICE')) || env('ZENDER_SERVICE') < 2) {
                if (!empty(env('ZENDER_DEVICE'))) {
                    $mode = "devices";
                } else {
                    $mode = "credits";
                }

                if ($mode == "devices") {
                    $params = [
                        "secret" => env('ZENDER_APIKEY'),
                        "mode" => "devices",
                        "device" => env('ZENDER_DEVICE'),
                        "phone" => $to,
                        "message" => $text,
                        "sim" => env('ZENDER_SIM') < 2 ? 1 : 2
                    ];
                } else {
                    $params = [
                        "secret" => env('ZENDER_APIKEY'),
                        "mode" => "credits",
                        "gateway" => env('ZENDER_GATEWAY'),
                        "phone" => $to,
                        "message" => $text
                    ];
                }

                $apiurl = env('ZENDER_SITEURL') . "/api/send/sms";
            } else {
                $params = [
                    "secret" => env('ZENDER_APIKEY'),
                    "account" => env('ZENDER_WHATSAPP'),
                    "type" => "text",
                    "recipient" => $to,
                    "message" => $text
                ];

                $apiurl = env('ZENDER_SITEURL') . "/api/send/whatsapp";
            }

            $args = http_build_query($params);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiurl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $response = curl_exec($ch);
            if ($response === false) {
                Log::error('Zender cURL error: ' . curl_error($ch));
            }
            curl_close($ch);

            return $response;
        }

        // Check Reve SMS sending
        elseif (OtpConfiguration::where('type', 'reve_sms')->first()->value == 1) {
            $url = env('REVE_SMS_URL');
            $data = [
                'apikey' => env('REVE_SMS_APIKEY'),
                'secretkey' => env('REVE_SMS_SECRETKEY'),
                'callerID' => env('REVE_SMS_CALLER_ID'),
                'toUser' => $to,
                'messageContent' => $text,
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            if ($response === false) {
                Log::error('Reve SMS cURL error: ' . curl_error($ch));
            }
            curl_close($ch);
            return $response;
        }

        return true;
    }

     public static function order_send_message($number, $body)
    {
        // dd($number,$body);
 
        // $url = 'https://www.24bulksmsbd.com/api/smsSendApi';
        $url = 'https://amarsmsbd.xyz/api/smsSendApi';
        $data = array(
            'customer_id' => 943,
            'api_key' => '738f7d77f30a58e981801827a633b94caaad7cc200e00',
            'message' => $body,  // Use $body parameter for the SMS text
            'mobile_no' => $number  // Use $number parameter for the recipient number
        );
        
        // Initialize cURL session
        $curl = curl_init($url);
        
        // Set cURL options
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));  // Send the data as JSON
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);  // Return the response as a string
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);  // Enable SSL certificate verification
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);  // Enable SSL host verification
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',  // Set the content type to JSON
        ));
        
        // Execute the cURL request
        $output = curl_exec($curl);
        
        // Check for errors
        if ($output === false) {
            return 'cURL Error: ' . curl_error($curl);
        } else {
            return $output;  // Return the API response
        }
        
        // Close the cURL session
        curl_close($curl);
    }

}
