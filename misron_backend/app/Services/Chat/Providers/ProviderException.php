<?php

namespace App\Services\Chat\Providers;

use RuntimeException;

class ProviderException extends RuntimeException
{
    public const AUTH = 'auth';          // token expired / revoked: account needs reconnecting
    public const RETRY = 'retry';        // rate limit or provider outage: try again later
    public const REJECTED = 'rejected';  // provider refused this message: retrying will not help
    public const UNAVAILABLE = 'unavailable'; // capability missing (approval, window, unsupported)

    public $kind;

    public function __construct(string $message, string $kind = self::REJECTED)
    {
        parent::__construct($message);
        $this->kind = $kind;
    }
}
