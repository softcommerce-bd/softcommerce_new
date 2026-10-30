<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\ResourceCollection;

class SliderCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function ($data) {
                return [
                    'photo' => uploaded_asset($data['image']),
                    'url' => $data['link'],
                    'title_one' => $data['title_one'] ?? '',
                    'title_two' => $data['title_two'] ?? '',
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