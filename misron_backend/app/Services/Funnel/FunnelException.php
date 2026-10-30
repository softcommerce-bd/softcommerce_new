<?php

namespace App\Services\Funnel;

use RuntimeException;

/**
 * A funnel operation that cannot go ahead. Carries the HTTP status and a
 * machine-readable code so the editor API and the Blade admin can each
 * present it their own way.
 */
class FunnelException extends RuntimeException
{
    private $status;
    private $errorCode;
    private $details;

    public function __construct(string $message, string $errorCode, int $status = 422, array $details = [])
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->status = $status;
        $this->details = $details;
    }

    public static function invalid(ValidationResult $result): self
    {
        return new self(
            'The page has problems that need fixing first.',
            'invalid_page',
            422,
            $result->toArray()
        );
    }

    public static function conflict(int $currentRevision): self
    {
        return new self(
            'This funnel was changed in another window. Reload to get the latest version.',
            'revision_conflict',
            409,
            ['revision' => $currentRevision]
        );
    }

    public static function state(string $message): self
    {
        return new self($message, 'invalid_state', 409);
    }

    public static function slug(string $message): self
    {
        return new self($message, 'invalid_slug', 422, ['errors' => [['path' => 'slug', 'code' => 'invalid_slug', 'message' => $message]]]);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function details(): array
    {
        return $this->details;
    }
}
