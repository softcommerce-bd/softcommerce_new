<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
//   public function toArray($request)
//     {
//         return [
//             'id'           => $this->id,
//             'category_name'         => $this->name,
//             'Category_slug'         => $this->slug,
//             'banner'         => uploaded_asset($this->banner),
//             'icon'         => uploaded_asset($this->icon),
//             'cover_image'  => $this->coverImage ? uploaded_asset($this->coverImage->file_name) : null,
//             'products'     => ProductResource::collection(get_cached_products($this->id)),
//         ];
//     }


    // public function toArray($request)
    //     {
    //         // Decode JSON strings (if stored that way in DB)
    //         $image_ids = json_decode($this->header_banner_images, true) ?? [];
    //         $link_values = json_decode($this->header_banner_links, true) ?? [];
    
    //         // Map image IDs to URLs using `uploaded_asset`
    //         $header_banners = [];
    //         foreach ($image_ids as $key => $media_id) {
    //             $header_banners[] = [
    //                 'image' => $media_id ? uploaded_asset($media_id) : null,
    //                 'link'  => $link_values[$key] ?? null,
    //             ];
    //         }
    
    //         return [
    //             'id'              => $this->id,
    //             'category_name'   => $this->name,
    //             'category_slug'   => $this->slug,
    //             'banner'          => uploaded_asset($this->banner),
    //             'icon'            => uploaded_asset($this->icon),
    //             'cover_image'     => $this->coverImage ? uploaded_asset($this->coverImage->file_name) : null,
    //             'header_banners'  => $header_banners,
    //             'products'        => ProductResource::collection(get_cached_products($this->id)),
    //         ];
    //     }
    
    public function toArray($request)
{
    // Decode banner image/link arrays
    $image_ids = json_decode($this->header_banner_images, true) ?? [];
    $link_values = json_decode($this->header_banner_links, true) ?? [];

    // Map banner images to links
    $header_banners = [];
    foreach ($image_ids as $key => $media_id) {
        $header_banners[] = [
            'image' => $media_id ? uploaded_asset($media_id) : null,
            'link'  => $link_values[$key] ?? null,
        ];
    }

    // Get cached products and paginate manually
    $allProducts = get_cached_products($this->id);
    $perPage = request()->input('per_page', 24);
    $page = \Illuminate\Pagination\Paginator::resolveCurrentPage();
    $offset = ($page - 1) * $perPage;

    $paginatedProducts = new \Illuminate\Pagination\LengthAwarePaginator(
        $allProducts->slice($offset, $perPage)->values(),
        $allProducts->count(),
        $perPage,
        $page,
        ['path' => request()->url(), 'query' => request()->query()]
    );

    return [
        'id'              => $this->id,
        'category_name'   => $this->name,
        'category_slug'   => $this->slug,
        'banner'          => uploaded_asset($this->banner),
        'icon'            => uploaded_asset($this->icon),
        'cover_image'     => $this->coverImage ? uploaded_asset($this->coverImage->file_name) : null,
        'header_banners'  => $header_banners,
        'products'        => ProductResource::collection($paginatedProducts),
        'products_pagination' => [
            'total'        => $paginatedProducts->total(),
            'per_page'     => $paginatedProducts->perPage(),
            'current_page' => $paginatedProducts->currentPage(),
            'last_page'    => $paginatedProducts->lastPage(),
        ],
    ];
}

}
