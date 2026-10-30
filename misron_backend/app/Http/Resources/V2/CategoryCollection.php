<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\ResourceCollection;
use App\Models\Category;

class CategoryCollection extends ResourceCollection
{
    /**
     * How many immediate children each category in this collection has.
     *
     * This used to call `CategoryUtility::get_immediate_children_count()` inside
     * the map, which is one COUNT query per row. A full category tree made that
     * one query per category on every menu and footer render. One grouped query
     * answers it for the whole collection instead.
     */
    protected function childCounts()
    {
        $ids = $this->collection->pluck('id')->filter()->all();

        if (empty($ids)) {
            return collect();
        }

        return Category::whereIn('parent_id', $ids)
            ->groupBy('parent_id')
            ->selectRaw('parent_id, count(*) as aggregate')
            ->pluck('aggregate', 'parent_id');
    }

    public function toArray($request)
    {
        $childCounts = $this->childCounts();

        return [
            'data' => $this->collection->map(function ($data) use ($childCounts) {
                // `uploaded_asset()` falls back to a placeholder image rather
                // than returning nothing, so the guard has to be on the column
                // itself. An unset image is sent as an empty string and the
                // frontend decides what to show in its place.
                $banner = $data->banner ? uploaded_asset($data->banner) : '';
                $icon = $data->icon ? uploaded_asset($data->icon) : '';
                // The featured grid shows the cover image, not the banner: the
                // banner is the wide strip on the category page itself.
                $cover_image = $data->cover_image ? uploaded_asset($data->cover_image) : '';
                return [
                    'id' => $data->id,
                    'slug' => $data->slug,
                    'name' => $data->getTranslation('name'),
                    'banner' => $banner,
                    'icon' => $icon,
                    'cover_image' => $cover_image,
                    // Depth and manual rank travel with every category so the
                    // frontend can group a flat list into parents and children
                    // without a second call, and without re-deriving the order.
                    'parent_id' => (int) $data->parent_id,
                    'level' => (int) $data->level,
                    'order_level' => (int) $data->order_level,
                    'number_of_children' => (int) ($childCounts[$data->id] ?? 0),
                    // Only set by endpoints that count products (the featured
                    // grid); null elsewhere rather than a misleading 0.
                    'products_count' => isset($data->products_count)
                        ? (int) $data->products_count
                        : null,
                    'links' => [
                        'products' => route('api.products.category', $data->id),
                        'sub_categories' => route('subCategories.index', $data->id)
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
