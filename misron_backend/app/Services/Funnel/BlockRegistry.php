<?php

namespace App\Services\Funnel;

/**
 * Read access to config/funnel_blocks.php: which block types exist, where they
 * may be placed and which props, styles and bindings they accept.
 */
class BlockRegistry
{
    public const ROOT = 'root';

    private $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? config('funnel_blocks');
    }

    public function has(string $type): bool
    {
        return isset($this->config['blocks'][$type]);
    }

    public function get(string $type): ?array
    {
        return $this->config['blocks'][$type] ?? null;
    }

    public function types(): array
    {
        return array_keys($this->config['blocks']);
    }

    /**
     * Whether $childType may sit directly inside $parentType. Both sides must
     * agree: the child lists the parent, and the parent accepts children.
     */
    public function allowsChild(string $parentType, string $childType): bool
    {
        $child = $this->get($childType);
        if ($child === null || !in_array($parentType, $child['parents'], true)) {
            return false;
        }

        if ($parentType === self::ROOT) {
            return true;
        }

        $accepted = $this->get($parentType)['children'] ?? null;

        return $accepted === '*' || (is_array($accepted) && in_array($childType, $accepted, true));
    }

    public function acceptsChildren(string $type): bool
    {
        return ($this->get($type)['children'] ?? null) !== null;
    }

    public function propRules(string $type): array
    {
        return $this->get($type)['props'] ?? [];
    }

    public function itemRules(string $item): ?array
    {
        return $this->config['items'][$item] ?? null;
    }

    public function styleRules(): array
    {
        return $this->config['style'];
    }

    public function breakpoints(): array
    {
        return $this->config['breakpoints'];
    }

    public function pageSettingRules(): array
    {
        return $this->config['page_settings'];
    }

    public function offerTypes(): array
    {
        return $this->config['offer_types'];
    }

    public function requiredCheckoutFields(): array
    {
        return $this->config['required_checkout_fields'];
    }

    /** [min, max] offers for blocks that define offers, null otherwise. */
    public function bindingRange(string $type): ?array
    {
        return $this->get($type)['bindings'] ?? null;
    }

    public function maxPerPage(string $type): ?int
    {
        return $this->get($type)['max_per_page'] ?? null;
    }

    public function childCountRange(string $type): array
    {
        $block = $this->get($type) ?? [];

        return [$block['min_children'] ?? 0, $block['max_children'] ?? null];
    }

    /** Registry shape served to the editor so both sides agree on the rules. */
    public function toArray(): array
    {
        return $this->config;
    }
}
