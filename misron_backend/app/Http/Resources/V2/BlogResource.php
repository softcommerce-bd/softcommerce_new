<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

class BlogResource extends JsonResource
{
    public function toArray($request)
    {
        // Get banner image URL
        $banner_url = null;
        if (!empty($this->banner) && file_exists(public_path('uploads/all/' . $this->banner))) {
            $banner_url = uploaded_asset($this->banner);
        }
        
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'banner' => uploaded_asset($this->banner),
            'banner_id' => $this->banner,
            'meta_title' => $this->meta_title,
            'meta_img' => $this->meta_img ? uploaded_asset($this->meta_img) : null,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'status' => (bool) $this->status,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'category_name' => $this->category->category_name,
                'slug' => $this->category->slug,
            ] : null,
            'reading_time' => $this->calculateReadingTime($this->description),
            'word_count' => str_word_count(strip_tags($this->description)),
            'links' => [
                'details' => route('blogs.show', ['slug' => $this->slug]),
                
            ]
        ];
    }

    public function with($request)
    {
        return [
            'success' => true,
            'status' => 200
        ];
    }

    private function calculateReadingTime($content)
    {
        if (empty($content)) {
            return 1;
        }
        
        $wordCount = str_word_count(strip_tags($content));
        $readingTime = ceil($wordCount / 200);
        
        return max(1, $readingTime);
    }
}