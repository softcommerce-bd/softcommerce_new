<?php

namespace App\Services\Couriers;

class CourierManager
{
    public static function driver($courier)
    {
        return match ($courier) {
            'steadfast' => new SteadfastCourier(),
            'pathao' => new PathaoCourier(),
            default => throw new \Exception("Unsupported courier"),
        };
    }

    public static function createShipment($courier, $order, array $override = [])
    {
        $driver = self::driver($courier);

        // Only Pathao needs a city/zone override; other drivers keep the
        // single-argument signature.
        return $override
            ? $driver->createShipment($order, $override)
            : $driver->createShipment($order);
    }
    public static function getStatus($courier, $trackingId)
    {
        return self::driver($courier)->getStatus($trackingId);
    }
}