<?php

namespace App\Utility;

use App\Models\Addon;
use App\Models\Color;
use Illuminate\Support\Facades\Log;

class ProductUtility
{
//   public static function get_attribute_options($collection)
// {
//     // DEBUG: Log what we're receiving
//     \Log::info('ProductUtility - choice_no:', ['data' => $collection['choice_no'] ?? 'not set']);
//     \Log::info('ProductUtility - choice_options_3:', ['data' => $collection['choice_options_3'] ?? 'not set']);
    
//     $options = [];

//     if (
//         isset($collection['colors_active']) &&
//         $collection['colors_active'] &&
//         isset($collection['colors']) &&
//         is_array($collection['colors']) &&
//         count($collection['colors']) > 0
//     ) {
//         array_push($options, $collection['colors']);
//     }

//     if (isset($collection['choice_no']) && is_array($collection['choice_no'])) {
//         foreach ($collection['choice_no'] as $no) {
//             $name = 'choice_options_' . $no;
            
//             // Try multiple ways to get the data
//             $data = [];
            
//             // Method 1: From collection
//             if (isset($collection[$name])) {
//                 $data = $collection[$name];
//                 \Log::info('Found in collection: ' . $name, ['data' => $data]);
//             }
            
//             // Method 2: From request (for non-file uploads)
//             if (empty($data) && request()->has($name)) {
//                 $data = request()->input($name);
//                 \Log::info('Found in request: ' . $name, ['data' => $data]);
//             }
            
//             // Convert string to array if needed
//             if (is_string($data)) {
//                 $data = explode(',', $data);
//             }
            
//             if (is_array($data) && !empty($data)) {
//                 $options[] = $data;
//             }
//         }
//     }

//     \Log::info('ProductUtility - Final options:', ['options' => $options]);
    
//     return $options;
// }

public static function get_attribute_options($collection)
{
    $options = [];

    if (
        isset($collection['colors_active']) &&
        $collection['colors_active'] &&
        isset($collection['colors']) &&
        is_array($collection['colors']) &&
        count($collection['colors']) > 0
    ) {
        array_push($options, $collection['colors']);
    }

    if (isset($collection['choice_no']) && is_array($collection['choice_no'])) {
        foreach ($collection['choice_no'] as $no) {
            $name = 'choice_options_' . $no;
            
            // Get data from collection (NOT from request)
            $data = isset($collection[$name]) ? $collection[$name] : [];
            
            // Convert string to array if needed
            if (is_string($data)) {
                $data = explode(',', $data);
            }
            
            if (is_array($data) && !empty($data)) {
                array_push($options, $data);
            }
        }
    }


     \Log::info('ProductUtility - Final options:', ['options' => $options]);

    return $options;
}
    public static function get_combination_string($combination, $collection)
    {
        $str = '';
        foreach ($combination as $key => $item) {
            if ($key > 0) {
                $str .= '-' . str_replace(' ', '', $item);
            } else {
                if (isset($collection['colors_active']) && $collection['colors_active'] && isset($collection['colors']) && count($collection['colors']) > 0) {
                    $color = Color::where('code', $item)->first();
                    $str .= $color ? $color->name : str_replace(' ', '', $item);
                } else {
                    $str .= str_replace(' ', '', $item);
                }
            }
        }
        return $str;
    }
}