<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Page;
use Illuminate\Http\Response;

class PageController extends Controller
{
     public function show($slug)
    {
        try {
            $page = Page::where('slug', $slug)->first();
            
            if (!$page) {
                return response()->json([
                    'success' => false,
                    'message' => 'Page not found',
                    'data' => null
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'message' => 'Page retrieved successfully',
                'data' => [
                    'id' => $page->id,
                    'type' => $page->type,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'content' => $page->content,
                    'meta_title' => $page->meta_title,
                    'meta_description' => $page->meta_description,
                    'keywords' => $page->keywords,
                    'meta_image' => $page->meta_image ? asset($page->meta_image) : null,
                    'created_at' => $page->created_at,
                    'updated_at' => $page->updated_at
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching the page',
                'error' => $e->getMessage(),
                'data' => null
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
