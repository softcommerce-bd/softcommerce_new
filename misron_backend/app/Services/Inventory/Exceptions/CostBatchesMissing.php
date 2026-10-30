<?php

namespace App\Services\Inventory\Exceptions;

/**
 * The stock row has the units but the purchase batches that record their cost
 * do not. Selling would leave the order without a buying price, so the sale is
 * refused until somebody has checked the product's inventory.
 */
class CostBatchesMissing extends InventoryException
{
    public function errorCode(): string
    {
        return 'inventory_unverified';
    }
}
