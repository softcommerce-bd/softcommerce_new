<?php

namespace App\Services\Funnel;

/**
 * Validates and normalizes a funnel page document against the block registry.
 *
 * Two modes share one pass. "draft" checks structure and values, which is
 * what an autosave needs. "publish" also checks completeness: products chosen,
 * offer references resolvable, a checkout present wherever products can be
 * selected, countdowns tied to a real end time.
 *
 * The normalized document contains only registered types, props, styles and
 * binding fields; everything else is dropped. Product existence and prices are
 * not checked here (that needs the catalogue), only their shape.
 */
class PageValidator
{
    public const MODE_DRAFT = 'draft';
    public const MODE_PUBLISH = 'publish';

    private const NODE_KEYS = ['id', 'type', 'props', 'style', 'advanced', 'productBindings', 'children'];

    // Blocks that only make sense when the page can take an order.
    private const NEEDS_CHECKOUT = [
        'product_card', 'product_offer_selector', 'bundle_offer_selector', 'purchase_button',
        'order_summary', 'payment_method_selector', 'coupon_input', 'delivery_charges', 'discount_summary',
    ];

    private $registry;
    private $rules;
    private $migrator;
    private $limits;

    /** @var ValidationResult */
    private $result;
    private $mode;
    private $ids;
    private $typeCounts;
    private $offerRefs;
    private $offerEnds;
    private $countdowns;
    private $bindingCount;
    private $nodeLimitHit;

    public function __construct(BlockRegistry $registry, PropRules $rules, SchemaMigrator $migrator, ?array $limits = null)
    {
        $this->registry = $registry;
        $this->rules = $rules;
        $this->migrator = $migrator;
        $this->limits = $limits ?? config('funnel.limits');
    }

    /**
     * @param array|string $input decoded document or its JSON text
     */
    public function validate($input, string $mode = self::MODE_DRAFT): ValidationResult
    {
        $this->reset($mode);
        $result = $this->result;

        $maxBytes = (int) $this->limits['max_document_bytes'];

        if (is_string($input)) {
            if (strlen($input) > $maxBytes) {
                $result->error('', 'too_large', 'The page is too large to save.');
                return $result;
            }
            try {
                $input = json_decode($input, true, 128, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $result->error('', 'invalid_json', 'The page data could not be read.');
                return $result;
            }
        } elseif (is_array($input)) {
            $encoded = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
            if ($encoded === false || strlen($encoded) > $maxBytes) {
                $result->error('', 'too_large', 'The page is too large to save.');
                return $result;
            }
        }

        if (!is_array($input)) {
            $result->error('', 'invalid_document', 'The page data is not a page document.');
            return $result;
        }

        try {
            $document = $this->migrator->migrate($input);
        } catch (\InvalidArgumentException $e) {
            $result->error('schemaVersion', 'unsupported_schema', $e->getMessage());
            return $result;
        }

        $page = $document['page'] ?? null;
        if (!is_array($page) || ($page !== [] && PropRules::isList($page))) {
            $result->error('page', 'invalid_document', 'The page data is not a page document.');
            return $result;
        }

        foreach (array_keys($page) as $key) {
            if (!in_array($key, ['settings', 'sections'], true)) {
                $result->warning("page.{$key}", 'unknown_key', "Unknown page entry \"{$key}\" was removed.");
            }
        }

        $settings = $this->rules->applyMap($this->registry->pageSettingRules(), $page['settings'] ?? null, 'page.settings', $result);

        $sections = $page['sections'] ?? [];
        if (!is_array($sections) || !PropRules::isList($sections)) {
            $result->error('page.sections', 'invalid_list', 'Page sections must be a list.');
            $sections = [];
        }

        $sections = $this->validateChildren($sections, BlockRegistry::ROOT, 'page.sections', 1);

        $this->checkPage($sections);

        $normalized = ['sections' => $sections];
        if ($settings !== []) {
            $normalized = ['settings' => $settings] + $normalized;
        }

        $result->document = ['schemaVersion' => $this->migrator->currentVersion(), 'page' => $normalized];
        $result->nodeCount = count($this->ids);

        return $result;
    }

    private function reset(string $mode): void
    {
        $this->result = new ValidationResult();
        $this->mode = $mode;
        $this->ids = [];
        $this->typeCounts = [];
        $this->offerRefs = [];
        $this->offerEnds = [];
        $this->countdowns = [];
        $this->bindingCount = 0;
        $this->nodeLimitHit = false;
    }

    private function publishing(): bool
    {
        return $this->mode === self::MODE_PUBLISH;
    }

    private function validateChildren(array $nodes, string $parentType, string $path, int $depth): array
    {
        $out = [];
        foreach ($nodes as $i => $node) {
            $clean = $this->validateNode($node, $parentType, "{$path}[{$i}]", $depth);
            if ($clean !== null) {
                $out[] = $clean;
            }
        }

        return $out;
    }

    private function validateNode($node, string $parentType, string $path, int $depth): ?array
    {
        $result = $this->result;

        if (count($this->ids) >= (int) $this->limits['max_nodes']) {
            if (!$this->nodeLimitHit) {
                $this->nodeLimitHit = true;
                $result->error($path, 'too_many_blocks', 'The page has more than ' . $this->limits['max_nodes'] . ' blocks.');
            }
            return null;
        }

        if (!is_array($node) || ($node !== [] && PropRules::isList($node))) {
            $result->error($path, 'invalid_block', 'Block data is not valid.');
            return null;
        }

        $type = $node['type'] ?? null;
        if (!is_string($type) || !$this->registry->has($type)) {
            $label = is_string($type) ? $type : 'unknown';
            $result->error("{$path}.type", 'unknown_block', "Block type \"{$label}\" is not available.");
            return null;
        }

        $id = $node['id'] ?? null;
        if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{3,64}$/', $id)) {
            $result->error("{$path}.id", 'invalid_id', 'Block id is missing or not valid.');
            return null;
        }
        if (isset($this->ids[$id])) {
            $result->error("{$path}.id", 'duplicate_id', "Two blocks share the id \"{$id}\".");
            return null;
        }
        $this->ids[$id] = $path;

        if (!$this->registry->allowsChild($parentType, $type)) {
            $where = $parentType === BlockRegistry::ROOT ? 'at the top level of the page' : 'inside ' . $this->label($parentType);
            $result->error($path, 'invalid_nesting', ucfirst($this->label($type)) . " cannot be placed {$where}.");
            return null;
        }

        if ($depth > (int) $this->limits['max_depth']) {
            $result->error($path, 'too_deep', 'Blocks are nested more than ' . $this->limits['max_depth'] . ' levels deep.');
            return null;
        }

        $this->typeCounts[$type] = ($this->typeCounts[$type] ?? 0) + 1;

        foreach (array_keys($node) as $key) {
            if (!in_array($key, self::NODE_KEYS, true)) {
                $result->warning("{$path}.{$key}", 'unknown_key', "Unknown block entry \"{$key}\" was removed.");
            }
        }

        $clean = ['id' => $id, 'type' => $type];

        $props = $this->rules->applyMap($this->registry->propRules($type), $node['props'] ?? null, "{$path}.props", $result);
        $props = $this->blockRules($type, $props, "{$path}.props");
        if ($props !== []) {
            $clean['props'] = $props;
        }

        $style = $this->validateStyle($node['style'] ?? null, "{$path}.style");
        if ($style !== []) {
            $clean['style'] = $style;
        }

        $advanced = $this->validateAdvanced($node['advanced'] ?? null, "{$path}.advanced");
        if ($advanced !== []) {
            $clean['advanced'] = $advanced;
        }

        $range = $this->registry->bindingRange($type);
        if ($range !== null) {
            $clean['productBindings'] = $this->validateBindings($node['productBindings'] ?? [], $range, "{$path}.productBindings", $id, $type);
        } elseif (!empty($node['productBindings'])) {
            $result->warning("{$path}.productBindings", 'unexpected_bindings', ucfirst($this->label($type)) . ' does not use products; the product selection was removed.');
        }

        if ($this->registry->acceptsChildren($type)) {
            $children = $node['children'] ?? [];
            if (!is_array($children) || !PropRules::isList($children)) {
                $result->error("{$path}.children", 'invalid_list', 'Block children must be a list.');
                $children = [];
            }

            [$min, $max] = $this->registry->childCountRange($type);
            if ($max !== null && count($children) > $max) {
                $result->error("{$path}.children", 'too_many_children', ucfirst($this->label($type)) . " can hold at most {$max} items.");
                $children = array_slice($children, 0, $max);
            }
            if ($this->publishing() && count($children) < $min) {
                $result->error("{$path}.children", 'too_few_children', ucfirst($this->label($type)) . " needs at least {$min} item.");
            }

            $clean['children'] = $this->validateChildren($children, $type, "{$path}.children", $depth + 1);
        } elseif (!empty($node['children'])) {
            $result->error("{$path}.children", 'children_not_allowed', ucfirst($this->label($type)) . ' cannot contain other blocks.');
        }

        return $clean;
    }

    /** Rules that depend on more than one prop, or on the rest of the page. */
    private function blockRules(string $type, array $props, string $path): array
    {
        if (($props['offerKey'] ?? '') !== '') {
            $this->offerRefs[] = ['key' => $props['offerKey'], 'path' => "{$path}.offerKey"];
        } elseif ($type === 'customer_reviews' && $this->publishing()) {
            $this->result->error("{$path}.offerKey", 'missing_offer', 'Choose which product the reviews come from.');
        }

        if ($this->publishing()) {
            $this->scanForSampleText($props, $path);
            if (in_array($type, ['video', 'video_hero'], true) && ($props['url'] ?? '') === '') {
                $this->result->error("{$path}.url", 'video_without_url', 'Add the video link, or remove the video, before publishing.');
            }
        }

        switch ($type) {
            case 'checkout_form':
                $props = $this->checkoutFields($props, $path);
                break;

            case 'countdown_timer':
                $this->countdowns[] = [
                    'path' => $path,
                    'endsAt' => $props['endsAt'] ?? null,
                    'offerKey' => $props['offerKey'] ?? null,
                ];
                break;

            case 'button':
                if (($props['action'] ?? 'link') === 'link' && ($props['link'] ?? '') === '' && $this->publishing()) {
                    $this->result->warning("{$path}.link", 'missing_link', 'A button has no link, so it will do nothing when tapped.');
                }
                break;

            case 'payment_method_selector':
                foreach ($props['allowed'] ?? [] as $i => $method) {
                    if (!preg_match('/^[a-z0-9_]{2,40}$/', $method)) {
                        $this->result->error("{$path}.allowed[{$i}]", 'invalid_option', 'Payment method name is not valid.');
                    }
                }
                break;
        }

        return $props;
    }

    /**
     * Templates mark wording the merchant must write themselves, such as a real
     * customer review, as "[[like this]]". A page cannot go live with it.
     */
    private function scanForSampleText($value, string $path): void
    {
        if (is_string($value)) {
            if (preg_match('/\[\[.+?\]\]/su', $value, $match)) {
                $this->result->error($path, 'sample_text', 'Replace the sample text "' . mb_substr($match[0], 0, 60) . '" with your own, or remove the block, before publishing.');
            }

            return;
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $this->scanForSampleText($item, "{$path}.{$key}");
            }
        }
    }

    /**
     * Name, phone and address are needed to deliver an order, so they are
     * always present, enabled and required however the form was configured.
     */
    private function checkoutFields(array $props, string $path): array
    {
        if (!isset($props['fields'])) {
            return $props;
        }

        $seen = [];
        $fields = [];
        foreach ($props['fields'] as $i => $field) {
            $key = $field['key'] ?? null;
            if ($key === null) {
                $this->result->error("{$path}.fields[{$i}].key", 'missing_field_key', 'A checkout field has no type.');
                continue;
            }
            if (isset($seen[$key])) {
                $this->result->error("{$path}.fields[{$i}].key", 'duplicate_field', "The checkout field \"{$key}\" appears twice.");
                continue;
            }
            $seen[$key] = true;
            $fields[] = $field;
        }

        foreach ($this->registry->requiredCheckoutFields() as $required) {
            if (!isset($seen[$required])) {
                $fields[] = ['key' => $required];
            }
        }

        foreach ($fields as &$field) {
            if (in_array($field['key'], $this->registry->requiredCheckoutFields(), true)) {
                $field['enabled'] = true;
                $field['required'] = true;
            }
        }
        unset($field);

        $props['fields'] = $fields;

        return $props;
    }

    private function validateStyle($style, string $path): array
    {
        if ($style === null) {
            return [];
        }
        if (!is_array($style) || ($style !== [] && PropRules::isList($style))) {
            $this->result->error($path, 'not_object', 'Style settings are not valid.');
            return [];
        }

        $clean = [];
        foreach ($style as $breakpoint => $values) {
            if (!in_array($breakpoint, $this->registry->breakpoints(), true)) {
                $this->result->warning("{$path}.{$breakpoint}", 'unknown_breakpoint', "Unknown screen size \"{$breakpoint}\" was removed.");
                continue;
            }
            $values = $this->rules->applyMap($this->registry->styleRules(), $values, "{$path}.{$breakpoint}", $this->result);
            if ($values !== []) {
                $clean[$breakpoint] = $values;
            }
        }

        return $clean;
    }

    private function validateAdvanced($advanced, string $path): array
    {
        if ($advanced === null) {
            return [];
        }
        if (!is_array($advanced) || ($advanced !== [] && PropRules::isList($advanced))) {
            $this->result->error($path, 'not_object', 'Advanced settings are not valid.');
            return [];
        }

        $clean = [];
        foreach ($advanced as $key => $value) {
            if ($value === null) {
                continue;
            }
            switch ($key) {
                case 'hidden':
                    if (!is_array($value)) {
                        $this->result->error("{$path}.hidden", 'not_object', 'Visibility settings are not valid.');
                        break;
                    }
                    $hidden = [];
                    foreach ($value as $breakpoint => $flag) {
                        if (in_array($breakpoint, $this->registry->breakpoints(), true) && is_bool($flag)) {
                            if ($flag) {
                                $hidden[$breakpoint] = true;
                            }
                        } else {
                            $this->result->error("{$path}.hidden.{$breakpoint}", 'invalid_bool', 'Visibility must be on or off per screen size.');
                        }
                    }
                    if ($hidden !== []) {
                        $clean['hidden'] = $hidden;
                    }
                    break;

                case 'locked':
                    $locked = $this->rules->apply('bool', $value, "{$path}.locked", $this->result);
                    if ($locked === true) {
                        $clean['locked'] = true;
                    }
                    break;

                case 'anchor':
                    if (is_string($value) && preg_match('/^[a-z][a-z0-9-]{0,40}$/', $value)) {
                        $clean['anchor'] = $value;
                    } elseif ($value !== '') {
                        $this->result->error("{$path}.anchor", 'invalid_anchor', 'Anchor names use lowercase letters, digits and dashes, starting with a letter.');
                    }
                    break;

                case 'label':
                    $label = $this->rules->apply('string:80', $value, "{$path}.label", $this->result);
                    if (is_string($label) && $label !== '') {
                        $clean['label'] = $label;
                    }
                    break;

                default:
                    $this->result->warning("{$path}.{$key}", 'unknown_prop', "Unknown advanced setting \"{$key}\" was removed.");
            }
        }

        return $clean;
    }

    /**
     * Validates a list of offers outside a page, for the editor's live price
     * preview. Returns the same result object, with the clean bindings in
     * $result->bindings.
     */
    public function validateBindingList($bindings, string $mode = self::MODE_DRAFT): ValidationResult
    {
        $this->reset($mode);
        $max = (int) $this->limits['max_product_bindings'];
        $clean = $this->validateBindings($bindings, [0, $max], 'bindings', 'preview', 'product_offer_selector');
        $this->result->document = ['bindings' => $clean];
        $this->checkOverlaps();

        return $this->result;
    }

    private function validateBindings($bindings, array $range, string $path, string $blockId = '', string $blockType = ''): array
    {
        [$min, $max] = $range;

        if (!is_array($bindings) || !PropRules::isList($bindings)) {
            $this->result->error($path, 'invalid_list', 'Product selection is not valid.');
            return [];
        }
        if (count($bindings) > $max) {
            $this->result->error($path, 'too_many_offers', "This block can show at most {$max} offers.");
            $bindings = array_slice($bindings, 0, $max);
        }
        if ($this->publishing() && count($bindings) < $min) {
            $this->result->error($path, 'missing_products', $min === 1
                ? 'Choose a product for this block.'
                : "Choose at least {$min} products for this block.");
        }

        $clean = [];
        foreach ($bindings as $i => $binding) {
            $this->bindingCount++;
            if ($this->bindingCount === (int) $this->limits['max_product_bindings'] + 1) {
                $this->result->error("{$path}[{$i}]", 'too_many_offers', 'The page has more than ' . $this->limits['max_product_bindings'] . ' offers.');
            }

            $item = $this->validateBinding($binding, "{$path}[{$i}]");
            if ($item !== null) {
                $clean[] = $item;
                $this->result->bindings[] = ['key' => $item['key'], 'path' => "{$path}[{$i}]", 'blockId' => $blockId, 'blockType' => $blockType, 'binding' => $item];
            }
        }

        return $clean;
    }

    private function validateBinding($binding, string $path): ?array
    {
        $result = $this->result;

        if (!is_array($binding) || ($binding !== [] && PropRules::isList($binding))) {
            $result->error($path, 'invalid_offer', 'Offer data is not valid.');
            return null;
        }

        $key = $binding['key'] ?? null;
        if (!is_string($key) || !preg_match('/^[a-z0-9_]{3,40}$/', $key)) {
            $result->error("{$path}.key", 'invalid_key', 'Offer key is missing or not valid.');
            return null;
        }
        if (isset($result->offerKeys[$key])) {
            $result->error("{$path}.key", 'duplicate_offer_key', "Two offers share the key \"{$key}\".");
            return null;
        }

        foreach (array_keys($binding) as $entry) {
            if (!in_array($entry, ['key', 'items', 'offer', 'display'], true)) {
                $result->warning("{$path}.{$entry}", 'unknown_key', "Unknown offer entry \"{$entry}\" was removed.");
            }
        }

        $items = $this->bindingItems($binding['items'] ?? [], "{$path}.items");
        $offer = $this->bindingOffer($binding['offer'] ?? null, "{$path}.offer");
        $display = $this->rules->applyMap([
            'title' => 'string:200',
            'description' => 'text:1000',
            'image' => 'image',
            'badge' => 'string:60',
            'ctaText' => 'string:60',
            'featured' => 'bool',
            'defaultSelected' => 'bool',
        ], $binding['display'] ?? null, "{$path}.display", $result);

        $result->offerKeys[$key] = $path;
        $this->offerEnds[$key] = $offer['endsAt'] ?? null;

        $clean = ['key' => $key, 'items' => $items, 'offer' => $offer];
        if ($display !== []) {
            $clean['display'] = $display;
        }

        return $clean;
    }

    private function bindingItems($items, string $path): array
    {
        if (!is_array($items) || !PropRules::isList($items)) {
            $this->result->error($path, 'invalid_list', 'Offer products are not valid.');
            return [];
        }
        if (count($items) > 10) {
            $this->result->error($path, 'too_many', 'An offer can contain at most 10 products.');
            $items = array_slice($items, 0, 10);
        }
        if ($this->publishing() && $items === []) {
            $this->result->error($path, 'missing_products', 'Choose a product for this offer.');
        }

        $clean = [];
        foreach ($items as $i => $item) {
            $itemPath = "{$path}[{$i}]";
            if (!is_array($item)) {
                $this->result->error($itemPath, 'invalid_offer', 'Offer product is not valid.');
                continue;
            }

            $productId = $this->rules->apply('int:1:2147483647', $item['productId'] ?? null, "{$itemPath}.productId", $this->result);
            if (PropRules::isInvalid($productId)) {
                continue;
            }

            $variant = null;
            if (isset($item['variant']) && $item['variant'] !== '') {
                $variant = $this->rules->apply('string:255', $item['variant'], "{$itemPath}.variant", $this->result);
                if (PropRules::isInvalid($variant)) {
                    continue;
                }
            }

            $quantity = 1;
            if (isset($item['quantity'])) {
                $quantity = $this->rules->apply('int:1:999', $item['quantity'], "{$itemPath}.quantity", $this->result);
                if (PropRules::isInvalid($quantity)) {
                    continue;
                }
            }

            $lock = false;
            if (isset($item['lockVariant'])) {
                $lock = $this->rules->apply('bool', $item['lockVariant'], "{$itemPath}.lockVariant", $this->result);
                if (PropRules::isInvalid($lock)) {
                    continue;
                }
            }

            $this->result->productIds[$productId] = $productId;
            $clean[] = ['productId' => $productId, 'variant' => $variant, 'quantity' => $quantity, 'lockVariant' => $lock && $variant !== null];
        }

        return $clean;
    }

    private function bindingOffer($offer, string $path): array
    {
        $clean = $this->rules->applyMap([
            'type' => 'enum:' . implode(',', $this->registry->offerTypes()),
            'value' => 'num:0:100000000',
            'buyQty' => 'int:1:99',
            'getQty' => 'int:1:99',
            'giftProductId' => 'int:1:2147483647',
            'giftVariant' => 'string:255',
            'giftQuantity' => 'int:1:10',
            'freeDelivery' => 'bool',
            'startsAt' => 'datetime',
            'endsAt' => 'datetime',
            'maxPerOrder' => 'int:1:999',
            'tiers' => 'list:offer_tier:10',
        ], $offer, $path, $this->result);

        $type = $clean['type'] ?? 'none';
        $clean['type'] = $type;
        $value = $clean['value'] ?? null;
        $publishing = $this->publishing();

        switch ($type) {
            case 'percent':
                if ($value !== null && ($value <= 0 || $value > 100)) {
                    $this->result->error("{$path}.value", 'invalid_discount', 'A percentage discount must be more than 0 and at most 100.');
                } elseif ($value === null && $publishing) {
                    $this->result->error("{$path}.value", 'missing_discount', 'Enter the discount percentage.');
                }
                break;

            case 'fixed_amount':
            case 'fixed_price':
                if ($value !== null && $value <= 0) {
                    $this->result->error("{$path}.value", 'invalid_discount', 'The amount must be more than 0.');
                } elseif ($value === null && $publishing) {
                    $this->result->error("{$path}.value", 'missing_discount', 'Enter the amount.');
                }
                break;

            case 'quantity_discount':
                $tiers = $clean['tiers'] ?? [];
                $seen = [];
                foreach ($tiers as $i => $tier) {
                    if (!isset($tier['minQty'], $tier['type'], $tier['value'])) {
                        $this->result->error("{$path}.tiers[{$i}]", 'incomplete_tier', 'Each price break needs a quantity, a discount type and a value.');
                    } elseif ($tier['value'] <= 0 || ($tier['type'] === 'percent' && $tier['value'] > 100)) {
                        $this->result->error("{$path}.tiers[{$i}].value", 'invalid_discount', $tier['type'] === 'percent' ? 'A percentage must be more than 0 and at most 100.' : 'The amount must be more than 0.');
                    }
                    if (isset($tier['minQty'])) {
                        if (isset($seen[$tier['minQty']])) {
                            $this->result->error("{$path}.tiers[{$i}].minQty", 'duplicate_tier', 'Two price breaks start at the same quantity.');
                        }
                        $seen[$tier['minQty']] = true;
                    }
                }
                if ($tiers === [] && $publishing) {
                    $this->result->error("{$path}.tiers", 'missing_discount', 'Add at least one quantity price break.');
                }
                if ($tiers !== []) {
                    usort($tiers, function ($a, $b) {
                        return ($a['minQty'] ?? 0) <=> ($b['minQty'] ?? 0);
                    });
                    $clean['tiers'] = $tiers;
                }
                break;

            case 'bxgy':
                if ($publishing && (!isset($clean['buyQty']) || !isset($clean['getQty']))) {
                    $this->result->error($path, 'missing_discount', 'Enter how many to buy and how many are free.');
                }
                break;

            case 'free_gift':
                if (isset($clean['giftProductId'])) {
                    $this->result->productIds[$clean['giftProductId']] = $clean['giftProductId'];
                } elseif ($publishing) {
                    $this->result->error("{$path}.giftProductId", 'missing_gift', 'Choose the free gift product.');
                }
                break;
        }

        if (isset($clean['startsAt'], $clean['endsAt']) && strtotime($clean['endsAt']) <= strtotime($clean['startsAt'])) {
            $this->result->error("{$path}.endsAt", 'invalid_period', 'The offer must end after it starts.');
        }

        // Only the settings that belong to the chosen offer type are kept, so
        // switching type in the editor never leaves a stale value behind.
        $keep = array_merge(['type', 'freeDelivery', 'startsAt', 'endsAt', 'maxPerOrder'], [
            'percent' => ['value'],
            'fixed_amount' => ['value'],
            'fixed_price' => ['value'],
            'quantity_discount' => ['tiers'],
            'bxgy' => ['buyQty', 'getQty'],
            'free_gift' => ['giftProductId', 'giftVariant', 'giftQuantity'],
        ][$type] ?? []);
        $clean = array_intersect_key($clean, array_flip($keep));
        if (($clean['freeDelivery'] ?? false) === false) {
            unset($clean['freeDelivery']);
        }

        return $clean;
    }

    private function checkPage(array $sections): void
    {
        $result = $this->result;

        foreach ($this->typeCounts as $type => $count) {
            $max = $this->registry->maxPerPage($type);
            if ($max !== null && $count > $max) {
                $result->error('page.sections', 'too_many_of_type', "Only {$max} " . $this->name($type) . ' is allowed per page.');
            }
        }

        foreach ($this->offerRefs as $ref) {
            if (!isset($result->offerKeys[$ref['key']])) {
                $message = 'A block refers to an offer that is no longer on the page.';
                $this->publishing()
                    ? $result->error($ref['path'], 'unknown_offer', $message)
                    : $result->warning($ref['path'], 'unknown_offer', $message);
            }
        }

        $this->checkOverlaps();

        if (!$this->publishing()) {
            return;
        }

        if ($sections === []) {
            $result->error('page.sections', 'empty_page', 'Add at least one section before publishing.');
        }

        foreach ($this->countdowns as $countdown) {
            $end = $countdown['endsAt'];
            if ($end === null && $countdown['offerKey'] !== null) {
                $end = $this->offerEnds[$countdown['offerKey']] ?? null;
            }
            if ($end === null) {
                $result->error($countdown['path'], 'countdown_without_end', 'A countdown needs a real end time: set one, or link it to an offer that has an end date.');
            }
        }

        $hasCheckout = ($this->typeCounts['checkout_form'] ?? 0) > 0;
        if (!$hasCheckout) {
            foreach (self::NEEDS_CHECKOUT as $type) {
                if (($this->typeCounts[$type] ?? 0) > 0) {
                    $message = 'Customers can choose products on this page, but there is no checkout form to order them.';
                    config('funnel.require_checkout')
                        ? $result->error('page.sections', 'missing_checkout', $message)
                        : $result->warning('page.sections', 'missing_checkout', $message . ' Add an order form from the Order form group.');
                    break;
                }
            }
        }

        if ($hasCheckout && !$this->checkoutHasButton($sections)) {
            $result->error('page.sections', 'checkout_no_button', 'The order form has no order button inside it, so customers cannot send their order. Add an "Order button" to the form.');
        }
    }

    /** Whether some checkout form on the page holds an order button (at any depth). */
    private function checkoutHasButton(array $nodes): bool
    {
        $contains = function (array $children) use (&$contains): bool {
            foreach ($children as $child) {
                if (($child['type'] ?? null) === 'purchase_button' || (!empty($child['children']) && $contains($child['children']))) {
                    return true;
                }
            }

            return false;
        };
        foreach ($nodes as $node) {
            if (($node['type'] ?? null) === 'checkout_form' && $contains($node['children'] ?? [])) {
                return true;
            }
            if (($node['type'] ?? null) !== 'checkout_form' && !empty($node['children']) && $this->checkoutHasButton($node['children'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * The same product (and variation) offered twice with overlapping dates
     * shows customers two prices for one thing; warn so it is a choice.
     */
    private function checkOverlaps(): void
    {
        $seen = [];
        foreach ($this->result->bindings as $entry) {
            $items = $entry['binding']['items'] ?? [];
            if (count($items) !== 1) {
                continue;
            }
            $key = $items[0]['productId'] . '|' . ($items[0]['variant'] ?? '');
            $offer = $entry['binding']['offer'] ?? [];
            $window = [
                isset($offer['startsAt']) ? strtotime($offer['startsAt']) : PHP_INT_MIN,
                isset($offer['endsAt']) ? strtotime($offer['endsAt']) : PHP_INT_MAX,
            ];
            foreach ($seen[$key] ?? [] as $other) {
                if ($window[0] <= $other['window'][1] && $other['window'][0] <= $window[1]) {
                    $this->result->warning($entry['path'], 'overlapping_offers', 'This product is offered twice on the page at the same time, with possibly different prices.');
                    break;
                }
            }
            $seen[$key][] = ['window' => $window];
        }
    }

    /** "checkout_form" => "a checkout form", for messages shown to merchants. */
    private function label(string $type): string
    {
        $name = $this->name($type);

        return (preg_match('/^[aeiou]/', $name) ? 'an ' : 'a ') . $name;
    }

    private function name(string $type): string
    {
        return str_replace('_', ' ', $type);
    }
}
