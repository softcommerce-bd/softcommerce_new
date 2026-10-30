<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class GTMService
{
    protected $client;
    protected $measurementId;
    protected $apiSecret;
    
    public function __construct()
    {
        $this->client = new Client();
        $this->measurementId = env('GA4_MEASUREMENT_ID'); // G-XXXXXXXX
        $this->apiSecret = env('GA4_API_SECRET'); // Get from GA4 Admin
    }
    
    /**
     * Send event to GA4 Measurement Protocol
     */
    public function sendEvent($eventName, $eventData = [], $userId = null)
    {
        $url = "https://www.google-analytics.com/mp/collect";
        $url .= "?measurement_id=" . $this->measurementId;
        $url .= "&api_secret=" . $this->apiSecret;
        
        $payload = [
            'client_id' => $userId ?? $this->getClientId(),
            'events' => [
                [
                    'name' => $eventName,
                    'params' => $eventData
                ]
            ]
        ];
        
        try {
            $response = $this->client->post($url, [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                ]
            ]);
            
            if ($response->getStatusCode() == 204) {
                Log::info("GA4 Event sent: {$eventName}", $eventData);
                return true;
            }
        } catch (\Exception $e) {
            Log::error("GA4 Event failed: {$e->getMessage()}");
            return false;
        }
        
        return false;
    }
    
    /**
     * Get or generate client ID
     */
    protected function getClientId()
    {
        // Use session or generate random
        if (session()->has('ga_client_id')) {
            return session()->get('ga_client_id');
        }
        
        $clientId = uniqid() . '.' . rand(1000000000, 9999999999);
        session()->put('ga_client_id', $clientId);
        
        return $clientId;
    }
    
    /**
     * Send view_item event from backend
     */
    public function viewItem($product, $user = null)
    {
        $eventData = [
            'currency' => 'BDT',
            'value' => (float) $product->unit_price,
            'items' => [
                [
                    'item_id' => (string) $product->id,
                    'item_name' => $product->name,
                    'sku' => $product->sku ?? (string) $product->id,
                    'item_category' => $product->main_category->name ?? '',
                    'quantity' => 1,
                    'price' => (float) $product->unit_price,
                ]
            ]
        ];
        
        // Add user info if available
        if ($user) {
            $eventData['user_id'] = (string) $user->id;
            $eventData['user_email'] = $user->email;
            $eventData['user_phone'] = $user->phone;
        }
        
        return $this->sendEvent('view_item', $eventData, $user?->id);
    }
    
    /**
     * Send add_to_cart event from backend
     */
    public function addToCart($product, $quantity = 1, $user = null)
    {
        $eventData = [
            'currency' => 'BDT',
            'value' => (float) $product->unit_price * $quantity,
            'items' => [
                [
                    'item_id' => (string) $product->id,
                    'item_name' => $product->name,
                    'sku' => $product->sku ?? (string) $product->id,
                    'item_category' => $product->main_category->name ?? '',
                    'quantity' => $quantity,
                    'price' => (float) $product->unit_price,
                ]
            ]
        ];
        
        if ($user) {
            $eventData['user_id'] = (string) $user->id;
        }
        
        return $this->sendEvent('add_to_cart', $eventData, $user?->id);
    }
    
    /**
     * Send begin_checkout event from backend
     */
    public function beginCheckout($cartItems, $total, $user = null)
    {
        $items = [];
        foreach ($cartItems as $cart) {
            $items[] = [
                'item_id' => (string) $cart->product_id,
                'item_name' => $cart->product->name,
                'quantity' => $cart->quantity,
                'price' => (float) $cart->price,
            ];
        }
        
        $eventData = [
            'currency' => 'BDT',
            'value' => (float) $total,
            'items' => $items
        ];
        
        if ($user) {
            $eventData['user_id'] = (string) $user->id;
        }
        
        return $this->sendEvent('begin_checkout', $eventData, $user?->id);
    }
    
    /**
     * Send purchase event from backend
     */
    public function purchase($order, $user = null)
    {
        $items = [];
        foreach ($order->orderDetails as $detail) {
            $items[] = [
                'item_id' => (string) $detail->product_id,
                'item_name' => $detail->product->name,
                'quantity' => $detail->quantity,
                'price' => (float) ($detail->price / $detail->quantity),
            ];
        }
        
        $eventData = [
            'transaction_id' => $order->code,
            'currency' => 'BDT',
            'value' => (float) $order->grand_total,
            'tax' => (float) $order->orderDetails->sum('tax'),
            'shipping' => (float) $order->orderDetails->sum('shipping_cost'),
            'items' => $items
        ];
        
        if ($user) {
            $eventData['user_id'] = (string) $user->id;
        }
        
        return $this->sendEvent('purchase', $eventData, $user?->id);
    }
}