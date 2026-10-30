<?php

namespace App\Services\Inventory\Exceptions;

/** Other orders kept emptying the same batches; the caller may try again. */
class InventoryBusy extends InventoryException
{
    public function errorCode(): string
    {
        return 'inventory_busy';
    }
}
