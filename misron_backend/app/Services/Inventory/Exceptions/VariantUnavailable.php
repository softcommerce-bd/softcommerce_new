<?php

namespace App\Services\Inventory\Exceptions;

/** No stock row exists for this product and variation. */
class VariantUnavailable extends InventoryException
{
    public function errorCode(): string
    {
        return 'variant_unavailable';
    }
}
