<?php

namespace App\Services\Couriers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SteadfastCourier implements CourierInterface
{
    protected function settings()
    {
        return Cache::rememberForever('steadfast_settings', function () {
            return \App\Models\SteadfastSetting::first();
        });
    }

    public function createShipment($order)
    {
        $settings = $this->settings();

        $shipping = json_decode($order->shipping_address, true);

        $payload = [
            'invoice' => $order->code,
            'recipient_name' => $shipping['name'],
            'recipient_phone' => str_replace('+', '', $shipping['phone']),
            'recipient_address' => $shipping['address'],
            'cod_amount' => $order->due_amount > 0
                ? (float)$order->due_amount
                : (float)$order->grand_total,
        ];

        $response = Http::withHeaders([
            'Api-Key' => $settings->steadfast_api_key,
            'Secret-Key' => $settings->steadfast_secret_key,
        ])->post('https://portal.packzy.com/api/v1/create_order', $payload);

        if (!$response->successful()) {
            throw new \Exception("Steadfast API failed");
        }

        $data = $response->json();

        return [
            'consignment_id' => $data['consignment']['consignment_id'] ?? null,
            'tracking_code'  => $data['consignment']['tracking_code'] ?? null,
            'status'         => $data['consignment']['status'] ?? 'pending'
        ];
    }

    public function getStatus($consignmentId)
    {
        $settings = $this->settings();

        $response = Http::timeout(10)->withHeaders([
            'Api-Key'=>$settings->steadfast_api_key,
            'Secret-Key'=>$settings->steadfast_secret_key,
        ])->get("https://portal.packzy.com/api/v1/status_by_cid/{$consignmentId}");

        if (!$response->successful()) {
            return 'error';
        }

        $data = $response->json();

        return $data['delivery_status'] ?? 'unknown';
    }
}