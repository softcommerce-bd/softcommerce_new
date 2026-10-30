<?php

namespace App\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use App\Models\BlockedIp;

class IpBlockCheckController extends Controller
{
    public function check(Request $request)
    {
        $ip = $request->header('X-Client-IP') ?? $request->ip();

        $blockedIp = BlockedIp::where('ip_address', $ip)
            ->where('status', 'blocked')
            ->first();

        if ($blockedIp) {
            return response()->json([
                'blocked' => true,
                'ip' => $ip,
                'message' => translate('Your IP has been blocked due to suspicious activity. Please contact support.'),
            ]);
        }

        return response()->json([
            'blocked' => false,
            'ip' => $ip,
        ]);
    }
}
