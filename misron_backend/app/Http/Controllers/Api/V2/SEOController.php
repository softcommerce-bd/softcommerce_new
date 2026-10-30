<?php

namespace App\Http\Controllers\api\v2;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Blog;
use App\Models\Brand;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SEOController extends Controller
{
    /**
     * Get general SEO settings for the website
     */
    public function getSettings()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'website_name' => get_setting('website_name'),
                    'site_motto' => get_setting('site_motto'),
                    'meta_title' => get_setting('meta_title'),
                    'meta_description' => get_setting('meta_description'),
                    'meta_keywords' => get_setting('meta_keywords'),
                    'meta_image' => uploaded_asset(get_setting('meta_image')),
                    'site_icon' => uploaded_asset(get_setting('site_icon')),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching SEO settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch SEO settings'
            ], 500);
        }
    }

    /**
     * Get SEO data for a specific product
     */
    public function getProductSEO($id, $slug)
    {
        try {
            $product = Product::with('category')->findOrFail($id);

            // Prepare product images
            $thumbnailImage = $product->thumbnail_img ? uploaded_asset($product->thumbnail_img) : null;
            $metaImage = $product->meta_img ? uploaded_asset($product->meta_img) : $thumbnailImage;

            // Get gallery images
            $galleryImages = [];
            if ($product->photos && is_array(json_decode($product->photos, true))) {
                $photos = json_decode($product->photos, true);
                foreach ($photos as $photo) {
                    $galleryImages[] = uploaded_asset($photo);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'meta_title' => $product->meta_title ?: $product->name,
                    'meta_description' => $product->meta_description ?: $product->short_description,
                    'meta_keywords' => $product->meta_keywords,
                    'meta_image' => $metaImage,
                    'thumbnail_image' => $thumbnailImage,
                    'gallery_images' => $galleryImages,
                    'price' => $product->unit_price,
                    'currency' => get_setting('currency_symbol', '$'),
                    'in_stock' => $product->stock > 0,
                    'brand_name' => $product->brand ? $product->brand->name : null,
                    'category_name' => $product->category ? $product->category->name : null,
                    'category_slug' => $product->category ? $product->category->slug : null,
                    'short_description' => $product->short_description,
                    'description' => $product->description,
                    'rating' => $product->rating,
                    'reviews_count' => $product->reviews_count,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching product SEO: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }
    }

    /**
     * Get SEO data for a specific category
     */
    public function getCategorySEO($id, $slug)
    {
        try {
            $category = Category::findOrFail($id);

            // Check if slug matches
            if ($category->slug !== $slug) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category slug mismatch'
                ], 404);
            }

            $metaImage = $category->meta_img ? uploaded_asset($category->meta_img) : ($category->banner ? uploaded_asset($category->banner) : null);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'meta_title' => $category->meta_title ?: $category->name,
                    'meta_description' => $category->meta_description ?: $category->description,
                    'meta_keywords' => $category->meta_keywords,
                    'meta_image' => $metaImage,
                    'banner_image' => $category->banner ? uploaded_asset($category->banner) : null,
                    'description' => $category->description,
                    'parent_id' => $category->parent_id,
                    'parent_name' => $category->parent ? $category->parent->name : null,
                    'level' => $category->level,
                    'products_count' => $category->products()->count(),
                    'created_at' => $category->created_at,
                    'updated_at' => $category->updated_at,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching category SEO: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }
    }

    /**
     * Get SEO data for a specific blog post
     */
    public function getBlogSEO($id, $slug)
    {
        try {
            $blog = Blog::with('category')->findOrFail($id);

            // Check if slug matches
            if ($blog->slug !== $slug) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blog slug mismatch'
                ], 404);
            }

            $metaImage = $blog->meta_img ? uploaded_asset($blog->meta_img) : ($blog->banner ? uploaded_asset($blog->banner) : null);

            // Parse tags if stored as JSON or comma-separated
            $tags = [];
            if ($blog->tags) {
                if (is_array($blog->tags)) {
                    $tags = $blog->tags;
                } elseif (is_string($blog->tags)) {
                    $tags = explode(',', $blog->tags);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $blog->id,
                    'title' => $blog->title,
                    'slug' => $blog->slug,
                    'meta_title' => $blog->meta_title ?: $blog->title,
                    'meta_description' => $blog->meta_description ?: $blog->short_description,
                    'meta_keywords' => $blog->meta_keywords,
                    'meta_image' => $metaImage,
                    'featured_image' => $blog->banner ? uploaded_asset($blog->banner) : null,
                    'excerpt' => $blog->short_description,
                    'content' => $blog->content,
                    'author' => $blog->author ? $blog->author->name : 'Admin',
                    'author_name' => $blog->author ? $blog->author->name : 'Admin',
                    'tags' => $tags,
                    'category' => $blog->category ? $blog->category->name : null,
                    'category_slug' => $blog->category ? $blog->category->slug : null,
                    'views_count' => $blog->views_count ?? 0,
                    'comments_count' => $blog->comments_count ?? 0,
                    'created_at' => $blog->created_at,
                    'updated_at' => $blog->updated_at,
                    'published_at' => $blog->published_at ?? $blog->created_at,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching blog SEO: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Blog post not found'
            ], 404);
        }
    }

    /**
     * Get SEO data for a specific static page
     */
    public function getPageSEO($slug)
    {
        try {
            $page = Page::where('slug', $slug)->first();

            if (!$page) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page not found'
                ], 404);
            }

            $metaImage = $page->meta_img ? uploaded_asset($page->meta_img) : ($page->banner ? uploaded_asset($page->banner) : null);

            // Parse tags if stored as JSON or comma-separated
            $tags = [];
            if ($page->tags) {
                if (is_array($page->tags)) {
                    $tags = $page->tags;
                } elseif (is_string($page->tags)) {
                    $tags = explode(',', $page->tags);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $page->id,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'content' => $page->content,
                    'excerpt' => $page->excerpt,
                    'meta_title' => $page->meta_title ?: $page->title,
                    'meta_description' => $page->meta_description ?: $page->excerpt,
                    'meta_keywords' => $page->meta_keywords,
                    'meta_image' => $metaImage,
                    'featured_image' => $page->banner ? uploaded_asset($page->banner) : null,
                    'author' => $page->author,
                    'author_name' => $page->author_name,
                    'tags' => $tags,
                    'category' => $page->category,
                    'status' => $page->status,
                    'views_count' => $page->views_count ?? 0,
                    'created_at' => $page->created_at,
                    'updated_at' => $page->updated_at,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching page SEO: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch page data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get SEO data for a specific brand
     * (Optional - add if you have brands)
     */
    public function getBrandSEO($id, $slug)
    {
        try {
            $brand = Brand::findOrFail($id);

            if ($brand->slug !== $slug) {
                return response()->json([
                    'success' => false,
                    'message' => 'Brand slug mismatch'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'slug' => $brand->slug,
                    'meta_title' => $brand->meta_title ?: $brand->name,
                    'meta_description' => $brand->meta_description,
                    'meta_keywords' => $brand->meta_keywords,
                    'meta_image' => $brand->meta_img ? uploaded_asset($brand->meta_img) : null,
                    'logo' => $brand->logo ? uploaded_asset($brand->logo) : null,
                    'description' => $brand->description,
                    'products_count' => $brand->products()->count(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching brand SEO: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Brand not found'
            ], 404);
        }
    }

    /**
     * Get SEO data for homepage
     * (Optional - if you need homepage SEO)
     */
    public function getHomepageSEO()
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'title' => get_setting('website_name'),
                    'meta_title' => get_setting('meta_title'),
                    'meta_description' => get_setting('meta_description'),
                    'meta_keywords' => get_setting('meta_keywords'),
                    'meta_image' => uploaded_asset(get_setting('meta_image')),
                    'site_name' => get_setting('website_name'),
                    'site_motto' => get_setting('site_motto'),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching homepage SEO: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch homepage SEO'
            ], 500);
        }
    }

    /**
     * Get SEO data for search page
     * (Optional - if you need search page SEO)
     */
    public function getSearchSEO(Request $request)
    {
        try {
            $query = $request->get('q', '');

            return response()->json([
                'success' => true,
                'data' => [
                    'title' => $query ? "Search results for: {$query}" : "Search Products",
                    'meta_title' => $query ? "Search: {$query} | " . get_setting('website_name') : "Search | " . get_setting('website_name'),
                    'meta_description' => $query ? "Find {$query} at best prices in our store" : "Search for products in our online store",
                    'meta_keywords' => $query ? "search, {$query}, products" : "search, products, shopping",
                    'query' => $query,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching search SEO: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch search SEO'
            ], 500);
        }
    }
}
