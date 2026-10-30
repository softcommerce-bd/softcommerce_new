<?php

namespace App\Services\Funnel;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Applies one rule from the block registry (e.g. "string:300", "list:faq_item:40")
 * to one value. Returns the normalized value, or self::invalid() after
 * recording an error on the result.
 */
class PropRules
{
    private static $invalid;

    private $registry;
    private $sanitizer;
    private $limits;

    public function __construct(BlockRegistry $registry, RichTextSanitizer $sanitizer, ?array $limits = null)
    {
        $this->registry = $registry;
        $this->sanitizer = $sanitizer;
        $this->limits = $limits ?? config('funnel.limits');
    }

    public static function invalid()
    {
        if (self::$invalid === null) {
            self::$invalid = new \stdClass();
        }

        return self::$invalid;
    }

    public static function isInvalid($value): bool
    {
        return $value === self::invalid();
    }

    /** array_is_list() for PHP 8.0, which the platform still allows. */
    public static function isList(array $value): bool
    {
        $i = 0;
        foreach ($value as $key => $unused) {
            if ($key !== $i++) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validates every key of $values against $rules. Unknown keys are dropped
     * with a warning, null values are dropped silently (null means "use the
     * block default"), invalid values are dropped with an error.
     */
    public function applyMap(array $rules, $values, string $path, ValidationResult $result): array
    {
        if ($values === null) {
            return [];
        }
        if (!is_array($values) || ($values !== [] && self::isList($values))) {
            $result->error($path, 'not_object', 'Expected a set of named values.');
            return [];
        }

        $clean = [];
        foreach ($values as $key => $value) {
            $key = (string) $key;
            if (!array_key_exists($key, $rules)) {
                $result->warning("{$path}.{$key}", 'unknown_prop', "Unknown setting \"{$key}\" was removed.");
                continue;
            }
            if ($value === null) {
                continue;
            }
            $normalized = $this->apply($rules[$key], $value, "{$path}.{$key}", $result);
            if (!self::isInvalid($normalized)) {
                $clean[$key] = $normalized;
            }
        }

        return $clean;
    }

    public function apply(string $rule, $value, string $path, ValidationResult $result)
    {
        $parts = explode(':', $rule);
        $name = array_shift($parts);

        switch ($name) {
            case 'string':
                return $this->plainText($value, (int) $parts[0], false, $path, $result);
            case 'text':
                return $this->plainText($value, (int) $parts[0], true, $path, $result);
            case 'rich':
                return $this->richText($value, $path, $result);
            case 'int':
                return $this->integer($value, (int) $parts[0], (int) $parts[1], $path, $result);
            case 'num':
                return $this->number($value, (float) $parts[0], (float) $parts[1], $path, $result);
            case 'bool':
                if (is_bool($value)) {
                    return $value;
                }
                $result->error($path, 'invalid_bool', 'Expected on or off.');
                return self::invalid();
            case 'enum':
                return $this->enum($value, explode(',', $parts[0]), $path, $result);
            case 'color':
                return $this->color($value, $path, $result);
            case 'url':
                return $this->url($value, $path, $result);
            case 'image':
                return $this->image($value, $path, $result);
            case 'icon':
                return $this->pattern($value, '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 40, 'invalid_icon', 'Unknown icon name.', $path, $result);
            case 'key':
                return $this->pattern($value, '/^[a-z0-9_]{3,40}$/', 40, 'invalid_key', 'Offer reference is not valid.', $path, $result);
            case 'phone':
                return $this->pattern($value, '/^\+?[0-9][0-9 -]{5,19}$/', 20, 'invalid_phone', 'Phone number is not valid.', $path, $result);
            case 'datetime':
                return $this->datetime($value, $path, $result);
            case 'strings':
                return $this->strings($value, (int) $parts[0], (int) $parts[1], $path, $result);
            case 'list':
                return $this->objectList($value, $parts[0], (int) $parts[1], $path, $result);
        }

        $result->error($path, 'unknown_rule', "Validation rule \"{$name}\" is not defined.");
        return self::invalid();
    }

    private function plainText($value, int $max, bool $multiline, string $path, ValidationResult $result)
    {
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }
        if (!is_string($value)) {
            $result->error($path, 'invalid_text', 'Expected text.');
            return self::invalid();
        }
        if (!mb_check_encoding($value, 'UTF-8')) {
            $result->error($path, 'invalid_encoding', 'Text is not valid UTF-8.');
            return self::invalid();
        }

        $value = str_replace(["\r\n", "\r"], "\n", $value);
        $value = $multiline
            ? preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', $value)
            : preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
        $value = trim($value);

        $max = min($max, (int) $this->limits['max_string']);
        if (mb_strlen($value) > $max) {
            $result->error($path, 'too_long', "Text is longer than {$max} characters.");
            return self::invalid();
        }

        return $value;
    }

    private function richText($value, string $path, ValidationResult $result)
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            $result->error($path, 'invalid_text', 'Expected formatted text.');
            return self::invalid();
        }

        $max = (int) $this->limits['max_rich_text'];
        if (strlen($value) > $max * 4) {
            $result->error($path, 'too_long', 'Formatted text is too long.');
            return self::invalid();
        }

        $clean = $this->sanitizer->sanitize($value);
        if (mb_strlen($clean) > $max) {
            $result->error($path, 'too_long', "Formatted text is longer than {$max} characters.");
            return self::invalid();
        }

        return $clean;
    }

    private function integer($value, int $min, int $max, string $path, ValidationResult $result)
    {
        if (is_string($value) && preg_match('/^-?\d+$/', $value)) {
            $value = (int) $value;
        }
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }
        if (!is_int($value)) {
            $result->error($path, 'invalid_int', 'Expected a whole number.');
            return self::invalid();
        }
        if ($value < $min || $value > $max) {
            $result->error($path, 'out_of_range', "Value must be between {$min} and {$max}.");
            return self::invalid();
        }

        return $value;
    }

    private function number($value, float $min, float $max, string $path, ValidationResult $result)
    {
        if (is_string($value) && is_numeric($value)) {
            $value = $value + 0;
        }
        if (!is_int($value) && !is_float($value)) {
            $result->error($path, 'invalid_number', 'Expected a number.');
            return self::invalid();
        }
        if (is_float($value) && !is_finite($value)) {
            $result->error($path, 'invalid_number', 'Expected a number.');
            return self::invalid();
        }
        if ($value < $min || $value > $max) {
            $result->error($path, 'out_of_range', "Value must be between {$min} and {$max}.");
            return self::invalid();
        }

        return $value;
    }

    private function enum($value, array $allowed, string $path, ValidationResult $result)
    {
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            $result->error($path, 'invalid_option', 'Choose one of: ' . implode(', ', $allowed) . '.');
            return self::invalid();
        }

        return $value;
    }

    private function color($value, string $path, ValidationResult $result)
    {
        if (is_string($value)) {
            $value = strtolower(trim($value));
            if ($value === 'transparent' || preg_match('/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $value)) {
                return $value;
            }
        }

        $result->error($path, 'invalid_color', 'Colour must be a hex value such as #ff6600.');
        return self::invalid();
    }

    private function url($value, string $path, ValidationResult $result)
    {
        if (!is_string($value)) {
            $result->error($path, 'invalid_url', 'Expected a link.');
            return self::invalid();
        }

        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (!UrlRule::isSafe($value)) {
            $result->error($path, 'invalid_url', 'Link must start with https://, /, #, tel: or mailto:.');
            return self::invalid();
        }

        return $value;
    }

    private function image($value, string $path, ValidationResult $result)
    {
        if (!is_array($value) || ($value !== [] && self::isList($value))) {
            $result->error($path, 'invalid_image', 'Expected an image.');
            return self::invalid();
        }

        $clean = [];

        if (isset($value['uploadId'])) {
            $id = $value['uploadId'];
            if (is_string($id) && ctype_digit($id)) {
                $id = (int) $id;
            }
            if (!is_int($id) || $id < 1) {
                $result->error("{$path}.uploadId", 'invalid_upload', 'Image reference is not valid.');
                return self::invalid();
            }
            $clean['uploadId'] = $id;
        }

        if (isset($value['url']) && $value['url'] !== '') {
            if (!is_string($value['url']) || !UrlRule::isSafeImage(trim($value['url']))) {
                $result->error("{$path}.url", 'invalid_url', 'Image address must start with https:// or /.');
                return self::invalid();
            }
            $clean['url'] = trim($value['url']);
        }

        if (isset($value['alt'])) {
            $alt = $this->plainText($value['alt'], 200, false, "{$path}.alt", $result);
            if (self::isInvalid($alt)) {
                return self::invalid();
            }
            if ($alt !== '') {
                $clean['alt'] = $alt;
            }
        }

        foreach (array_keys($value) as $key) {
            if (!in_array($key, ['uploadId', 'url', 'alt'], true)) {
                $result->warning("{$path}.{$key}", 'unknown_prop', "Unknown image setting \"{$key}\" was removed.");
            }
        }

        return $clean;
    }

    private function pattern($value, string $regex, int $max, string $code, string $message, string $path, ValidationResult $result)
    {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return '';
            }
            if (strlen($value) <= $max && preg_match($regex, $value)) {
                return $value;
            }
        }

        $result->error($path, $code, $message);
        return self::invalid();
    }

    private function datetime($value, string $path, ValidationResult $result)
    {
        $pattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:\d{2})?$/';
        if (!is_string($value) || !preg_match($pattern, $value)) {
            $result->error($path, 'invalid_datetime', 'Date and time must look like 2026-12-31T23:59:00+06:00.');
            return self::invalid();
        }

        try {
            $zone = new DateTimeZone(config('app.timezone') ?: 'UTC');
            $date = new DateTimeImmutable($value, $zone);
        } catch (\Exception $e) {
            $result->error($path, 'invalid_datetime', 'Date and time is not valid.');
            return self::invalid();
        }

        return $date->format(DATE_ATOM);
    }

    private function strings($value, int $maxCount, int $maxLength, string $path, ValidationResult $result)
    {
        if (!is_array($value) || !self::isList($value)) {
            $result->error($path, 'invalid_list', 'Expected a list.');
            return self::invalid();
        }
        if (count($value) > $maxCount) {
            $result->error($path, 'too_many', "At most {$maxCount} entries are allowed.");
            return self::invalid();
        }

        $clean = [];
        foreach ($value as $i => $item) {
            $text = $this->plainText($item, $maxLength, false, "{$path}[{$i}]", $result);
            if (self::isInvalid($text)) {
                return self::invalid();
            }
            $clean[] = $text;
        }

        return $clean;
    }

    private function objectList($value, string $itemName, int $maxCount, string $path, ValidationResult $result)
    {
        $rules = $this->registry->itemRules($itemName);
        if ($rules === null) {
            $result->error($path, 'unknown_rule', "List item type \"{$itemName}\" is not defined.");
            return self::invalid();
        }
        if (!is_array($value) || !self::isList($value)) {
            $result->error($path, 'invalid_list', 'Expected a list.');
            return self::invalid();
        }
        if (count($value) > $maxCount) {
            $result->error($path, 'too_many', "At most {$maxCount} entries are allowed.");
            return self::invalid();
        }

        $clean = [];
        foreach ($value as $i => $item) {
            $clean[] = $this->applyMap($rules, $item, "{$path}[{$i}]", $result);
        }

        return $clean;
    }
}
