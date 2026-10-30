<?php

namespace App\Http\Controllers\api\v2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\BlogCollection;
use App\Http\Resources\V2\BlogResource;
use Illuminate\Http\Request;
use App\Models\Blog;
use App\Models\BlogCategory;

class BlogApiController extends Controller
{
    /**
     * Get all blogs with pagination and filters
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 12);
        $search = $request->get('search');
        $selectedCategories = $request->get('selected_categories', []);

        // Convert string to array if needed (for comma-separated values)
        if (is_string($selectedCategories) && !empty($selectedCategories)) {
            $selectedCategories = explode(',', $selectedCategories);
        }

        // Ensure it's an array
        if (!is_array($selectedCategories)) {
            $selectedCategories = [];
        }

        // Remove any empty values
        $selectedCategories = array_filter($selectedCategories);

        \Log::info('Blog filters:', [
            'search' => $search,
            'selected_categories' => $selectedCategories,
            'selected_categories_type' => gettype($selectedCategories)
        ]);

        $blogs = Blog::query();

        // Filter by status (only published blogs)
        $blogs->where('status', 1);

        // Search functionality
        if ($search) {
            $searchTerms = explode(' ', trim($search));
            $blogs->where(function ($q) use ($searchTerms) {
                foreach ($searchTerms as $word) {
                    $q->where('title', 'like', '%' . $word . '%')
                        ->orWhere('short_description', 'like', '%' . $word . '%');
                }
            });

            // Custom order by relevance
            $case1 = $search . '%';
            $case2 = '%' . $search . '%';
            $blogs->orderByRaw("CASE
            WHEN title LIKE '$case1' THEN 1
            WHEN title LIKE '$case2' THEN 2
            ELSE 3
            END");
        } else {
            $blogs->orderBy('created_at', 'desc');
        }

        // Filter by categories
        if (!empty($selectedCategories)) {
            $blogCategories = BlogCategory::whereIn('slug', $selectedCategories)->pluck('id')->toArray();

            \Log::info('Category filter:', [
                'selected_slugs' => $selectedCategories,
                'found_category_ids' => $blogCategories
            ]);

            if (!empty($blogCategories)) {
                $blogs->whereIn('category_id', $blogCategories);
            }
        }

        // Eager load category relationship
        $blogs = $blogs->with('category')->paginate($perPage);

        // Return the collection resource
        return new BlogCollection($blogs);
    }

    /**
     * Get single blog by slug
     *
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */

    public function show($slug)
    {
        $blog = Blog::where('slug', $slug)
            ->where('status', 1)
            ->with('category')
            ->firstOrFail();

        // Increment view count (if you have views column)
        // $blog->increment('views');

        return new BlogResource($blog);
    }


    public function recent(Request $request)
    {
        $limit = $request->get('limit', 9);

        $recentBlogs = Blog::where('status', 1)
            ->orderBy('created_at', 'desc')
            ->with('category')
            ->paginate($limit);

        return new BlogCollection($recentBlogs);
    }

    /**
     * Get all blog categories
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function categories()
    {
        $categories = BlogCategory::all();

        return response()->json([
            'success' => true,
            'data' => $categories
        ], 200);
    }

    /**
     * Get blogs by category
     *
     * @param string $categorySlug
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function byCategory($categorySlug, Request $request)
    {
        $perPage = $request->get('per_page', 12);

        $category = BlogCategory::where('slug', $categorySlug)->first();

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        $blogs = Blog::where('category_id', $category->id)
            ->where('status', 1)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'category' => $category,
                'blogs' => $blogs->items(),
                'pagination' => [
                    'current_page' => $blogs->currentPage(),
                    'last_page' => $blogs->lastPage(),
                    'per_page' => $blogs->perPage(),
                    'total' => $blogs->total(),
                ]
            ]
        ], 200);
    }

    /**
     * Search blogs
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2'
        ]);

        $query = $request->get('query');
        $perPage = $request->get('per_page', 12);

        $blogs = Blog::where('status', 1)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', '%' . $query . '%')
                    ->orWhere('short_description', 'like', '%' . $query . '%')
                    ->orWhere('description', 'like', '%' . $query . '%');
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $blogs->items(),
            'pagination' => [
                'current_page' => $blogs->currentPage(),
                'last_page' => $blogs->lastPage(),
                'per_page' => $blogs->perPage(),
                'total' => $blogs->total(),
            ],
            'search_query' => $query
        ], 200);
    }
}
