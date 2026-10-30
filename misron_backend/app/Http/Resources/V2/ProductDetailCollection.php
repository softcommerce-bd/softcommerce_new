<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\Review;
use App\Models\Attribute;
use App\Services\SizeChartService;

class ProductDetailCollection extends ResourceCollection
{
    public function toArray($request)
    {
        $sizeChartService = new SizeChartService();

        return [
            'data' => $this->collection->map(function ($data) use ($sizeChartService) {
                $precision = 2;
                $calculable_price = home_discounted_base_price($data, false);
                $calculable_price = number_format($calculable_price, $precision, '.', '');
                $calculable_price = floatval($calculable_price);
                
                $photo_paths = get_images_path($data->photos);

                $photos = [];

                if (!empty($photo_paths)) {
                    for ($i = 0; $i < count($photo_paths); $i++) {
                        if ($photo_paths[$i] != "") {
                            $item = array();
                            $item['variant'] = "";
                            $item['path'] = $photo_paths[$i];
                            $photos[] = $item;
                        }
                    }
                }

                // Process variant images
                foreach ($data->stocks as $stockItem) {
                    if ($stockItem->image != null && $stockItem->image != "") {
                        $item = array();
                        $item['variant'] = $stockItem->variant;
                        $item['path'] = uploaded_asset($stockItem->image);
                        $photos[] = $item;
                    }
                }

                $brand = [
                    'id' => 0,
                    'name' => "",
                    'slug' => "",
                    'logo' => "",
                ];

                if ($data->brand != null) {
                    $brand = [
                        'id' => $data->brand->id,
                        'slug' => $data->brand->slug,
                        'name' => $data->brand->getTranslation('name'),
                        'logo' => uploaded_asset($data->brand->logo),
                    ];
                }

                // A product can exist without any stock rows (e.g. after an
                // inventory reset). It is then shown as out of stock instead of
                // failing the whole response.
                $first_stock = $data->stocks->first();

                $whole_sale = [];
                if (addon_is_activated('wholesale') && $first_stock) {
                    $whole_sale =  ProductWholesaleResource::collection($first_stock->wholesalePrices);
                }

                // Calculate total stock across all variants
                $total_stock = $data->stocks->sum('qty');
                
                // Get variant stock information
              // Get variant stock information with proper mapping
                $variant_stocks = [];
                $variant_options = [];
                
                // First, get all choice options. Every group contributes its
                // values: assigning here instead of merging kept only the last
                // group, so a product with Size *and* Colour dropped every
                // stock row belonging to the first group.
                $choice_options = json_decode($data->choice_options);
                if ($choice_options) {
                    foreach ($choice_options as $choice) {
                        $attribute = Attribute::find($choice->attribute_id);
                        if ($attribute) {
                            $variant_options = array_merge($variant_options, $choice->values);
                        }
                    }
                }

                // Then map stocks to these options.
                //
                // Variants are stored either as a bare size ("XL") or with a
                // colour prefix ("LightPink-XL"). Keying purely on the size
                // collapsed every colour of one size onto the same key, so the
                // last row read won and a sold-out colour could zero out a size
                // that other colours still stocked. Sizes are therefore summed
                // across colours, and the full variant is also published under
                // its own key so a colour+size pair can be resolved exactly.
                // Product-level availability: the best state any variation offers.
                $availability = ['state' => 'out_of_stock', 'can_order' => false, 'policy' => 'stock_only', 'expected_at' => null];
                $rank = ['in_stock' => 3, 'available_on_order' => 2, 'preorder' => 1, 'out_of_stock' => 0];
                foreach ($data->stocks as $stockRow) {
                    $one = \App\Services\Inventory\StockPolicy::storefrontState((int) $data->id, $stockRow->variant);
                    if ($rank[$one['state']] >= $rank[$availability['state']] && ($one['can_order'] || !$availability['can_order'])) {
                        $availability = $one;
                    }
                }

                foreach ($data->stocks as $stock) {
                    if ($stock->variant) {
                        $variant_parts = explode('-', $stock->variant);
                        $size_only = end($variant_parts); // Gets "XL" or "XXL"

                        // Only include if this size exists in options
                        if (in_array($size_only, $variant_options)) {
                            if (isset($variant_stocks[$size_only])) {
                                $variant_stocks[$size_only]['quantity'] += (int)$stock->qty;
                            } else {
                                $variant_stocks[$size_only] = [
                                    'quantity' => (int)$stock->qty,
                                    'price' => $stock->price,
                                    'image' => $stock->image ? uploaded_asset($stock->image) : null,
                                    'sku' => $stock->sku,
                                    // First stock row for this size. The
                                    // rollup spans colours, so this is exact
                                    // only for a product without colours;
                                    // where colours exist, tracking should
                                    // read the colour+size entry below.
                                    'stock_id' => (int) $stock->id,
                                    'full_variant' => $stock->variant, // Keep full variant for reference
                                ];
                            }
                        }

                        // Exact colour+size entry, untouched by the size rollup.
                        if ($stock->variant !== $size_only) {
                            $variant_stocks[$stock->variant] = [
                                'quantity' => (int)$stock->qty,
                                'price' => $stock->price,
                                'image' => $stock->image ? uploaded_asset($stock->image) : null,
                                'sku' => $stock->sku,
                                'stock_id' => (int) $stock->id,
                                'full_variant' => $stock->variant,
                            ];
                        }
                    }
                }

                // Also create a mapping for the choice options to include stock info
                $enhanced_choice_options = [];
                if ($choice_options) {
                    foreach ($choice_options as $choice) {
                        $attribute = Attribute::find($choice->attribute_id);
                        if ($attribute) {
                            $enhanced_options = [];
                            foreach ($choice->values as $value) {
                                $enhanced_options[] = [
                                    'value' => $value,
                                    'stock' => isset($variant_stocks[$value]) ? $variant_stocks[$value]['quantity'] : 0,
                                    'in_stock' => isset($variant_stocks[$value]) && $variant_stocks[$value]['quantity'] > 0,
                                ];
                            }
                            
                            $enhanced_choice_options[] = [
                                'name' => $choice->attribute_id,
                                'title' => $attribute->getTranslation('name'),
                                'options' => $enhanced_options,
                            ];
                        }
                    }
                }
                
                    // Process colors to add names using get_single_color_name helper
            $colors = json_decode($data->colors) ?? [];
            $colors_with_names = [];

            foreach ($colors as $color) {
                $colors_with_names[] = [
                    'code' => $color,
                    'name' => get_single_color_name($color), // Using the helper
                    'hex' => $color,
                ];
            }

               $video_url = null;
               $whatsapp_number = whatsapp_number('whatsapp_chat') ?? null;
               

                if (!empty($data->custom_video) && file_exists(public_path('uploads/videos/' . $data->custom_video))) {
                    $video_url = asset('uploads/videos/' . $data->custom_video);
                }

                 return [
                    'id' => (int)$data->id,
                    'name' => $data->getTranslation('name'),
                    'category_name' => $data->main_category ? $data->main_category->getTranslation('name') : '',
                    'added_by' => $data->added_by,
                    'seller_id' => $data->user->id,
                    'custom_video' => $video_url,
                    'whatsapp_number' => $whatsapp_number,
                    'recent_stock' => stocks_check($data->id) ?? 0,
                    'shop_id' => $data->added_by == 'admin' ? 0 : $data->user->shop->id,
                    'shop_slug' => $data->added_by == 'admin' ? '' : $data->user->shop->slug,
                    'shop_name' => $data->added_by == 'admin' ? translate('In House Product') : $data->user->shop->name,
                    'shop_logo' => $data->added_by == 'admin' ? uploaded_asset(get_setting('header_logo')) : uploaded_asset($data->user->shop->logo) ?? "",
                    'photos' => $photos,
                    'thumbnail_image' => uploaded_asset($data->thumbnail_img),
                    'tags' => explode(',', $data->tags),
                    'price_high_low' => (float)explode('-', home_discounted_base_price($data, false))[0] == (float)explode('-', home_discounted_price($data, false))[1] ? format_price((float)explode('-', home_discounted_price($data, false))[0]) : "From " . format_price((float)explode('-', home_discounted_price($data, false))[0]) . " to " . format_price((float)explode('-', home_discounted_price($data, false))[1]),
                    'choice_options' => $enhanced_choice_options, // Use enhanced choice options
                    // Resolved from the product's category, walking up to
                    // ancestors: charts are authored on one category but apply
                    // to everything beneath it. Null when no ancestor has one,
                    // and the storefront then hides the size guide entirely.
                    'size_chart' => $sizeChartService->forCategory($data->category_id),
                    'colors' => json_decode($data->colors) ?? [],
                    'colors_name' => $colors_with_names,
                    'has_discount' => home_base_price($data, false) != home_discounted_base_price($data, false),
                    'discount' => "-" . discount_in_percentage($data) . "%",
                    'stroked_price' => home_base_price($data),
                    'main_price' => home_discounted_base_price($data),
                    'calculable_price' => $calculable_price,
                    'meta_title' => $data->meta_title ?? $data->getTranslation('name'),
                    'meta_description' => $data->meta_description ?? '',
                    'meta_img' => uploaded_asset($data->meta_img) ?? '',
                    'currency_symbol' => currency_symbol(),
                    
                    // Improved stock information
                    'stock_info' => [
                        'total_stock' => $data->stocks->sum('qty'),
                        'has_variants' => $data->stocks->count() > 1 || ($first_stock && $first_stock->variant && $first_stock->variant != ''),
                        'in_stock' => $data->stocks->sum('qty') > 0,
                        'variants' => $variant_stocks,
                    ],
                    
                    // Keep current_stock for backward compatibility
                    'current_stock' => $data->stocks->sum('qty'),

                    // Selling policy as the server sees it (server-side checks stay authoritative): in_stock | available_on_order | preorder | out_of_stock
                    'stock_policy' => $availability['policy'],
                    'stock_state' => $availability['state'],
                    'can_order' => $availability['can_order'],
                    'expected_at' => $availability['expected_at'],
                    
                    // Variant-specific stock (now mapped to size only)
                    'variant_stocks' => $variant_stocks,
                    
                    'unit' => $data->unit ?? "",
                    'rating' => (float)$data->rating,
                    'rating_count' => (int)Review::where(['product_id' => $data->id])->count(),
                    'earn_point' => (float)$data->earn_point,
                    'description' => $data->getTranslation('description'),
                    'specification' => $data->specification ?? '',
                    'question_answer' => $data->question_answer ?? '',
                    'downloads' => $data->pdf ? uploaded_asset($data->pdf) : null,
                    'video_link' => $data->video_link != null ?  $data->video_link : "",
                    'brand' => $brand,
                    'link' => route('product', $data->slug),
                    'wholesale' => $whole_sale,
                    'est_shipping_time' => (int)$data->est_shipping_days,
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

    protected function convertToChoiceOptions($data)
    {
        $result = array();
        if ($data) {
            foreach ($data as $key => $choice) {
                $item['name'] = $choice->attribute_id;
                $item['title'] = Attribute::find($choice->attribute_id)->getTranslation('name');
                $item['options'] = $choice->values;
                array_push($result, $item);
            }
        }
        return $result;
    }

    protected function convertPhotos($data)
    {
        $result = array();
        foreach ($data as $key => $item) {
            array_push($result, uploaded_asset($item));
        }
        return $result;
    }
}