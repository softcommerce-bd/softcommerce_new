<?php

namespace App\Http\Controllers;

use App\Models\PathaoCourierSetting;
use Illuminate\Http\Request;

class PathaoCourierSettingsController extends Controller
{
     public function editPathaoSettings()
    {
        $settings = PathaoCourierSetting::first();
        return view('backend.pathao.update_settings', compact('settings'));
    }

    // Feeds the city/zone pickers on the order screen.
    public function cities()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => (new \App\Services\Couriers\PathaoCourier())->cities(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function zones($cityId)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => (new \App\Services\Couriers\PathaoCourier())->zones($cityId),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

// public function updatePathaoSettings(Request $request)
// {
//     $request->validate([
//         'store_id' => 'nullable|string',
//         'client_id' => 'required|string',
//         'client_secret' => 'required|string',
//         'username' => 'required',
//         'base_url' => 'required',
//         'password' => 'required|string',
//     ]);

//     $data = [
//         'base_url' => $request->base_url,
//         'store_id' => $request->store_id,
//         'client_id' => $request->client_id,
//         'client_secret' => $request->client_secret,
//         'username' => $request->username,
//         'password' => $request->password,
//     ];

//     $setting = PathaoCourierSetting::first();

//     if ($setting) {
//         $setting->update($data);
//     } else {
//         PathaoCourierSetting::create($data);
//     }

//     flash(translate('Pathao settings updated successfully!'))->success();
//     return redirect()->back();
// }

public function updatePathaoSettings(Request $request)
{
    // Validate input
    $request->validate([
        'store_id' => 'nullable|string',
        'client_id' => 'required|string',
        'client_secret' => 'required|string',
        'username' => 'required|string',
        'base_url' => 'required|string',
        'password' => 'required|string',
        // Note: no need to validate 'sandbox' because it comes as "on" or null
    ]);

    // Prepare data
    $data = [
        'base_url' => $request->base_url,
        'store_id' => $request->store_id,
        'client_id' => $request->client_id,
        'client_secret' => $request->client_secret,
        'settlement_charge' => $request->settlement_charge ?? 0.00,
        'username' => $request->username,
        'password' => $request->password,
        'sandbox' => $request->has('sandbox'), // true or false, works with BOOLEAN column
    ];

    // Update or create
    PathaoCourierSetting::updateOrCreate([], $data);

    // Flash message and redirect
    flash(translate('Pathao settings updated successfully!'))->success();
    return redirect()->back();
}

}
