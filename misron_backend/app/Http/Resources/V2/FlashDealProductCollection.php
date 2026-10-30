<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\ResourceCollection;

class FlashDealProductCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function($data) {
                return [
                    'id' => $data->product_id,
                    'slug' => $data->product->slug,
                    'sales_name' => $data->product->sales_name ?? '',
                    'name' => $data->product->name,
                    'image' => uploaded_asset($data->product->thumbnail_img),
                    'price' => home_discounted_base_price($data->product),
                    'recent_stock' => stocks_check($data->product_id) ?? 0,
                    'sales' => (int) $data->product->num_of_sale ?? 0,
                    'links' => [
                        'details' => route('products.show', $data->product_id),
                    ]
                ];
            })
        ];
    }
}
