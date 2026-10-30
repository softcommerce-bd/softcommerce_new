<?php

namespace App\Services;

use AizPackages\CombinationGenerate\Services\CombinationService;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Utility\ProductUtility;
use Combinations;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ProductService
{
  public function store(array $data)
{
    $collection = collect($data);

    // Handle custom video
    $custom_video = null;
    $videoFile = $collection->get('custom_video');

    if ($videoFile && $videoFile instanceof \Illuminate\Http\UploadedFile) {
        $filename = time() . '.' . $videoFile->getClientOriginalExtension();
        $videoFile->move(public_path('uploads/videos'), $filename);
        $custom_video = $filename;
        $collection->forget('custom_video');
    }

    // DEBUG: Log incoming data
    \Log::info('ProductService - Incoming data:', [
        'choice_no' => $collection->get('choice_no'),
        'choice_options_3' => $collection->get('choice_options_3'),
        'has_file' => $videoFile ? 'yes' : 'no'
    ]);

    // IMPORTANT: If choice_options_3 is missing but choice_attributes exists, reconstruct it
    if (!$collection->has('choice_options_3') && $collection->has('choice_attributes')) {
        $attributes = $collection->get('choice_attributes');
        if (is_array($attributes) && !empty($attributes)) {
            foreach ($attributes as $attr_id) {
                $option_name = 'choice_options_' . $attr_id;
                // Try to get from request
                if (request()->has($option_name)) {
                    $value = request()->input($option_name);
                    if (is_string($value)) {
                        $value = explode(',', $value);
                    }
                    $collection[$option_name] = $value;
                    \Log::info('Reconstructed ' . $option_name, ['value' => $value]);
                }
            }
        }
    }

    $approved = 1;
    if (auth()->user()->user_type == 'seller') {
        $user_id = auth()->user()->id;
        if (get_setting('product_approve_by_admin') == 1) {
            $approved = 0;
        }
    } else {
        $user_id = User::where('user_type', 'admin')->first()->id;
    }
    
    $tags = array();
    if ($collection['tags'][0] != null) {
        foreach (json_decode($collection['tags'][0]) as $key => $tag) {
            array_push($tags, $tag->value);
        }
    }
    $collection['tags'] = implode(',', $tags);
    
    $discount_start_date = null;
    $discount_end_date   = null;
    if ($collection['date_range'] != null) {
        $date_var               = explode(" to ", $collection['date_range']);
        $discount_start_date = strtotime($date_var[0]);
        $discount_end_date   = strtotime($date_var[1]);
    }
    unset($collection['date_range']);

    if ($collection['meta_title'] == null) {
        $collection['meta_title'] = $collection['name'];
    }
    if ($collection['meta_description'] == null) {
        $collection['meta_description'] = strip_tags($collection['description']);
    }

    if ($collection['meta_img'] == null) {
        $collection['meta_img'] = $collection['thumbnail_img'];
    }

    $shipping_cost = 0;
    if (isset($collection['shipping_type'])) {
        if ($collection['shipping_type'] == 'free') {
            $shipping_cost = 0;
        } elseif ($collection['shipping_type'] == 'flat_rate') {
            $shipping_cost = $collection['flat_shipping_cost'];
        }
    }
    unset($collection['flat_shipping_cost']);

    $slug = Str::slug($collection['name']);
    $same_slug_count = Product::where('slug', 'LIKE', $slug . '%')->count();
    $slug_suffix = $same_slug_count ? '-' . ($same_slug_count + 1) : '';
    $slug .= $slug_suffix;

    $colors = json_encode(array());
    if (
        isset($collection['colors_active']) &&
        $collection['colors_active'] &&
        $collection['colors'] &&
        count($collection['colors']) > 0
    ) {
        $colors = json_encode($collection['colors']);
    }

    // Ensure choice options are in array format
    if (isset($collection['choice_no']) && is_array($collection['choice_no'])) {
        foreach ($collection['choice_no'] as $no) {
            $name = 'choice_options_' . $no;
            if (isset($collection[$name]) && is_string($collection[$name])) {
                $collection[$name] = explode(',', $collection[$name]);
            }
        }
    }

    $options = ProductUtility::get_attribute_options($collection->toArray());
    $combinations = (new CombinationService())->generate_combination($options);
    
    \Log::info('ProductService - Combinations generated:', ['count' => count($combinations)]);

    // Store variant data before unsetting
    $variant_data = [];
    if (count($combinations) > 0) {
        foreach ($combinations as $combination) {
            $str = ProductUtility::get_combination_string($combination, $collection);
            $variantKey = str_replace('.', '_', $str);
            
            $variant_data[$str] = [
                'price' => $collection->get('price_' . $variantKey, $collection->get('unit_price', 0)),
                'sku' => $collection->get('sku_' . $variantKey, null),
                'qty' => $collection->get('qty_' . $variantKey, 0),
                'image' => $collection->get('img_' . $variantKey, null)
            ];
            
            // Unset from collection
            $collection->forget([
                'price_' . $variantKey,
                'sku_' . $variantKey,
                'qty_' . $variantKey,
                'img_' . $variantKey
            ]);
        }
    }

    unset($collection['colors_active']);

    // Build choice_options array
    $choice_options = array();
    if (isset($collection['choice_no']) && $collection['choice_no']) {
        foreach ($collection['choice_no'] as $no) {
            $str = 'choice_options_' . $no;
            $item = array();
            $item['attribute_id'] = $no;
            $attribute_data = array();

            if (isset($collection[$str]) && is_array($collection[$str])) {
                foreach ($collection[$str] as $eachValue) {
                    array_push($attribute_data, $eachValue);
                }
                unset($collection[$str]);
            }

            $item['values'] = $attribute_data;
            array_push($choice_options, $item);
        }
    }

    $choice_options = json_encode($choice_options, JSON_UNESCAPED_UNICODE);

    if (isset($collection['choice_no']) && $collection['choice_no']) {
        $attributes = json_encode($collection['choice_no']);
        unset($collection['choice_no']);
    } else {
        $attributes = json_encode(array());
    }

    $published = 1;
    if ($collection['button'] == 'unpublish' || $collection['button'] == 'draft') {
        $published = 0;
    }
    unset($collection['button']);

    // Create product data
    $productData = $collection->merge(compact(
        'user_id',
        'approved',
        'discount_start_date',
        'discount_end_date',
        'shipping_cost',
        'slug',
        'colors',
        'choice_options',
        'attributes',
        'published',
        'custom_video',  // MAKE SURE THIS IS NOT COMMENTED
    ))->toArray();

    $product = Product::create($productData);
    
    // ========== CREATE STOCK ENTRIES ==========
    // This is the critical part you were missing!
    if (count($combinations) > 0) {
        // Variant product - create stock for each combination
        $product->variant_product = 1;
        $product->save();
        
        $totalStock = 0;
        foreach ($variant_data as $variant_name => $variant_info) {
            $product->stocks()->create([
                'variant' => $variant_name,
                'sku' => $variant_info['sku'],
                'price' => $variant_info['price'],
                'qty' => $variant_info['qty'],
                'image' => $variant_info['image']
            ]);
            $totalStock += (int)$variant_info['qty'];
        }
        $product->current_stock = $totalStock;
        $product->save();
    } else {
        // Simple product - create single stock
        $product->stocks()->create([
            'variant' => '',
            'sku' => $collection->get('sku', null),
            'price' => $collection->get('unit_price', 0),
            'qty' => $collection->get('current_stock', 0),
            'image' => null
        ]);
    }
    // ==========================================

    return $product;
}



    // public function update(array $data, Product $product)
    // {
    //     $collection = collect($data);

    //     $slug = Str::slug($collection['name']);
    //     $slug = $collection['slug'] ? Str::slug($collection['slug']) : Str::slug($collection['name']);
    //     $same_slug_count = Product::where('slug', 'LIKE', $slug . '%')->count();
    //     $slug_suffix = $same_slug_count > 1 ? '-' . $same_slug_count + 1 : '';
    //     $slug .= $slug_suffix;

    //     if (addon_is_activated('refund_request') && !isset($collection['refundable'])) {
    //         $collection['refundable'] = 0;
    //     }

    //     if (!isset($collection['is_quantity_multiplied'])) {
    //         $collection['is_quantity_multiplied'] = 0;
    //     }

    //     if (!isset($collection['cash_on_delivery'])) {
    //         $collection['cash_on_delivery'] = 0;
    //     }
    //     if (!isset($collection['featured'])) {
    //         $collection['featured'] = 0;
    //     }
    //     if (!isset($collection['todays_deal'])) {
    //         $collection['todays_deal'] = 0;
    //     }


    //     $tags = array();
    //     if ($collection['tags'][0] != null) {
    //         foreach (json_decode($collection['tags'][0]) as $key => $tag) {
    //             array_push($tags, $tag->value);
    //         }
    //     }
    //     $collection['tags'] = implode(',', $tags);
    //     $discount_start_date = null;
    //     $discount_end_date   = null;
    //     if ($collection['date_range'] != null) {
    //         $date_var               = explode(" to ", $collection['date_range']);
    //         $discount_start_date = strtotime($date_var[0]);
    //         $discount_end_date   = strtotime($date_var[1]);
    //     }
    //     unset($collection['date_range']);

    //     if ($collection['meta_title'] == null) {
    //         $collection['meta_title'] = $collection['name'];
    //     }
    //     if ($collection['meta_description'] == null) {
    //         $collection['meta_description'] = strip_tags($collection['description']);
    //     }

    //     if ($collection['meta_img'] == null) {
    //         $collection['meta_img'] = $collection['thumbnail_img'];
    //     }

    //     if ($collection['lang'] != env("DEFAULT_LANGUAGE")) {
    //         unset($collection['name']);
    //         unset($collection['unit']);
    //         unset($collection['description']);
    //     }
    //     unset($collection['lang']);


    //     $shipping_cost = 0;
    //     if (isset($collection['shipping_type'])) {
    //         if ($collection['shipping_type'] == 'free') {
    //             $shipping_cost = 0;
    //         } elseif ($collection['shipping_type'] == 'flat_rate') {
    //             $shipping_cost = $collection['flat_shipping_cost'];
    //         }
    //     }
    //     unset($collection['flat_shipping_cost']);

    //     $colors = json_encode(array());
    //     if (
    //         isset($collection['colors_active']) &&
    //         $collection['colors_active'] &&
    //         $collection['colors'] &&
    //         count($collection['colors']) > 0
    //     ) {
    //         $colors = json_encode($collection['colors']);
    //     }

    //     $options = ProductUtility::get_attribute_options($collection);

    //     $combinations = (new CombinationService())->generate_combination($options);
    //     if (count($combinations) > 0) {
    //         foreach ($combinations as $key => $combination) {
    //             $str = ProductUtility::get_combination_string($combination, $collection);

    //             unset($collection['price_' . str_replace('.', '_', $str)]);
    //             unset($collection['sku_' . str_replace('.', '_', $str)]);
    //             unset($collection['qty_' . str_replace('.', '_', $str)]);
    //             unset($collection['img_' . str_replace('.', '_', $str)]);
    //         }
    //     }

    //     unset($collection['colors_active']);

    //     // $choice_options = array();
    //     // if (isset($collection['choice_no']) && $collection['choice_no']) {
    //     //     $str = '';
    //     //     $item = array();
    //     //     foreach ($collection['choice_no'] as $key => $no) {
    //     //         $str = 'choice_options_' . $no;
    //     //         $item['attribute_id'] = $no;
    //     //         $attribute_data = array();
    //     //         // foreach (json_decode($request[$str][0]) as $key => $eachValue) {
    //     //         foreach ($collection[$str] as $key => $eachValue) {
    //     //             // array_push($data, $eachValue->value);
    //     //             array_push($attribute_data, $eachValue);
    //     //         }
    //     //         unset($collection[$str]);

    //     //         $item['values'] = $attribute_data;
    //     //         array_push($choice_options, $item);
    //     //     }
    //     // }
    //     $choice_options = [];

    //     if (isset($collection['choice_no']) && is_array($collection['choice_no'])) {
    //         foreach ($collection['choice_no'] as $no) {
    //             $str = 'choice_options_' . $no;

    //             // Check if the key exists before accessing it
    //             if (!isset($collection[$str]) || !is_array($collection[$str])) {
    //                 continue; // Skip if missing
    //             }

    //             $item = [
    //                 'attribute_id' => $no,
    //                 'values' => $collection[$str], // Directly assign values
    //             ];

    //             unset($collection[$str]); // Remove used key
    //             array_push($choice_options, $item);
    //         }
    //     }

    //     $choice_options = json_encode($choice_options, JSON_UNESCAPED_UNICODE);

    //     if (isset($collection['choice_no']) && $collection['choice_no']) {
    //         $attributes = json_encode($collection['choice_no']);
    //         unset($collection['choice_no']);
    //     } else {
    //         $attributes = json_encode(array());
    //     }

    //     unset($collection['button']);

    //     $data = $collection->merge(compact(
    //         'discount_start_date',
    //         'discount_end_date',
    //         'shipping_cost',
    //         'slug',
    //         'colors',
    //         'choice_options',
    //         'attributes',
    //     ))->toArray();

    //     $product->update($data);

    //     return $product;
    // }

    public function update(array $data, Product $product)
{
    $collection = collect($data);
    // dd($collection);

    // Handle custom video for update
    $custom_video = $product->custom_video; // Keep existing video by default
    $videoFile = $collection->get('custom_video');
    
    if ($videoFile && $videoFile instanceof \Illuminate\Http\UploadedFile) {
        // Delete old video if exists
        if ($product->custom_video && file_exists(public_path('uploads/videos/' . $product->custom_video))) {
            unlink(public_path('uploads/videos/' . $product->custom_video));
        }
        
        // Upload new video
        $filename = time() . '.' . $videoFile->getClientOriginalExtension();
        $videoFile->move(public_path('uploads/videos'), $filename);
        $custom_video = $filename;
        
        // Remove the uploaded file object from collection
        $collection->forget('custom_video');
    } else {
        // If no new video uploaded, remove custom_video from collection to keep existing
        $collection->forget('custom_video');
    }

    $slug = Str::slug($collection['name']);
    $slug = $collection['slug'] ? Str::slug($collection['slug']) : Str::slug($collection['name']);
    $same_slug_count = Product::where('slug', 'LIKE', $slug . '%')->where('id', '!=', $product->id)->count();
    $slug_suffix = $same_slug_count > 0 ? '-' . ($same_slug_count + 1) : '';
    $slug .= $slug_suffix;

    if (addon_is_activated('refund_request') && !isset($collection['refundable'])) {
        $collection['refundable'] = 0;
    }

    if (!isset($collection['is_quantity_multiplied'])) {
        $collection['is_quantity_multiplied'] = 0;
    }

    if (!isset($collection['cash_on_delivery'])) {
        $collection['cash_on_delivery'] = 0;
    }
    if (!isset($collection['featured'])) {
        $collection['featured'] = 0;
    }
    if (!isset($collection['todays_deal'])) {
        $collection['todays_deal'] = 0;
    }

    $tags = array();
    if (isset($collection['tags'][0]) && $collection['tags'][0] != null) {
        foreach (json_decode($collection['tags'][0]) as $key => $tag) {
            array_push($tags, $tag->value);
        }
    }
    $collection['tags'] = implode(',', $tags);
    
    $discount_start_date = null;
    $discount_end_date   = null;
    if ($collection['date_range'] != null) {
        $date_var               = explode(" to ", $collection['date_range']);
        $discount_start_date = strtotime($date_var[0]);
        $discount_end_date   = strtotime($date_var[1]);
    }
    unset($collection['date_range']);

    if ($collection['meta_title'] == null) {
        $collection['meta_title'] = $collection['name'];
    }
    if ($collection['meta_description'] == null) {
        $collection['meta_description'] = strip_tags($collection['description']);
    }

    if ($collection['meta_img'] == null) {
        $collection['meta_img'] = $collection['thumbnail_img'];
    }

    if ($collection['lang'] != env("DEFAULT_LANGUAGE")) {
        unset($collection['name']);
        unset($collection['unit']);
        unset($collection['description']);
    }
    unset($collection['lang']);

    $shipping_cost = 0;
    if (isset($collection['shipping_type'])) {
        if ($collection['shipping_type'] == 'free') {
            $shipping_cost = 0;
        } elseif ($collection['shipping_type'] == 'flat_rate') {
            $shipping_cost = $collection['flat_shipping_cost'];
        }
    }
    unset($collection['flat_shipping_cost']);

    $colors = json_encode(array());
    if (
        isset($collection['colors_active']) &&
        $collection['colors_active'] &&
        isset($collection['colors']) &&
        count($collection['colors']) > 0
    ) {
        $colors = json_encode($collection['colors']);
    }

    // Ensure choice options are in array format
    if (isset($collection['choice_no']) && is_array($collection['choice_no'])) {
        foreach ($collection['choice_no'] as $no) {
            $name = 'choice_options_' . $no;
            if (isset($collection[$name]) && is_string($collection[$name])) {
                $collection[$name] = explode(',', $collection[$name]);
            }
        }
    }

    $options = ProductUtility::get_attribute_options($collection->toArray());
    $combinations = (new CombinationService())->generate_combination($options);
    
    // Store variant data before unsetting
    $variant_data = [];
    if (count($combinations) > 0) {
        foreach ($combinations as $combination) {
            $str = ProductUtility::get_combination_string($combination, $collection);
            $variantKey = str_replace('.', '_', $str);
            
            $variant_data[$str] = [
                'price' => $collection->get('price_' . $variantKey, $collection->get('unit_price', 0)),
                'sku' => $collection->get('sku_' . $variantKey, null),
                'qty' => $collection->get('qty_' . $variantKey, 0),
                'image' => $collection->get('img_' . $variantKey, null)
            ];
            
            // Unset from collection
            $collection->forget([
                'price_' . $variantKey,
                'sku_' . $variantKey,
                'qty_' . $variantKey,
                'img_' . $variantKey
            ]);
        }
    }

    unset($collection['colors_active']);

    // Build choice_options array
    $choice_options = [];
    if (isset($collection['choice_no']) && is_array($collection['choice_no'])) {
        foreach ($collection['choice_no'] as $no) {
            $str = 'choice_options_' . $no;

            if (!isset($collection[$str]) || !is_array($collection[$str])) {
                continue;
            }

            $item = [
                'attribute_id' => $no,
                'values' => $collection[$str],
            ];

            unset($collection[$str]);
            array_push($choice_options, $item);
        }
    }

    $choice_options = json_encode($choice_options, JSON_UNESCAPED_UNICODE);

    if (isset($collection['choice_no']) && $collection['choice_no']) {
        $attributes = json_encode($collection['choice_no']);
        unset($collection['choice_no']);
    } else {
        $attributes = json_encode(array());
    }

    unset($collection['button']);

    // Merge all data including custom_video
    $data = $collection->merge(compact(
        'discount_start_date',
        'discount_end_date',
        'shipping_cost',
        'slug',
        'colors',
        'choice_options',
        'attributes',
        'custom_video',  // Add custom_video to update
    ))->toArray();

    $product->update($data);

    // Stock rows are updated in place. They used to be deleted and recreated with the quantities typed
    // into this form, which overwrote every sale, purchase and adjustment made since the form was opened.
    $quantityIgnored = $this->syncStockRows(
        $product,
        count($combinations) > 0 ? $variant_data : null,
        ['sku' => $collection->get('sku', null), 'price' => $collection->get('unit_price', 0), 'qty' => $collection->get('current_stock', 0)]
    );
    if ($quantityIgnored && function_exists('flash')) {
        flash(translate('The quantities in the form were not applied: stock changes through purchases and stock adjustments, so sales and receipts made meanwhile are never overwritten.'))->warning();
    }

    return $product;
}

    /**
     * Brings a product's stock rows in line with its form, without touching the quantity of a row
     * that already exists.
     *
     *  - A variation that already has a row keeps its row (and its id, which stock adjustments refer to)
     *    and its quantity; only its SKU, price and picture are updated.
     *  - A new variation gets a row with the quantity typed (an opening quantity).
     *  - A variation the form no longer offers is removed only when nothing is left on its shelf;
     *    otherwise it stays, so stock is never silently deleted.
     *  - products.current_stock becomes the real total of the rows.
     *
     * @param array<string, array{sku: mixed, price: mixed, qty: mixed, image: mixed}>|null $variants by name; null for a plain product
     * @param array{sku: mixed, price: mixed, qty: mixed} $plain the plain product's form values
     * @return bool whether a typed quantity differed from an existing row and was therefore not applied
     */
    public function syncStockRows(Product $product, ?array $variants, array $plain): bool
    {
        $ignored = false;
        $existing = $product->stocks()->orderBy('id')->get();

        if ($variants !== null) {
            $byName = $existing->keyBy('variant');

            foreach ($variants as $name => $info) {
                $row = $byName->get((string) $name);
                if ($row) {
                    $ignored = $ignored || (int) $row->qty !== (int) ($info['qty'] ?? 0);
                    $row->update(['sku' => $info['sku'] ?? null, 'price' => $info['price'] ?? 0, 'image' => $info['image'] ?? null]);
                } else {
                    $product->stocks()->create([
                        'variant' => (string) $name,
                        'sku' => $info['sku'] ?? null,
                        'price' => $info['price'] ?? 0,
                        'qty' => (int) ($info['qty'] ?? 0),
                        'image' => $info['image'] ?? null,
                    ]);
                }
            }

            foreach ($byName as $name => $row) {
                if (!array_key_exists((string) $name, $variants) && (int) $row->qty === 0) {
                    $row->delete();
                }
            }
            $product->variant_product = 1;
        } else {
            $row = $existing->first();
            if ($row) {
                $ignored = (int) $row->qty !== (int) ($plain['qty'] ?? 0);
                $row->update(['sku' => $plain['sku'] ?? null, 'price' => $plain['price'] ?? 0]);
            } else {
                $product->stocks()->create([
                    'variant' => '',
                    'sku' => $plain['sku'] ?? null,
                    'price' => $plain['price'] ?? 0,
                    'qty' => (int) ($plain['qty'] ?? 0),
                    'image' => null,
                ]);
            }
            $product->variant_product = 0;
        }

        $product->current_stock = (int) $product->stocks()->sum('qty');
        $product->save();

        return $ignored;
    }

    public function product_duplicate_store($product)
    {
        $product_new = $product->replicate();
        $product_new->num_of_sale = 0;
        $product_new->slug = $product_new->slug . '-' . Str::random(5);
        $product_new->approved = (get_setting('product_approve_by_admin') == 1 && $product->added_by != 'admin') ? 0 : 1;
        $product_new->save();

        return $product_new;
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->product_translations()->delete();
        $product->categories()->detach();
        $product->stocks()->delete();
        $product->taxes()->delete();
        $product->wishlists()->delete();
        $product->carts()->delete();
        $product->frequently_bought_products()->delete();
        $product->last_viewed_products()->delete();
        $product->flash_deal_products()->delete();
        Product::destroy($id);
    }

    public function product_search(array $data)
    {
        $collection     = collect($data);
        $auth_user      = auth()->user();
        $productType    = $collection['product_type'];
        $products       = Product::query();

        if ($collection['category'] != null) {
            $category = Category::with('childrenCategories')->find($collection['category']);
            $products = $category->products();
        }

        $products = in_array($auth_user->user_type, ['admin', 'staff']) ? $products->where('products.added_by', 'admin') : $products->where('products.user_id', $auth_user->id);
        $products->where('published', '1')->where('auction_product', 0)->where('approved', '1');

        if ($productType == 'physical') {
            $products->where('digital', 0)->where('wholesale_product', 0);
        } elseif ($productType == 'digital') {
            $products->where('digital', 1);
        } elseif ($productType == 'wholesale') {
            $products->where('wholesale_product', 1);
        }

        if ($collection['product_id'] != null) {
            $products->where('id', '!=', $collection['product_id']);
        }

        if ($collection['search_key'] != null) {
            $products->where('name', 'like', '%' . $collection['search_key'] . '%');
        }

        return $products->limit(20)->get();
    }

    public function setCategoryWiseDiscount(array $data)
    {
        $auth_user      = auth()->user();
        $discount_start_date = null;
        $discount_end_date   = null;
        if ($data['date_range'] != null) {
            $date_var               = explode(" to ", $data['date_range']);
            $discount_start_date = strtotime($date_var[0]);
            $discount_end_date   = strtotime($date_var[1]);
        }
        $seller_product_discount =  isset($data['seller_product_discount']) ? $data['seller_product_discount'] : null;
        $admin_id = User::where('user_type', 'admin')->first()->id;

        $products = Product::where('category_id', $data['category_id'])->where('auction_product', 0);
        if (in_array($auth_user->user_type, ['admin', 'staff']) && $seller_product_discount == 0) {
            $products = $products->where('user_id', $admin_id);
        } elseif ($auth_user->user_type == 'seller') {
            $products = $products->where('user_id', $auth_user->id);
        }

        $products->update([
            'discount' => $data['discount'],
            'discount_type' => 'percent',
            'discount_start_date' => $discount_start_date,
            'discount_end_date' => $discount_end_date,
        ]);
        return 1;
    }
}
