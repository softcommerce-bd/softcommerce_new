<?php

namespace App\Http\Controllers\Api\V2;


use App\Models\Search;
use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Shop;
use Illuminate\Http\Request;

class SearchSuggestionController extends Controller
{

    public function getList(Request $request)
{
    $query_key = $request->query_key ?? $request->search ?? $request->q;
    $type = $request->type ?? 'product'; // Default to product search

    $items = [];

    // Search for products
    if ($type == "product" || $type == "all") {
        $product_query = Product::where('published', 1);

        if ($query_key != "") {
            $product_query->where(function ($query) use ($query_key) {
                foreach (explode(' ', trim($query_key)) as $word) {
                    $query->where('name', 'like', '%' . $word . '%')
                        ->orWhere('tags', 'like', '%' . $word . '%')
                        ->orWhereHas('product_translations', function($q) use ($word) {
                            $q->where('name', 'like', '%' . $word . '%');
                        })
                        // Product code: the SKU on any of the product's variants.
                        ->orWhereHas('stocks', function($q) use ($word) {
                            $q->where('sku', 'like', '%' . $word . '%');
                        });
                }
            });
        }

        $products = filter_products($product_query)->limit(5)->get();

        foreach ($products as $product) {
            $item = [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'thumbnail_image' => uploaded_asset($product->thumbnail_img),
                'main_price' => single_price($product->unit_price),
                'unit_price' => $product->unit_price,
                'type' => "product",
                'type_string' => "Product"
            ];
            $items[] = $item;
        }
    }

    // Search for brands
    if ($type == "brands" || $type == "all") {
        $brand_query = Brand::query();
        if ($query_key != "") {
            $brand_query->where('name', 'like', "%$query_key%");
        }
        $brands = $brand_query->limit(3)->get();

        foreach ($brands as $brand) {
            $item = [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'logo' => uploaded_asset($brand->logo),
                'type' => "brand",
                'type_string' => "Brand"
            ];
            $items[] = $item;
        }
    }

    // Search for categories
    if ($type == "categories" || $type == "all") {
        $category_query = Category::query();
        if ($query_key != "") {
            $category_query->where('name', 'like', "%$query_key%");
        }
        $categories = $category_query->limit(3)->get();

        foreach ($categories as $category) {
            $item = [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'type' => "category",
                'type_string' => "Category"
            ];
            $items[] = $item;
        }
    }

    return response()->json([
        'success' => true,
        'data' => $items,
        'total' => count($items)
    ], 200);
}
}
