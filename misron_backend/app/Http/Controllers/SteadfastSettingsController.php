<?php

namespace App\Http\Controllers;

use App\Models\SteadfastSetting;
use Illuminate\Http\Request;

class SteadfastSettingsController extends Controller
{
    public function editSteadfastApiKeys()
    {
        $settings = SteadfastSetting::first(); // Assuming you only have one settings row
        return view('backend.steadfast.api_keys', compact('settings'));
    }
    
    public function updateSteadfastApiKeys(Request $request)
    {
        // Validate the request data
        $request->validate([
            'steadfast_api_key' => 'required|string',
            'steadfast_secret_key' => 'required|string',
            'settlement_charge' => 'nullable',
        ]);
    
        // Use updateOrCreate to update if exists, or create a new record if not
        SteadfastSetting::updateOrCreate(
            [], // You can modify this condition if needed to target a specific record
            [
                'steadfast_api_key' => $request->steadfast_api_key,
                'steadfast_secret_key' => $request->steadfast_secret_key,
                'settlement_charge' => $request->settlement_charge ?? 0.00,
            ]
        );
    
        // Flash success message
        flash(translate('API keys updated successfully.'))->success();
    
        // Redirect back
        return redirect()->back();
    }
}
