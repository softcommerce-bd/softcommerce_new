<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Search;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Color;
use App\Models\Shop;
use App\Models\Attribute;
use App\Models\AttributeCategory;
use App\Utility\CategoryUtility;

class SearchController extends Controller
{
    


    // public function index(Request $request, $category_id = null, $brand_id = null)
    // {
    //     $query = $request->keyword;
    //     $sort_by = $request->sort_by;
    //     $min_price = $request->min_price;
    //     $max_price = $request->max_price;
    //     $seller_id = $request->seller_id;
    //     $selected_attribute_values = [];
    //     $selected_colors = $request->colors ?? []; // array
    //     $category = null;
    //     $categories = [];
    //     $conditions = [];
    //     $attributes = Attribute::all();
    //     //    dd($selected_colors);
    //     //    dd($request->all());
    //     // Brand filter
    //     if ($brand_id != null) {
    //         $conditions['brand_id'] = $brand_id;
    //     } elseif ($request->brand != null) {
    //         $brand = Brand::where('slug', $request->brand)->first();
    //         if ($brand) {
    //             $conditions['brand_id'] = $brand->id;
    //             $brand_id = $brand->id;
    //         }
    //     }

    //     // Start building products query
    //     $products = Product::where($conditions);
    //     // Price range filter
    //     // if (is_numeric($min_price)) {
    //     //     $products->where('unit_price', '>=', floatval($min_price));
    //     // }
    //     // if (is_numeric($max_price)) {
    //     //     $products->where('unit_price', '<=', floatval($max_price));
    //     // }



    //     // Fetch categories
    //     $categories = Category::with('childrenCategories', 'coverImage')
    //         ->where('parent_id', 0)
    //         ->orderBy('order_level', 'desc')
    //         ->get();

    //     // Category-wise filtering
    //     if ($category_id != null) {
    //         $category_ids = CategoryUtility::children_ids($category_id);
    //         $category_ids[] = $category_id;

    //         $category = Category::with('childrenCategories')->find($category_id);

    //         $products = $products->whereIn('category_id', $category_ids);
    //     }

    //     // Keyword search
    //     if ($query != null) {
    //         $searchController = new SearchController;
    //         $searchController->store($request);

    //         $products->where(function ($q) use ($query) {
    //             foreach (explode(' ', trim($query)) as $word) {
    //                 $q->where('name', 'like', '%' . $word . '%')
    //                     ->orWhere('tags', 'like', '%' . $word . '%')
    //                     ->orWhereHas('product_translations', function ($q) use ($word) {
    //                         $q->where('name', 'like', '%' . $word . '%');
    //                     })
    //                     ->orWhereHas('stocks', function ($q) use ($word) {
    //                         $q->where('sku', 'like', '%' . $word . '%');
    //                     });
    //             }
    //         });

    //         $case1 = $query . '%';
    //         $case2 = '%' . $query . '%';

    //         $products->orderByRaw("CASE
    //         WHEN name LIKE '$case1' THEN 1
    //         WHEN name LIKE '$case2' THEN 2
    //         ELSE 3
    //         END");
    //     }

    //     // Attribute filter
    //     if ($request->has('selected_attribute_values')) {
    //         $selected_attribute_values = $request->selected_attribute_values;
    //         $products->where(function ($query) use ($selected_attribute_values) {
    //             foreach ($selected_attribute_values as $value) {
    //                 $query->orWhere('choice_options', 'like', '%' . $value . '%');
    //             }
    //         });
    //     }

    //     // Improved color filter for multiple selections
    //   if (!empty($selected_colors)) {
    //     $products->where(function ($q) use ($selected_colors) {
    //         foreach ($selected_colors as $color) {
    //             $q->orWhereJsonContains('colors', $color)
    //             ->orWhere('colors', 'like', '%"' . $color . '"%');
    //         }
    //     });
    //   }

    //     // Get available colors from current product set
    //     $colorOptions = Product::where($conditions)
    //         ->when($category_id, function ($q) use ($category_id) {
    //             $q->whereIn('category_id', is_array($category_id) ? $category_id : [$category_id]);
    //         })
    //         ->where('published', 1)
    //         ->where('approved', 1)
    //         ->get()
    //         ->flatMap(function ($product) {
    //             try {
    //                 $colors = $product->colors;

    //                 if (empty($colors)) {
    //                     return [];
    //                 }

    //                 if (is_string($colors)) {
    //                     $decoded = json_decode($colors, true);
    //                     return is_array($decoded) ? $decoded : [];
    //                 }

    //                 if (is_array($colors)) {
    //                     return $colors;
    //                 }

    //                 if (is_scalar($colors)) {
    //                     return [$colors];
    //                 }

    //                 return [];
    //             } catch (\Exception $e) {
    //                 return [];
    //             }
    //         })
    //         ->filter(function ($color) {
    //             return !empty($color) || $color === 0 || $color === '0';
    //         })
    //         ->unique()
    //         ->values()
    //         ->map(function ($color) {
    //             return [
    //                 'code' => (string)$color,
    //                 'name' => (string)$color
    //             ];
    //         });

    //     // dd($colorOptions);
    //     if ($min_price != null && $max_price != null) {
    //         $products->where('unit_price', '>=', $min_price)->where('unit_price', '<=', $max_price);
    //     }

    //     // Sorting
    //     switch ($sort_by) {
    //         case 'newest':
    //             $products->orderBy('created_at', 'desc');
    //             break;
    //         case 'oldest':
    //             $products->orderBy('created_at', 'asc');
    //             break;
    //         case 'best-selling':
    //             $products->orderBy('num_of_sale', 'desc');
    //             break;
    //         case 'price-asc':
    //             $products->orderBy('unit_price', 'asc');
    //             break;
    //         case 'price-desc':
    //             $products->orderBy('unit_price', 'desc');
    //             break;
    //         default:
    //             $products->orderBy('id', 'desc');
    //             break;
    //     }

    //     // Finalize products
    //     $products = filter_products($products)->with('taxes')->paginate(24)->appends(request()->query());

    //     return view('frontend.product_listing', compact(
    //         'products',
    //         'query',
    //         'category',
    //         'categories',
    //         'category_id',
    //         'brand_id',
    //         'sort_by',
    //         'seller_id',
    //         'attributes',
    //         'selected_attribute_values',
    //         'colorOptions',
    //         'selected_colors',
    //         'min_price',
    //         'max_price',
    //     ));
    // }


    public function listing(Request $request)
    {
        return $this->index($request);
    }

    // public function listingByCategory(Request $request, $category_slug)
    // {
    //     $category = Category::where('slug', $category_slug)->first();
    //     if ($category != null) {
    //         return $this->index($request, $category->id);
    //     }
    //     abort(404);
    // }
    
    
    
    public function listingByCategory(Request $request, $category_slug)
{
    $category = Category::where('slug', $category_slug)->first();
    if ($category != null) {
        return $this->index($request, $category->id);
    }
    abort(404);
}

public function index(Request $request, $category_id = null, $brand_id = null)
{
    $query = $request->keyword;
    $sort_by = $request->sort_by;
    $min_price = $request->min_price;
    $max_price = $request->max_price;
    $seller_id = $request->seller_id;
    $selected_attribute_values = [];
    $selected_colors = $request->colors ?? []; // array
    $category = null;
    $categories = [];
    $conditions = [];
    $attributes = Attribute::all();

    // Brand filter
    if ($brand_id != null) {
        $conditions['brand_id'] = $brand_id;
    } elseif ($request->brand != null) {
        $brand = Brand::where('slug', $request->brand)->first();
        if ($brand) {
            $conditions['brand_id'] = $brand->id;
            $brand_id = $brand->id;
        }
    }

    // Start building products query with product_categories relationship
    $products = Product::where($conditions);

    // Fetch categories
    $categories = Category::with('childrenCategories', 'coverImage')
        ->where('parent_id', 0)
        ->orderBy('order_level', 'desc')
        ->get();

    // Category-wise filtering using product_categories table
    if ($category_id != null) {
        $category_ids = CategoryUtility::children_ids($category_id);
        $category_ids[] = $category_id;

        $category = Category::with('childrenCategories')->find($category_id);

        // Use whereHas to filter products through product_categories table
        $products = $products->whereHas('product_categories', function ($q) use ($category_ids) {
            $q->whereIn('category_id', $category_ids);
        });
    }

    // Keyword search
    if ($query != null) {
        $searchController = new SearchController;
        $searchController->store($request);

        $products->where(function ($q) use ($query) {
            foreach (explode(' ', trim($query)) as $word) {
                $q->where('name', 'like', '%' . $word . '%')
                    ->orWhere('tags', 'like', '%' . $word . '%')
                    ->orWhereHas('product_translations', function ($q) use ($word) {
                        $q->where('name', 'like', '%' . $word . '%');
                    })
                    ->orWhereHas('stocks', function ($q) use ($word) {
                        $q->where('sku', 'like', '%' . $word . '%');
                    });
            }
        });

        $case1 = $query . '%';
        $case2 = '%' . $query . '%';

        $products->orderByRaw("CASE
        WHEN name LIKE '$case1' THEN 1
        WHEN name LIKE '$case2' THEN 2
        ELSE 3
        END");
    }

    // Attribute filter
    if ($request->has('selected_attribute_values')) {
        $selected_attribute_values = $request->selected_attribute_values;
        $products->where(function ($query) use ($selected_attribute_values) {
            foreach ($selected_attribute_values as $value) {
                $query->orWhere('choice_options', 'like', '%' . $value . '%');
            }
        });
    }

    // Improved color filter for multiple selections
    if (!empty($selected_colors)) {
        $products->where(function ($q) use ($selected_colors) {
            foreach ($selected_colors as $color) {
                $q->orWhereJsonContains('colors', $color)
                ->orWhere('colors', 'like', '%"' . $color . '"%');
            }
        });
    }

    // Get available colors from current product set - updated to use product_categories
    $colorConditions = $conditions;
    
    $colorOptions = Product::where($colorConditions)
        ->when($category_id, function ($q) use ($category_id) {
            $category_ids = CategoryUtility::children_ids($category_id);
            $category_ids[] = $category_id;
            $q->whereHas('product_categories', function ($q) use ($category_ids) {
                $q->whereIn('category_id', $category_ids);
            });
        })
        ->where('published', 1)
        ->where('approved', 1)
        ->get()
        ->flatMap(function ($product) {
            try {
                $colors = $product->colors;

                if (empty($colors)) {
                    return [];
                }

                if (is_string($colors)) {
                    $decoded = json_decode($colors, true);
                    return is_array($decoded) ? $decoded : [];
                }

                if (is_array($colors)) {
                    return $colors;
                }

                if (is_scalar($colors)) {
                    return [$colors];
                }

                return [];
            } catch (\Exception $e) {
                return [];
            }
        })
        ->filter(function ($color) {
            return !empty($color) || $color === 0 || $color === '0';
        })
        ->unique()
        ->values()
        ->map(function ($color) {
            return [
                'code' => (string)$color,
                'name' => (string)$color
            ];
        });

    // Price range filter
    if ($min_price != null && $max_price != null) {
        $products->where('unit_price', '>=', $min_price)->where('unit_price', '<=', $max_price);
    }

    // Sorting
    switch ($sort_by) {
        case 'newest':
            $products->orderBy('created_at', 'desc');
            break;
        case 'best_pick':
            $products->where('best_pick',1);
            break;
        case 'featured':
            $products->where('featured',1);
            break;
        case 'seasonal_highlight':
            $products->where('seasonal_highlight',1);
            break;
        case 'oldest':
            $products->orderBy('created_at', 'asc');
            break;
        case 'best-selling':
            $products->orderBy('num_of_sale', 'desc');
            break;
        case 'price-asc':
            $products->orderBy('unit_price', 'asc');
            break;
        case 'price-desc':
            $products->orderBy('unit_price', 'desc');
            break;
        default:
            $products->orderBy('id', 'desc');
            break;
    }

    // Finalize products - make sure to eager load product_categories if needed
    $products = filter_products($products)->with(['taxes', 'product_categories'])->paginate(24)->appends(request()->query());

    return view('frontend.product_listing', compact(
        'products',
        'query',
        'category',
        'categories',
        'category_id',
        'brand_id',
        'sort_by',
        'seller_id',
        'attributes',
        'selected_attribute_values',
        'colorOptions',
        'selected_colors',
        'min_price',
        'max_price',
    ));
}

    public function listingByBrand(Request $request, $brand_slug)
    {
        $brand = Brand::where('slug', $brand_slug)->first();
        if ($brand != null) {
            return $this->index($request, null, $brand->id);
        }
        abort(404);
    }

    //Suggestional Search
    // public function ajax_search(Request $request)
    // {
    //     $keywords = array();
    //     $query = $request->search;
    //     $products = Product::where('published', 1)->where('tags', 'like', '%' . $query . '%')->get();
    //     foreach ($products as $key => $product) {
    //         foreach (explode(',', $product->tags) as $key => $tag) {
    //             if (stripos($tag, $query) !== false) {
    //                 if (sizeof($keywords) > 5) {
    //                     break;
    //                 } else {
    //                     if (!in_array(strtolower($tag), $keywords)) {
    //                         array_push($keywords, strtolower($tag));
    //                     }
    //                 }
    //             }
    //         }
    //     }

    //     $products_query = filter_products(Product::query());

    //     $products_query = $products_query->where('published', 1)
    //         ->where(function ($q) use ($query) {
    //             foreach (explode(' ', trim($query)) as $word) {
    //                 $q->where('name', 'like', '%' . $word . '%')
    //                     ->orWhere('tags', 'like', '%' . $word . '%')
    //                     ->orWhereHas('product_translations', function ($q) use ($word) {
    //                         $q->where('name', 'like', '%' . $word . '%');
    //                     })
    //                     ->orWhereHas('stocks', function ($q) use ($word) {
    //                         $q->where('sku', 'like', '%' . $word . '%');
    //                     });
    //             }
    //         });
    //     $case1 = $query . '%';
    //     $case2 = '%' . $query . '%';

    //     $products_query->orderByRaw("CASE
    //             WHEN name LIKE '$case1' THEN 1
    //             WHEN name LIKE '$case2' THEN 2
    //             ELSE 3
    //             END");
    //     $products = $products_query->limit(3)->get();

    //     $categories = Category::where('name', 'like', '%' . $query . '%')->get()->take(3);

    //     $shops = Shop::whereIn('user_id', verified_sellers_id())->where('name', 'like', '%' . $query . '%')->get()->take(3);

    //     if (sizeof($keywords) > 0 || sizeof($categories) > 0 || sizeof($products) > 0 || sizeof($shops) > 0) {
    //         return view('frontend.partials.search_content', compact('products', 'categories', 'keywords', 'shops'));
    //     }
    //     return '0';
    // }
    // public function ajax_search(Request $request)
    // {
    //     $keywords = array();
    //     $query = $request->search;
    //     $products = Product::where('published', 1)->where('tags', 'like', '%' . $query . '%')->get();
    //     foreach ($products as $key => $product) {
    //         foreach (explode(',', $product->tags) as $key => $tag) {
    //             if (stripos($tag, $query) !== false) {
    //                 if (sizeof($keywords) > 5) {
    //                     break;
    //                 } else {
    //                     if (!in_array(strtolower($tag), $keywords)) {
    //                         array_push($keywords, strtolower($tag));
    //                     }
    //                 }
    //             }
    //         }
    //     }
    
    //     $products_query = filter_products(Product::query());
    
    //     $products_query = $products_query->where('published', 1)
    //         ->where(function ($q) use ($query) {
    //             foreach (explode(' ', trim($query)) as $word) {
    //                 $q->where('name', 'like', '%' . $word . '%')
    //                     ->orWhere('tags', 'like', '%' . $word . '%')
    //                     ->orWhereHas('product_translations', function ($q) use ($word) {
    //                         $q->where('name', 'like', '%' . $word . '%');
    //                     })
    //                     ->orWhereHas('stocks', function ($q) use ($word) {
    //                         $q->where('sku', 'like', '%' . $word . '%');
    //                     });
    //             }
    //         });
    //     // Order by search_order field in ascending order, placing NULLs at the end
    //     $products_query->orderByRaw("ISNULL(search_order), search_order ASC");
    
    //     $products = $products_query->limit(3)->get();
    
    //     $categories = Category::where('name', 'like', '%' . $query . '%')->get()->take(3);
    
    //     $shops = Shop::whereIn('user_id', verified_sellers_id())->where('name', 'like', '%' . $query . '%')->get()->take(3);
    
    //     if (sizeof($keywords) > 0 || sizeof($categories) > 0 || sizeof($products) > 0 || sizeof($shops) > 0) {
    //         return view('frontend.partials.search_content', compact('products', 'categories', 'keywords', 'shops'));
    //     }
    //     return '0';
    // }
    
      //Suggestional Search
    public function ajax_search(Request $request)
    {
        $keywords = array();
        $query = $request->search;
        $products = Product::where('published', 1)->where('tags', 'like', '%' . $query . '%')->get();
        foreach ($products as $key => $product) {
            foreach (explode(',', $product->tags) as $key => $tag) {
                if (stripos($tag, $query) !== false) {
                    if (sizeof($keywords) > 5) {
                        break;
                    } else {
                        if (!in_array(strtolower($tag), $keywords)) {
                            array_push($keywords, strtolower($tag));
                        }
                    }
                }
            }
        }

        $products_query = filter_products(Product::query());

        $products_query = $products_query->where('published', 1)
            ->where(function ($q) use ($query) {
                foreach (explode(' ', trim($query)) as $word) {
                    $q->where('name', 'like', '%' . $word . '%')
                        ->orWhere('tags', 'like', '%' . $word . '%')
                        ->orWhereHas('product_translations', function ($q) use ($word) {
                            $q->where('name', 'like', '%' . $word . '%');
                        })
                        ->orWhereHas('stocks', function ($q) use ($word) {
                            $q->where('sku', 'like', '%' . $word . '%');
                        });
                }
            });
        $case1 = $query . '%';
        $case2 = '%' . $query . '%';

        $products_query->orderByRaw("CASE
                WHEN name LIKE '$case1' THEN 1
                WHEN name LIKE '$case2' THEN 2
                ELSE 3
                END");

        // $priorityOrder = $products_query->orderByRaw("ISNULL(search_order), search_order ASC");
        $priorityOrder = $products_query->orderByRaw("COALESCE(search_order, 9999) ASC");
        $products = $priorityOrder->limit(3)->get();

        $categories = Category::where('name', 'like', '%' . $query . '%')->get()->take(3);

        $shops = Shop::whereIn('user_id', verified_sellers_id())->where('name', 'like', '%' . $query . '%')->get()->take(3);

        if (sizeof($keywords) > 0 || sizeof($categories) > 0 || sizeof($products) > 0 || sizeof($shops) > 0) {
            return view('frontend.partials.search_content', compact('products', 'categories', 'keywords', 'shops'));
        }
        return '0';
    }
    


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $search = Search::where('query', $request->keyword)->first();
        if ($search != null) {
            $search->count = $search->count + 1;
            $search->save();
        } else {
            $search = new Search;
            $search->query = $request->keyword;
            $search->save();
        }
    }
}
