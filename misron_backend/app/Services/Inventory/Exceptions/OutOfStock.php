<?php

namespace App\Services\Inventory\Exceptions;

/** The stock row holds fewer units than were asked for. */
class OutOfStock extends InventoryException
{
    public function errorCode(): string
    {
        return 'out_of_stock';
    }

    public function available(): int
    {
        return (int) ($this->context()['available'] ?? 0);
    }
}
