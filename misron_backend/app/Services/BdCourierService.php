<?php

namespace App\Services;

use App\Models\Bdcourier;
use App\Models\CourierStat;
use App\Models\User;

class BdCourierService
{
     protected $bdCourier;

    public function __construct(Bdcourier  $bdCourier)
    {
        $this->bdCourier = $bdCourier;
    }

    public function fetchData(string $phone)
    {
        $setting = $this->bdCourier->first();
        $token = $setting->token ?? env('BD_COURIER_TOKEN');

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://bdcourier.com/api/courier-check?phone=' . $phone,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$token}",
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response);
    }

    /**
     * Fetch courier stats by phone and update the user automatically.
     *
     * @param string $phone
     * @return bool
     */
    public function syncCourierStatsByPhone(string $phone): bool
    {
        $courier = CourierStat::where('phone', $phone)->first();
        if ($courier && $courier->updated_at->gt(now()->subMinutes(60))){
            return false;
        }
        $data = $this->fetchData($phone);
        if (!is_object($data) || !property_exists($data, 'status')) {
            return false;
        }

        if ($data->status !== 'success') {
            return false;
        }

        // Convert nested objects to array
        $courierStats = json_decode(json_encode($data->courierData), true);

        // Find existing record or create new
        $record = CourierStat::updateOrCreate(
            ['phone' => $phone],
            [
                'data' => $courierStats,
                'updated_at' => now(),
            ]
        );

        return true;
    }
}
