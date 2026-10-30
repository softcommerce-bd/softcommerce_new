<?php

namespace App\Services\Couriers;

interface CourierInterface
{
    public function createShipment($order);

    public function getStatus($trackingId);
}
