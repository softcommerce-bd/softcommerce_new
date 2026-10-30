<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $wholesale_product = ($this->wholesale_product == 1) ? true : false;
        return [
            
            'id'        => $this->id,
            'name'      => $this->getTranslation('name'),
            'slug'      => $this->slug,
            'thumbnail_image' => uploaded_asset($this->thumbnail_img),
            'has_discount' => home_base_price($this, false) != home_discounted_base_price($this, false),
            'discount' => "-" . discount_in_percentage($this) . "%",
            'stroked_price' => home_base_price($this),
            'main_price' => home_discounted_base_price($this),
            'recent_stock' => stocks_check($this->id) ?? 0,
            'rating' => (float) $this->rating,
            'sales' => (int) $this->num_of_sale,
            'is_wholesale' => $wholesale_product,
            'links' => [
                            'details' => route('products.show', ['slug' => $this->slug, 'user_id' => $this->user_id]),
                        ],
        ];
    }
}
