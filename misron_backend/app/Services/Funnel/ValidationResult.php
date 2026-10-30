<?php

namespace App\Services\Funnel;

/**
 * Outcome of validating a page document: the normalized document plus every
 * error (blocks saving) and warning (informational) with its JSON path.
 */
class ValidationResult
{
    public $document;

    private $errors = [];
    private $warnings = [];

    /** @var array<string,string> offer key => path of the binding defining it */
    public $offerKeys = [];

    /** @var array<int,int> product ids referenced by bindings, keyed by id */
    public $productIds = [];

    public $nodeCount = 0;

    /**
     * Every offer on the page, as normalized: key, JSON path, the block that
     * holds it and the clean binding. Used by the publish-time catalogue check.
     *
     * @var array<int, array{key: string, path: string, blockId: string, blockType: string, binding: array}>
     */
    public $bindings = [];

    public function error(string $path, string $code, string $message): void
    {
        $this->errors[] = ['path' => $path, 'code' => $code, 'message' => $message];
    }

    public function warning(string $path, string $code, string $message): void
    {
        $this->warnings[] = ['path' => $path, 'code' => $code, 'message' => $message];
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function warnings(): array
    {
        return $this->warnings;
    }

    public function hasError(string $code): bool
    {
        foreach ($this->errors as $error) {
            if ($error['code'] === $code) {
                return true;
            }
        }

        return false;
    }

    public function productIdList(): array
    {
        $ids = array_keys($this->productIds);
        sort($ids);

        return $ids;
    }

    /** Short human summary for flash messages in the Blade admin. */
    public function summary(int $limit = 3): string
    {
        $messages = array_map(function ($error) {
            return $error['message'];
        }, array_slice($this->errors, 0, $limit));

        $more = count($this->errors) - $limit;
        if ($more > 0) {
            $messages[] = "and {$more} more";
        }

        return implode(' ', $messages);
    }

    public function toArray(): array
    {
        return ['errors' => $this->errors, 'warnings' => $this->warnings];
    }
}
