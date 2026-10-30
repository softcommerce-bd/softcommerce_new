<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\BusinessSettingCollection;
use App\Models\BusinessSetting;

class BusinessSettingController extends Controller
{
    public function index()
    {
        return new BusinessSettingCollection(BusinessSetting::all());
    }
    
      public function allColors()
        {
            $types = [
                'base_color',
                'base_hov_color',
                'secondary_base_color',
                'secondary_base_hov_color',
                'header_topbar_color',
                'header_bottom_color',
                'footer_color',
            ];
        
            $colors = BusinessSetting::whereIn('type', $types)
                        ->pluck('value', 'type');
        
            return response()->json([
                'success' => true,
                'data' => $colors
            ]);
        }
}
