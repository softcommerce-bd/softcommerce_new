<?php

namespace App\Services\Inventory\Exceptions;

use RuntimeException;

/** An order cannot leave the warehouse: not enough physical stock is allocated to it. Nothing was changed. */
class InsufficientPhysicalStock extends RuntimeException
{
    public $context;

    public function __construct(string $message, array $context = [])
    {
        parent::__construct($message);
        $this->context = $context;
    }
}
