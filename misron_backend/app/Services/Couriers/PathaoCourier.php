<?php

namespace App\Services\Couriers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PathaoCourier implements CourierInterface
{
    protected function settings()
    {
        $settings = \App\Models\PathaoCourierSetting::first();

        if (!$settings) {
            throw new \Exception('Pathao settings not configured');
        }

        return $settings;
    }

    protected function baseUrl()
    {
        return rtrim($this->settings()->base_url, '/');
    }

    /**
     * Issue-token is rate limited by Pathao, so the token is cached.
     * Pathao tokens live 5 days; 50 minutes matches the existing
     * pathao_access_token cache used elsewhere in the app.
     */
    protected function accessToken()
    {
        $token = Cache::get('pathao_access_token');

        if ($token) {
            return $token;
        }

        $settings = $this->settings();

        $response = Http::timeout(15)
            ->acceptJson()
            ->post($this->baseUrl() . '/aladdin/api/v1/issue-token', [
                'client_id'     => $settings->client_id,
                'client_secret' => $settings->client_secret,
                'username'      => $settings->username,
                'password'      => $settings->password,
                'grant_type'    => 'password',
            ]);

        if (!$response->successful() || !isset($response->json()['access_token'])) {
            Log::error('Pathao Auth Failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            throw new \Exception('Pathao authentication failed');
        }

        $token = $response->json()['access_token'];

        Cache::put('pathao_access_token', $token, now()->addMinutes(50));

        return $token;
    }

    protected function request()
    {
        return Http::timeout(30)
            ->acceptJson()
            ->withToken($this->accessToken());
    }

    public function createShipment($order, array $override = [])
    {
        $settings = $this->settings();
        $shipping = json_decode($order->shipping_address, true);

        if (!$shipping || !isset($shipping['name'], $shipping['phone'], $shipping['address'])) {
            throw new \Exception('Invalid shipping address format');
        }

        if (!$settings->store_id) {
            throw new \Exception('Pathao store_id not configured');
        }

        $payload = [
            'store_id'            => (int) $settings->store_id,
            'merchant_order_id'   => (string) $order->code,
            'recipient_name'      => $shipping['name'],
            'recipient_phone'     => $this->formatPhone($shipping['phone']),
            'recipient_address'   => $shipping['address'],
            'delivery_type'       => 48, // 48 = normal delivery, 12 = on demand
            'item_type'           => 2,  // 2 = parcel, 1 = document
            'item_quantity'       => $this->itemQuantity($order),
            'item_weight'         => $this->itemWeight($order),
            'amount_to_collect'   => $order->due_amount > 0
                ? (int) round($order->due_amount)
                : (int) round($order->grand_total),
            'item_description'    => $order->order_note ?? $order->additional_info ?? '',
        ];

        // recipient_city/zone/area are optional: Pathao resolves them from
        // recipient_address when omitted. So only send them when an admin
        // explicitly picked them, and never send a null (the API rejects null).
        if (!empty($override['city_id'])) {
            $payload['recipient_city'] = (int) $override['city_id'];
        }
        if (!empty($override['zone_id'])) {
            $payload['recipient_zone'] = (int) $override['zone_id'];
        }
        if (!empty($override['area_id'])) {
            $payload['recipient_area'] = (int) $override['area_id'];
        }

        $response = $this->request()->post($this->baseUrl() . '/aladdin/api/v1/orders', $payload);

        if (!$response->successful()) {
            Log::error('Pathao Create Order Failed', [
                'order_id' => $order->id,
                'status'   => $response->status(),
                'body'     => $response->body(),
                'payload'  => $payload,
            ]);

            $message = $response->json()['message'] ?? 'Pathao API failed';

            throw new \Exception("Pathao create order failed: {$message}");
        }

        $data = $response->json()['data'] ?? [];

        if (empty($data['consignment_id'])) {
            throw new \Exception('Pathao returned no consignment_id');
        }

        return [
            'consignment_id' => $data['consignment_id'],
            'tracking_code'  => $data['consignment_id'],
            'status'         => $data['order_status'] ?? 'pending',
        ];
    }

    public function getStatus($consignmentId)
    {
        try {
            $response = $this->request()
                ->get($this->baseUrl() . '/aladdin/api/v1/orders/' . $consignmentId . '/info');

            if (!$response->successful()) {
                Log::error('Pathao Order Status Failed', [
                    'consignment_id' => $consignmentId,
                    'status'         => $response->status(),
                    'body'           => $response->body(),
                ]);

                return 'error';
            }

            return $response->json()['data']['order_status'] ?? 'unknown';
        } catch (\Throwable $e) {
            Log::error('Pathao Order Status Error: ' . $e->getMessage());

            return 'error';
        }
    }

    /**
     * Pathao requires numeric city and zone ids. Orders do not store a real
     * city: the 'city' field only ever holds a shipping band ("Inside Dhaka
     * City" / "Outside Dhaka City" / "pos"), so the actual location has to be
     * read out of the free-form address text instead.
     *
     * An unresolved address throws rather than guessing, because a wrong city
     * id silently ships the parcel to the wrong district.
     */
    protected function resolveLocation(array $shipping)
    {
        $address = strtolower(trim((string) ($shipping['address'] ?? '')));
        $band    = strtolower(trim((string) ($shipping['city'] ?? '')));

        if ($address === '' || $address === 'guest-address') {
            throw new \Exception('Shipping address too vague to resolve a Pathao city');
        }

        $cities = $this->cityList();
        $cityId = $this->matchByName($cities, $address, true);

        // "Inside Dhaka City" is authoritative when the address itself names
        // no known city.
        if (!$cityId && str_contains($band, 'inside dhaka')) {
            $cityId = $this->matchByName($cities, 'dhaka');
        }

        if (!$cityId) {
            throw new \Exception(
                "Could not resolve a Pathao city from address '{$address}'. Set the delivery city on this order."
            );
        }

        $zones  = $this->zoneList($cityId);
        $zoneId = $this->matchByName($zones, $address, true);

        if (!$zoneId) {
            throw new \Exception(
                "Could not resolve a Pathao zone from address '{$address}'. Set the delivery zone on this order."
            );
        }

        return [
            'city_id' => $cityId,
            'zone_id' => $zoneId,
            'area_id' => null,
        ];
    }

    /**
     * Public wrappers so the admin panel can populate its city/zone pickers.
     */
    public function cities()
    {
        return $this->cityList();
    }

    public function zones($cityId)
    {
        return $this->zoneList($cityId);
    }

    protected function cityList()
    {
        return Cache::remember('pathao_city_list', now()->addDay(), function () {
            $response = $this->request()->get($this->baseUrl() . '/aladdin/api/v1/city-list');

            if (!$response->successful()) {
                throw new \Exception('Pathao city list failed');
            }

            return collect($response->json()['data']['data'] ?? [])
                ->map(fn($c) => ['id' => $c['city_id'], 'name' => $c['city_name']])
                ->all();
        });
    }

    protected function zoneList($cityId)
    {
        return Cache::remember("pathao_zone_list_{$cityId}", now()->addDay(), function () use ($cityId) {
            $response = $this->request()
                ->get($this->baseUrl() . "/aladdin/api/v1/cities/{$cityId}/zone-list");

            if (!$response->successful()) {
                throw new \Exception('Pathao zone list failed');
            }

            return collect($response->json()['data']['data'] ?? [])
                ->map(fn($z) => ['zone_id' => $z['zone_id'], 'name' => $z['zone_name']])
                ->all();
        });
    }

    /**
     * $haystack mode searches the needle text for any known name,
     * used to pull a zone out of a free-form street address.
     */
    protected function matchByName(array $list, $needle, $haystack = false)
    {
        $needle = strtolower(trim($needle));

        if ($needle === '') {
            return null;
        }

        $key = isset($list[0]['zone_id']) ? 'zone_id' : 'id';

        if ($haystack) {
            $best = null;

            foreach ($list as $item) {
                $name = strtolower($item['name']);

                if (str_contains($needle, $name)) {
                    // Prefer the longest match so "Mirpur 10" beats "Mirpur".
                    if (!$best || strlen($name) > strlen($best['name'])) {
                        $best = ['id' => $item[$key], 'name' => $name];
                    }
                }
            }

            return $best['id'] ?? null;
        }

        foreach ($list as $item) {
            if (strtolower($item['name']) === $needle) {
                return $item[$key];
            }
        }

        foreach ($list as $item) {
            $name = strtolower($item['name']);

            if (str_contains($name, $needle) || str_contains($needle, $name)) {
                return $item[$key];
            }
        }

        return null;
    }

    protected function formatPhone($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        $phone = substr($phone, -11);

        if (!preg_match('/^01[0-9]{9}$/', $phone)) {
            throw new \Exception("Invalid phone number format: {$phone}");
        }

        return $phone;
    }

    protected function itemQuantity($order)
    {
        return max(1, (int) $order->orderDetails->sum('quantity'));
    }

    protected function itemWeight($order)
    {
        $totalWeightKg = 0;

        foreach ($order->orderDetails as $detail) {
            $product = $detail->product;

            if (!$product) {
                continue;
            }

            $weight = (float) $product->weight;

            $totalWeightKg += $product->weight_unit === 'gm'
                ? ($weight * $detail->quantity) / 1000
                : $weight * $detail->quantity;
        }

        return round(max(0.5, min($totalWeightKg, 10.0)), 2);
    }
}
