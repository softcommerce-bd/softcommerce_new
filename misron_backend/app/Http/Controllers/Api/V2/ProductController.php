<?php

namespace App\Http\Controllers\Api\V2;

use Cache;
use App\Models\Shop;
use App\Models\Color;
use App\Models\Product;
use App\Models\FlashDeal;
use Illuminate\Http\Request;
use App\Utility\SearchUtility;
use App\Utility\CategoryUtility;
use App\Http\Resources\V2\FlashDealCollection;
use App\Http\Resources\V2\LastViewedProductCollection;
use App\Http\Resources\V2\ProductMiniCollection;
use App\Http\Resources\V2\ProductDetailCollection;
use App\Models\Brand;
use App\Models\Category;
use App\Http\Resources\V2\Seller\BrandCollection;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function index()
    {
        return new ProductMiniCollection(Product::latest()->paginate(10));
    }
    public function buyWatchProducts()
    {
        $products = Product::where('custom_video', '!=', null)
            ->latest()
            ->limit(20)
            ->get();

        return new ProductMiniCollection($products);
    }

    public function product_details($slug, $user_id = null)
    {
        $product = Product::where('slug', $slug)->get();
        if (get_setting('last_viewed_product_activation') == 1 && $user_id != null) {
            lastViewedProducts($product[0]->id, $user_id);
        }
        return new ProductDetailCollection($product);
    }

    public function getPrice(Request $request)
    {
        $product = Product::where("slug", $request->slug)->first();
        if (!$product) {
            return response()->json(['result' => false, 'message' => translate('Product not found')], 404);
        }
        $str = '';
        $tax = 0;
        $quantity = 1;



        if ($request->has('quantity') && $request->quantity != null) {
            $quantity = $request->quantity;
        }

        if ($request->has('color') && $request->color != null) {
            $str = Color::where('code', '#' . $request->color)->first()->name;
        }

        $var_str = str_replace(',', '-', $request->variants);
        $var_str = str_replace(' ', '', $var_str);

        if ($var_str != "") {
            $temp_str = $str == "" ? $var_str : '-' . $var_str;
            $str .= $temp_str;
        }

        $product_stock = $product->stocks->where('variant', $str)->first();

        // No stock row for this variant (or none at all): report it as out of
        // stock instead of failing.
        if (!$product_stock) {
            return response()->json([
                'result' => true,
                'data' => [
                    'price' => single_price(home_discounted_base_price($product, false) * $quantity),
                    'stock' => 0,
                    'stock_txt' => translate('Out Of Stock'),
                    'digital' => $product->digital,
                    'variant' => $str,
                    'variation' => $str,
                    'max_limit' => 0,
                    'in_stock' => 0,
                    'image' => ""
                ]
            ]);
        }

        $price = $product_stock->price;


        if ($product->wholesale_product) {
            $wholesalePrice = $product_stock->wholesalePrices->where('min_qty', '<=', $quantity)->where('max_qty', '>=', $quantity)->first();
            if ($wholesalePrice) {
                $price = $wholesalePrice->price;
            }
        }

        $stock_qty = $product_stock->qty;
        $stock_txt = $product_stock->qty;
        $max_limit = $product_stock->qty;

        if ($stock_qty >= 1 && $product->min_qty <= $stock_qty) {
            $in_stock = 1;
        } else {
            $in_stock = 0;
        }

        //Product Stock Visibility
        if ($product->stock_visibility_state == 'text') {
            if ($stock_qty >= 1 && $product->min_qty < $stock_qty) {
                $stock_txt = translate('In Stock');
            } else {
                $stock_txt = translate('Out Of Stock');
            }
        }

        //discount calculation
        $discount_applicable = false;

        if ($product->discount_start_date == null) {
            $discount_applicable = true;
        } elseif (
            strtotime(date('d-m-Y H:i:s')) >= $product->discount_start_date &&
            strtotime(date('d-m-Y H:i:s')) <= $product->discount_end_date
        ) {
            $discount_applicable = true;
        }

        if ($discount_applicable) {
            if ($product->discount_type == 'percent') {
                $price -= ($price * $product->discount) / 100;
            } elseif ($product->discount_type == 'amount') {
                $price -= $product->discount;
            }
        }

        // taxes
        foreach ($product->taxes as $product_tax) {
            if ($product_tax->tax_type == 'percent') {
                $tax += ($price * $product_tax->tax) / 100;
            } elseif ($product_tax->tax_type == 'amount') {
                $tax += $product_tax->tax;
            }
        }

        $price += $tax;

        return response()->json(

            [
                'result' => true,
                'data' => [
                    'price' => single_price($price * $quantity),
                    'stock' => $stock_qty,
                    'stock_txt' => $stock_txt,
                    'digital' => $product->digital,
                    'variant' => $str,
                    'variation' => $str,
                    'max_limit' => $max_limit,
                    'in_stock' => $in_stock,
                    'image' => $product_stock->image == null ? "" : uploaded_asset($product_stock->image)
                ]

            ]
        );
    }

    public function seller($id, Request $request)
    {
        $shop = Shop::findOrFail($id);
        $products = Product::where('added_by', 'seller')->where('user_id', $shop->user_id);
        if ($request->name != "" || $request->name != null) {
            $products = $products->where('name', 'like', '%' . $request->name . '%');
        }
        $products->where('published', 1);
        return new ProductMiniCollection($products->latest()->paginate(10));
    }

    public function categoryProducts($slug, Request $request)
    {
        $category = Category::where('slug', $slug)->first();
        $category = Category::with('childrenCategories')->find($category->id);
        $products = $category->products();

        if ($request->name != "" || $request->name != null) {
            $products = $products->where('name', 'like', '%' . $request->name . '%');
        }

        return new ProductMiniCollection(filter_products($products)->latest()->paginate(10));
    }

    public function brand($slug, Request $request)
    {
        $brand = Brand::where('slug', $slug)->first();
        $products = Product::where('brand_id', $brand->id)->physical();
        if ($request->name != "" || $request->name != null) {
            $products = $products->where('name', 'like', '%' . $request->name . '%');
        }
        return new ProductMiniCollection(filter_products($products)->latest()->paginate(10));
    }

    public function getBrands()
    {
        $brands = Brand::all();

        return BrandCollection::collection($brands);
    }

    public function todaysDeal()
    {
        $products = Product::where('todays_deal', 1)->physical();
        return new ProductMiniCollection(filter_products($products)->limit(20)->latest()->get());
    }

    public function flashDeal()
    {
        return Cache::remember('app.flash_deals', 86400, function () {
            $flash_deals = FlashDeal::where('status', 1)->where('featured', 1)->where('start_date', '<=', strtotime(date('d-m-Y')))->where('end_date', '>=', strtotime(date('d-m-Y')))->get();
            return new FlashDealCollection($flash_deals);
        });
    }
    public function show($id)
    {
        return response()->json(['message' => 'Product detail not implemented.'], 404);
    }

    public function featured()
    {
        $products = Product::where('featured', 1)->physical();
        return new ProductMiniCollection(filter_products($products)->latest()->paginate(10));
    }

    public function topPicks()
    {
        $products = Product::where('top_pick', 1)->physical();
        return new ProductMiniCollection(filter_products($products)->latest()->paginate(10));
    }

    public function newArrival()
    {
        $products = Cache::remember('newest_products', 3600, function () {
            return filter_products(Product::latest())->limit(12)->get();
        });

        return new ProductMiniCollection($products);
    }

    public function bestSelling()
    {
        // The home page shows sixteen of these and expands to thirty-two, so
        // the endpoint has to return the full thirty-two for the toggle to
        // have anything to open. The cache key carries the count: raising the
        // limit alone would leave the previous twenty-item entry serving for
        // up to an hour, and the toggle would not appear.
        $products = Cache::remember('best_selling_products_32', 3600, function () {
            return get_best_selling_products(32);
        });

        return new ProductMiniCollection($products);
    }

    public function trendingNow()
    {
        $products = Product::where('trending_now', 1)->physical();
        return new ProductMiniCollection(filter_products($products)->latest()->paginate(10));
    }

    //     public function featured()
    // {
    //     try {
    //         $products = filter_products(Product::where('featured', 1)->physical())
    //                         ->latest()
    //                         ->paginate(10);
    //                         dd($products);
    //         return new ProductMiniCollection($products);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'status' => 500,
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function inhouse()
    {
        $products = Product::where('added_by', 'admin');
        return new ProductMiniCollection(filter_products($products)->latest()->paginate(12));
    }

    public function digital()
    {
        $products = Product::digital();
        return new ProductMiniCollection(filter_products($products)->latest()->paginate(10));
    }

    public function bestSeller()
    {
        $products = Product::orderBy('num_of_sale', 'desc')->physical();
        return new ProductMiniCollection(filter_products($products)->limit(20)->get());
    }

    public function frequentlyBought($slug)
    {
        $product = Product::where("slug", $slug)->first();
        $products = get_frequently_bought_products($product);
        return new ProductMiniCollection($products);
    }

    public function topFromSeller($slug)
    {
        $product = Product::where("slug", $slug)->first();
        $products = Product::where('user_id', $product->user_id)->orderBy('num_of_sale', 'desc')->physical();
        return new ProductMiniCollection(filter_products($products)->limit(10)->get());
    }

    public function search(Request $request)
    {
        $category_ids = [];
        $brand_ids = [];
        $color_codes = [];

        // Handle categories - FIXED
        if ($request->categories != null && $request->categories != "") {
            // Split by comma
            $category_ids = explode(',', $request->categories);
            // Remove any empty values
            $category_ids = array_filter($category_ids);

            // Get all child categories for each selected category
            $all_category_ids = [];
            foreach ($category_ids as $cid) {
                $cid = trim($cid);
                if (!empty($cid)) {
                    $all_category_ids[] = $cid;
                    // Get children IDs
                    $children = CategoryUtility::children_ids($cid);
                    if (!empty($children)) {
                        $all_category_ids = array_merge($all_category_ids, $children);
                    }
                }
            }
            $category_ids = array_unique($all_category_ids);

            \Log::info('Processing categories: ', $category_ids);
        }

        // Handle brands
        if ($request->brands != null && $request->brands != "") {
            $brand_ids = explode(',', $request->brands);
            $brand_ids = array_filter($brand_ids);
        }

        // Handle colors
        if ($request->colors != null && $request->colors != "") {
            $color_codes = explode(',', $request->colors);
            $color_codes = array_map(function ($code) {
                $code = urldecode(trim($code));
                // Ensure # is present
                if (!str_starts_with($code, '#')) {
                    $code = '#' . $code;
                }
                return $code;
            }, $color_codes);
            $color_codes = array_filter($color_codes);
        }

        $sort_by = $request->sort_key;
        $name = $request->name ?? $request->search ?? $request->q;
        $min = $request->min;
        $max = $request->max;

        $products = Product::where('published', 1)->physical();

        // Apply brand filter
        if (!empty($brand_ids)) {
            $products->whereIn('brand_id', $brand_ids);
            \Log::info('Applied brand filter: ', $brand_ids);
        }

        // Apply category filter - FIXED
        if (!empty($category_ids)) {
            $products->whereIn('category_id', $category_ids);
            \Log::info('Applied category filter with IDs: ', $category_ids);
        }

        // Apply color filter
        if (!empty($color_codes)) {
            $products->where(function ($query) use ($color_codes) {
                foreach ($color_codes as $colorCode) {
                    $query->orWhere('colors', 'LIKE', '%"' . $colorCode . '"%');
                }
            });
            \Log::info('Applied color filter: ', $color_codes);
        }

        // Search by name
        if ($name != null && $name != "") {
            $products->where(function ($query) use ($name) {
                $words = explode(' ', trim($name));
                foreach ($words as $word) {
                    $query->where('name', 'like', '%' . $word . '%')
                        ->orWhere('tags', 'like', '%' . $word . '%')
                        ->orWhereHas('product_translations', function ($q) use ($word) {
                            $q->where('name', 'like', '%' . $word . '%');
                        })
                        // Product code: the SKU on any of the product's variants.
                        ->orWhereHas('stocks', function ($q) use ($word) {
                            $q->where('sku', 'like', '%' . $word . '%');
                        });
                }
            });
            SearchUtility::store($name);
        }

        // Price filters
        if ($min != null && $min != "" && is_numeric($min)) {
            $products->where('unit_price', '>=', $min);
        }
        if ($max != null && $max != "" && is_numeric($max)) {
            $products->where('unit_price', '<=', $max);
        }

        // Apply sorting
        switch ($sort_by) {
            case 'price_low_to_high':
                $products->orderBy('unit_price', 'asc');
                break;
            case 'price_high_to_low':
                $products->orderBy('unit_price', 'desc');
                break;
            case 'newest':
            case 'new_arrival':
                $products->orderBy('created_at', 'desc');
                break;
            case 'best-selling':
                $products->orderBy('num_of_sale', 'desc');
                break;
            case 'top_rated':
                $products->orderBy('rating', 'desc');
                break;
            case 'featured':
                $products->where('featured', 1)->orderBy('created_at', 'desc');
                break;
            default:
                $products->orderBy('created_at', 'desc');
                break;
        }

        // Debug: Log the final query
        \Log::info('Final SQL: ' . $products->toSql());
        \Log::info('Final Bindings: ', $products->getBindings());

        return new ProductMiniCollection(filter_products($products)->paginate(20));
    }
    public function getset(Request $request)
    {
        $categoryId = $request->category_id;

        $query = Product::where('published', 1)->where('digital', 0);

        // Filter by category if provided
        if ($categoryId && $categoryId != 'all') {
            // Handle multiple categories (comma-separated)
            $categoryIds = explode(',', $categoryId);
            $query->whereIn('category_id', $categoryIds);
        }

        $products = $query->whereNotNull('colors')
            ->where('colors', '!=', '')
            ->where('colors', '!=', '[]')
            ->get(['colors']);

        $colorCounts = [];

        foreach ($products as $product) {
            if (!empty($product->colors) && $product->colors !== '[]') {
                $colorCodes = json_decode($product->colors, true);
                if (is_array($colorCodes)) {
                    foreach ($colorCodes as $code) {
                        $code = trim($code);
                        if (!empty($code)) {
                            $colorCounts[$code] = ($colorCounts[$code] ?? 0) + 1;
                        }
                    }
                }
            }
        }

        $availableColors = [];
        foreach ($colorCounts as $code => $count) {
            $color = Color::where('code', $code)->first();
            $availableColors[] = [
                'name' => $color ? $color->name : $code,
                'code' => $code,
                'product_count' => $count
            ];
        }

        usort($availableColors, function ($a, $b) {
            return $b['product_count'] - $a['product_count'];
        });

        return response()->json([
            'success' => true,
            'data' => $availableColors,
            'total' => count($availableColors)
        ]);
    }

    public function variantPrice(Request $request)
    {
        $product = Product::findOrFail($request->id);
        $str = '';
        $tax = 0;

        if ($request->has('color') && $request->color != "") {
            $str = Color::where('code', '#' . $request->color)->first()->name;
        }

        $var_str = str_replace(',', '-', $request->variants);
        $var_str = str_replace(' ', '', $var_str);

        if ($var_str != "") {
            $temp_str = $str == "" ? $var_str : '-' . $var_str;
            $str .= $temp_str;
        }
        return   $this->calc($product, $str, $request, $tax);
    }

    public function lastViewedProducts()
    {
        $lastViewedProducts = getLastViewedProducts();
        return new LastViewedProductCollection($lastViewedProducts);
    }
}
