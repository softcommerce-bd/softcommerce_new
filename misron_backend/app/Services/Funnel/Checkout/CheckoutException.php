<?php

namespace App\Services\Funnel\Checkout;

use RuntimeException;

/**
 * A refusal the checkout explains to the customer. `errorCode()` is stable (the
 * page chooses its wording from it), the message is an English fallback, `errors()`
 * maps form fields to their own problem, and `context()` carries facts such as the
 * fresh quote after a price change.
 */
class CheckoutException extends RuntimeException
{
    private string $errorCode;
    private int $status;
    private array $errors;
    private array $context;

    public function __construct(string $errorCode, string $message, int $status = 422, array $errors = [], array $context = [])
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->status = $status;
        $this->errors = $errors;
        $this->context = $context;
    }

    /** @param array<string, array{code: string, message: string}> $errors */
    public static function invalid(array $errors): self
    {
        return new self('validation_failed', 'Please check the highlighted fields.', 422, $errors);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function context(): array
    {
        return $this->context;
    }

    public function toArray(): array
    {
        return array_filter([
            'result' => false,
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
            'errors' => $this->errors ?: null,
        ] + $this->context, fn ($value) => $value !== null);
    }
}
