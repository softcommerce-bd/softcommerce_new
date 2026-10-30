<?php

namespace App\Services\Inventory\Exceptions;

/** The caller asked for something that is not a stock movement (zero or huge quantity, no product). */
class InvalidStockRequest extends InventoryException
{
    public function errorCode(): string
    {
        return 'invalid_request';
    }
}
