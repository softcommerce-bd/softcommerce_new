<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\ResourceCollection;

class BlogCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function ($data) {
                // Get banner image URL
                $banner_url = null;
                if (!empty($data->banner) && file_exists(public_path('uploads/all/' . $data->banner))) {
                    $banner_url = uploaded_asset($data->banner);
                }
                
                return [
                    'id' => $data->id,
                    'category_id' => $data->category_id,
                    'title' => $data->title,
                    'slug' => $data->slug,
                    'short_description' => $data->short_description,
                    'description' => $data->description,
                    'banner' =>  uploaded_asset($data->banner),
                    'banner_id' => $data->banner,
                    'meta_title' => $data->meta_title,
                    'meta_img' => $data->meta_img ? uploaded_asset($data->meta_img) : null,
                    'meta_description' => $data->meta_description,
                    'meta_keywords' => $data->meta_keywords,
                    'status' => (bool) $data->status,
                    'created_at' => $data->created_at ? $data->created_at->format('Y-m-d H:i:s') : null,
                    'updated_at' => $data->updated_at ? $data->updated_at->format('Y-m-d H:i:s') : null,
                    'category' => $data->category ? [
                        'id' => $data->category->id,
                        'category_name' => $data->category->category_name,
                        'slug' => $data->category->slug,
                    ] : null,
                    'reading_time' => $this->calculateReadingTime($data->description),
                    'excerpt' => $this->getExcerpt($data->description, 100),
                    'links' => [
                        'details' => route('blogs.show', ['slug' => $data->slug]),
                    ]
                ];
            }),
            'pagination' => [
                'current_page' => $this->resource->currentPage(),
                'last_page' => $this->resource->lastPage(),
                'per_page' => $this->resource->perPage(),
                'total' => $this->resource->total(),
                'next_page_url' => $this->resource->nextPageUrl(),
                'prev_page_url' => $this->resource->previousPageUrl(),
            ],
            'filters' => [
                'search' => $request->get('search'),
                'selected_categories' => $request->get('selected_categories', []),
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

    /**
     * Calculate estimated reading time in minutes
     */
    private function calculateReadingTime($content)
    {
        if (empty($content)) {
            return 1;
        }
        
        $wordCount = str_word_count(strip_tags($content));
        $readingTime = ceil($wordCount / 200); // Average reading speed: 200 words per minute
        
        return max(1, $readingTime);
    }

    /**
     * Get excerpt from description
     */
    private function getExcerpt($content, $length = 100)
    {
        if (empty($content)) {
            return '';
        }
        
        $cleanText = strip_tags($content);
        if (strlen($cleanText) <= $length) {
            return $cleanText;
        }
        
        return substr($cleanText, 0, $length) . '...';
    }
}