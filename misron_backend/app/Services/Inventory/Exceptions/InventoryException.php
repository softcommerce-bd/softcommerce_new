<?php

namespace App\Services\Inventory\Exceptions;

use RuntimeException;

/**
 * Base class for every refusal the stock service makes. Callers map `errorCode()`
 * to a response; the code is stable, the message is for logs.
 */
abstract class InventoryException extends RuntimeException
{
    /** @var array<string, mixed> */
    private array $context;

    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->context = $context;
    }

    /** Short machine-readable reason, e.g. "out_of_stock". */
    abstract public function errorCode(): string;

    /** Facts about the refusal: product, variation, quantities asked for and available. */
    public function context(): array
    {
        return $this->context;
    }
}
