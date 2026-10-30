<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\Color;

class ProductMiniCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function ($data) {
                $wholesale_product = ($data->wholesale_product == 1) ? true : false;
                $video_url = null;

                if (!empty($data->custom_video) && file_exists(public_path('uploads/videos/' . $data->custom_video))) {
                    $video_url = asset('uploads/videos/' . $data->custom_video);
                }

                // Process colors - get color names and codes from the colors table
                $productColors = [];
                if (!empty($data->colors)) {
                    // Decode the JSON string of color codes
                    $colorCodes = json_decode($data->colors, true);
                    
                    if (is_array($colorCodes) && !empty($colorCodes)) {
                        // Get color information from colors table
                        $colorsData = Color::whereIn('code', $colorCodes)->get();
                        
                        foreach ($colorCodes as $colorCode) {
                            $colorInfo = $colorsData->firstWhere('code', $colorCode);
                            if ($colorInfo) {
                                $productColors[] = [
                                    'name' => $colorInfo->name,
                                    'code' => $colorInfo->code,
                                ];
                            } else {
                                // If color not found in colors table, still include the hex code
                                $productColors[] = [
                                    'name' => $colorCode,
                                    'code' => $colorCode,
                                ];
                            }
                        }
                    }
                }

                return [
                    'id' => $data->id,
                    'slug' => $data->slug,
                    'name' => $data->getTranslation('name'),
                    'colors' => $productColors,
                    'brand_id' => $data->brand_id ?? '',
                    'sales_name' => $data->sales_name ?? '',
                    'category_name' => $data->main_category ? $data->main_category->getTranslation('name') : '',
                    'thumbnail_image' => uploaded_asset($data->thumbnail_img),
                    'hover_image' => uploaded_asset(
                        explode(',', $data->photos)[0] ?? $data->thumbnail_img
                    ),
                    'has_discount' => home_base_price($data, false) != home_discounted_base_price($data, false),
                    'discount' => "-" . discount_in_percentage($data) . "%",
                    'stroked_price' => home_base_price($data),
                    'main_price' => home_discounted_base_price($data),
                    'recent_stock' => stocks_check($data->id) ?? 0,
                    'rating' => (float) $data->rating,
                    'sales' => (int) $data->num_of_sale,
                    'custom_video' => $video_url,
                    'is_wholesale' => $wholesale_product,
                    'links' => [
                        'details' => route('products.show', ['slug' => $data->slug, 'user_id' => $data->user_id]),
                    ]
                ];
            })
        ];
    }

    public function with($request)
    {
        return [
            'success' => true,
            'status' => 200
        ];
    }
}