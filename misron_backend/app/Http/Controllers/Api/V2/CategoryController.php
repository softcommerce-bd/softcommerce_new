<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\CategoryCollection;
use App\Models\BusinessSetting;
use App\Models\Category;
use App\Models\Product;
use Cache;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\V2\CategoryResource;

class CategoryController extends Controller
{

    /**
     * Resolve the `parent_id` query parameter, which carries a slug rather than
     * an id. An unknown slug used to dereference null and return a 500; it now
     * falls back to the root level.
     */
    protected function resolveParentId($parent_id = 0)
    {
        if (request()->has('parent_id') && request()->parent_id) {
            $category = Category::where('slug', request()->parent_id)->first();
            $parent_id = $category ? $category->id : $parent_id;
        }

        return $parent_id;
    }

    public function index($parent_id = 0)
    {
        $parent_id = $this->resolveParentId($parent_id);

        // The `order_level > 0` filter that used to be here hid every category
        // the admin had not manually ranked — visible in the admin panel,
        // missing from the site. Unranked categories now sort last instead of
        // disappearing; see Category::scopeInDisplayOrder().
        return new CategoryCollection(
            Category::where('parent_id', $parent_id)
                ->where('digital', 0)
                ->inDisplayOrder()
                ->get()
        );
    }

    /**
     * The tabs of the "Shop by category" block on the home page.
     *
     * These are the categories the admin picks under Website Setup → Homepage
     * Setting → Category Wise Products, stored as a JSON array of ids in the
     * `home_categories` setting. The previous version ignored that selection
     * and auto-picked the first four root categories in display order, so the
     * admin panel and the storefront disagreed.
     *
     * The ids are returned in the order the admin arranged them — `whereIn`
     * alone would hand back database order — and the auto-picked list is kept
     * as a fallback for a site that has not filled the setting in yet.
     */
    public function selectedCategories($parent_id = 0)
    {
        $raw = get_setting('home_categories');
        $selected = $raw ? json_decode($raw, true) : [];
        $selected = is_array($selected)
            ? array_values(array_filter(array_map('intval', $selected)))
            : [];

        if (empty($selected)) {
            $parent_id = $this->resolveParentId($parent_id);

            $categories = Category::where('parent_id', $parent_id)
                ->where('digital', 0)
                ->inDisplayOrder()
                ->limit(4)
                ->get();

            // The home tab strip shows each tab's product count.
            $this->attachProductCounts($categories);

            return new CategoryCollection($categories);
        }

        $categories = Category::whereIn('id', $selected)
            ->get()
            ->sortBy(fn ($category) => array_search($category->id, $selected))
            ->values();

        $this->attachProductCounts($categories);

        return new CategoryCollection($categories);
    }

    /**
     * The featured section on the home page.
     *
     * Every category with the featured flag set, at any depth — a child marked
     * featured appears here alongside the parents. The result is one flat run
     * in display order, so the grid fills parents first and continues into the
     * children in the same flow, with no row break between the two groups.
     */
    public function topFeaturedCategories()
    {
        $categories = Category::where('featured', 1)
            ->where('digital', 0)
            ->inDisplayOrder()
            ->get();

        $this->attachProductCounts($categories);

        return new CategoryCollection($categories);
    }

    /**
     * Set `products_count` on each category: the published physical products in
     * it and in everything beneath it.
     *
     * Those are the products the shop page lists when it is filtered to the
     * category (it widens the filter to the same descendants), so the number on
     * a featured tile matches what the visitor lands on. Two queries for the
     * whole list: the id/parent map to find descendants in memory, and one
     * grouped count over the products.
     */
    protected function attachProductCounts($categories)
    {
        if ($categories->isEmpty()) {
            return;
        }

        $parentOf = DB::table('categories')->pluck('parent_id', 'id');
        $childrenOf = $parentOf->keys()->groupBy(function ($id) use ($parentOf) {
            return (int) $parentOf[$id];
        });

        // The seen-set stops a parent loop in bad data from recursing forever.
        $subtreeIds = function ($id, array $seen = []) use (&$subtreeIds, $childrenOf) {
            $seen[$id] = true;
            foreach ($childrenOf->get($id, []) as $childId) {
                if (!isset($seen[$childId])) {
                    $seen = $subtreeIds($childId, $seen);
                }
            }
            return $seen;
        };

        $subtrees = $categories->mapWithKeys(function ($category) use ($subtreeIds) {
            return [$category->id => array_keys($subtreeIds($category->id))];
        });

        $counts = Product::where('published', 1)
            ->physical()
            ->whereIn('category_id', $subtrees->flatten()->unique()->all())
            ->groupBy('category_id')
            ->selectRaw('category_id, count(*) as aggregate')
            ->pluck('aggregate', 'category_id');

        foreach ($categories as $category) {
            $category->products_count = (int) collect($subtrees[$category->id])
                ->sum(function ($id) use ($counts) {
                    return $counts[$id] ?? 0;
                });
        }
    }

    /**
     * The whole category tree in one call, for the menu panel and the footer.
     *
     * The header used to fetch `/categories` and then one `/sub-categories/{id}`
     * per parent, so opening the menu fired a request per top-level category.
     * This returns parents in display order, each with its children nested and
     * sorted by the same rule.
     */
    public function tree()
    {
        $categories = Category::where('digital', 0)->inDisplayOrder()->get();

        // One flat pass, already in display order, regrouped by parent. Each
        // group keeps the order it came in, so the children of a parent are
        // sorted by the same rule as the parents themselves.
        $childrenByParent = $categories->groupBy('parent_id');

        // Only true roots start the tree. A category whose parent row is gone
        // would otherwise never be reachable, so it counts as a root too.
        $roots = $categories->filter(function ($category) use ($categories) {
            return empty($category->parent_id)
                || !$categories->contains('id', $category->parent_id);
        })->values();

        return response()->json([
            'success' => true,
            'status' => 200,
            'data' => $this->treeNodes($roots, $childrenByParent),
        ]);
    }

    /**
     * Serialize one level of the tree and recurse into its children.
     *
     * `$seen` guards against a parent_id cycle in the data, which would
     * otherwise recurse until the request runs out of memory.
     */
    protected function treeNodes($categories, $childrenByParent, array $seen = [])
    {
        return $categories->map(function ($category) use ($childrenByParent, $seen) {
            $children = collect();

            if (!in_array($category->id, $seen, true)) {
                $children = $this->treeNodes(
                    $childrenByParent->get($category->id, collect()),
                    $childrenByParent,
                    array_merge($seen, [$category->id])
                );
            }

            return [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->getTranslation('name'),
                'banner' => uploaded_asset($category->banner) ?: '',
                'icon' => uploaded_asset($category->icon) ?: '',
                'parent_id' => (int) $category->parent_id,
                'level' => (int) $category->level,
                'order_level' => (int) $category->order_level,
                'number_of_children' => $children->count(),
                'children' => $children->values(),
            ];
        })->values();
    }

    public function info($slug)
    {
        return new CategoryCollection(Category::where('slug', $slug)->get());
    }

    public function featured()
    {
        // return Cache::remember('app.featured_categories', 86400, function () {
            return new CategoryCollection(Category::where('featured', 1)->get());
        // });
    }

    public function home()
    {
        // return Cache::remember('app.home_categories', 86400, function () {
            return new CategoryCollection(Category::whereIn('id', json_decode(get_setting('home_categories')))->get());
        // });
    }

    public function top()
    {
        // return Cache::remember('app.top_categories', 86400, function () {
            return new CategoryCollection(Category::whereIn('id', json_decode(get_setting('home_categories')))->limit(20)->get());
        // });
    }
    
         public function homeSetectedCategory()
    {
        $raw = get_setting('home_categories');
        $selectedHomePageCategory = $raw ? json_decode($raw, true) : [];
    
        $categories = get_category($selectedHomePageCategory);
    
        return response()->json([
            'success' => true,
            'data' => CategoryResource::collection($categories),
        ]);
    }
    
   public function categorySliderBanner($slug)
{
    // Find category by slug
    $category = Category::where('slug', $slug)->first();

    if (!$category) {
        return response()->json([
            'success' => false,
            'message' => 'Category not found'
        ], 404);
    }

    // Decode JSON fields from the category
    $image_ids = json_decode($category->header_banner_images, true) ?? [];
    $link_values = json_decode($category->header_banner_links, true) ?? [];

    // Map banners with image and link
    $header_banners = [];
    foreach ($image_ids as $key => $media_id) {
        $header_banners[] = [
            'image' => $media_id ? uploaded_asset($media_id) : null,
            'link'  => $link_values[$key] ?? null,
        ];
    }

    return response()->json([
        'success' => true,
        'category' => [
            'id'    => $category->id,
            'name'  => $category->name,
            'slug'  => $category->slug,
        ],
        'banners' => $header_banners
    ]);
}


}
