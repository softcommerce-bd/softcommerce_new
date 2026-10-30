<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\CheckoutOtp;
use App\Services\ReveSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckoutOtpController extends Controller
{
    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:20',
            'temp_user_id' => 'nullable|string|max:100',
        ]);

        // Check if checkout OTP is enabled
        if (get_setting('checkout_otp_activation') != 1) {
            return response()->json([
                'result' => false,
                'message' => translate('Checkout OTP is not enabled')
            ]);
        }

        $phone = $request->phone;
        $otp = rand(100000, 999999);

        // Delete any existing OTP for this phone
        CheckoutOtp::where('phone', $phone)->delete();

        // Create new OTP record
        $checkoutOtp = new CheckoutOtp();
        $checkoutOtp->phone = $phone;
        $checkoutOtp->otp = $otp;
        $checkoutOtp->temp_user_id = $request->temp_user_id;
        $checkoutOtp->user_id = auth()->check() ? auth()->id() : null;
        $checkoutOtp->session_id = session()->getId();
        $checkoutOtp->expires_at = now()->addMinutes(5);
        $checkoutOtp->save();

        // Send SMS via Reve SMS
        $message = "Your OTP for order verification is: {$otp}. This code will expire in 5 minutes.";
        $smsService = new \App\Services\ReveSmsService();
        $smsService->send($phone, $message);

        return response()->json([
            'result' => true,
            'message' => translate('OTP sent successfully to your phone')
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:20',
            'otp' => 'required|string|max:10',
            'temp_user_id' => 'nullable|string|max:100',
        ]);

        // Check if checkout OTP is enabled
        if (get_setting('checkout_otp_activation') != 1) {
            return response()->json([
                'result' => false,
                'message' => translate('Checkout OTP is not enabled')
            ]);
        }

        $phone = $request->phone;
        $otp = $request->otp;

        // Find the OTP record
        $checkoutOtp = CheckoutOtp::where('phone', $phone)
            ->where('is_verified', false)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$checkoutOtp) {
            return response()->json([
                'result' => false,
                'message' => translate('No valid OTP found. Please request a new OTP.')
            ]);
        }

        if ($checkoutOtp->otp != $otp) {
            return response()->json([
                'result' => false,
                'message' => translate('Invalid OTP. Please try again.')
            ]);
        }

        // Mark OTP as verified
        $checkoutOtp->is_verified = true;
        $checkoutOtp->save();

        return response()->json([
            'result' => true,
            'message' => translate('OTP verified successfully')
        ]);
    }
}
