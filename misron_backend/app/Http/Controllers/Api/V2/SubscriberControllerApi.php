<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubscriberControllerApi extends Controller
{
    /**
     * Display a listing of subscribers.
     */
    public function index()
    {
        $subscribers = Subscriber::all();
        return response()->json([
            'success' => true,
            'data' => $subscribers
        ]);
    }

    /**
     * Store a newly created subscriber.
     */
    public function store(Request $request): JsonResponse
    {
        // Validate the request
        $request->validate([
            'email' => 'required|email|max:255'
        ]);

        // Check if subscriber already exists
        $subscriber = Subscriber::where('email', $request->email)->first();
        
        if ($subscriber == null) {
            // Create new subscriber
            $subscriber = new Subscriber();
            $subscriber->email = $request->email;
            $subscriber->save();
            
            return response()->json([
                'success' => true,
                'message' => 'You have subscribed successfully',
                'data' => $subscriber
            ], 201);
        } else {
            return response()->json([
                'success' => true,
                'message' => 'You are already a subscriber',
                'data' => $subscriber
            ], 200);
        }
    }

    /**
     * Display the specified subscriber.
     */
    public function show($id)
    {
        $subscriber = Subscriber::find($id);
        
        if (!$subscriber) {
            return response()->json([
                'success' => false,
                'message' => 'Subscriber not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $subscriber
        ]);
    }

    /**
     * Update the specified subscriber.
     */
    public function update(Request $request, $id)
    {
        $subscriber = Subscriber::find($id);
        
        if (!$subscriber) {
            return response()->json([
                'success' => false,
                'message' => 'Subscriber not found'
            ], 404);
        }
        
        $request->validate([
            'email' => 'required|email|max255|unique:subscribers,email,' . $id
        ]);
        
        $subscriber->email = $request->email;
        $subscriber->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Subscriber updated successfully',
            'data' => $subscriber
        ]);
    }

    /**
     * Remove the specified subscriber.
     */
    public function destroy($id)
    {
        $subscriber = Subscriber::find($id);
        
        if (!$subscriber) {
            return response()->json([
                'success' => false,
                'message' => 'Subscriber not found'
            ], 404);
        }
        
        $subscriber->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Subscriber deleted successfully'
        ]);
    }
}
