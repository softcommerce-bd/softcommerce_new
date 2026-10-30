<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * JSON column cast that keeps Bengali text readable and small.
 *
 * Laravel's "array" cast escapes every non-ASCII character as \uXXXX, which
 * roughly triples the stored size of Bengali copy. Page documents are mostly
 * copy, so they are encoded with unescaped unicode and slashes instead.
 */
class CompactJson implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true, 512);

        return is_array($decoded) ? $decoded : null;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return null;
        }

        return self::encode($value);
    }

    public static function encode($value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
