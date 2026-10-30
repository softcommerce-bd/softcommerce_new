<?php

namespace App\Services\Funnel\Checkout;

/**
 * What the published page's checkout asks for, read from the published
 * document only (never from the request): which fields exist, which are
 * required, whether a consent box must be ticked and which payment methods the
 * merchant allowed.
 *
 * The merchant can relabel, reorder, hide and require optional fields, but the
 * ones an order cannot be fulfilled without are forced on whatever the page says,
 * the same rule the publish validator applies.
 */
class CheckoutConfig
{
    /** Fields the default Bangladesh form shows, in order. */
    public const DEFAULT_FIELDS = ['name', 'phone', 'address', 'district', 'area', 'notes'];

    private bool $present;
    /** @var array<string, array<string, mixed>> */
    private array $fields;
    private bool $requireConsent;
    private string $consentText;
    /** @var string[] */
    private array $paymentsAllowed;
    private string $language;

    private function __construct(bool $present, array $fields, bool $requireConsent, string $consentText, array $paymentsAllowed, string $language)
    {
        $this->present = $present;
        $this->fields = $fields;
        $this->requireConsent = $requireConsent;
        $this->consentText = $consentText;
        $this->paymentsAllowed = $paymentsAllowed;
        $this->language = $language;
    }

    public static function fromDocument(?array $document): self
    {
        $form = null;
        $selector = null;
        $visit = function (array $nodes) use (&$visit, &$form, &$selector) {
            foreach ($nodes as $node) {
                if (($node['type'] ?? null) === 'checkout_form' && $form === null) {
                    $form = $node;
                }
                if (($node['type'] ?? null) === 'payment_method_selector' && $selector === null) {
                    $selector = $node;
                }
                if (!empty($node['children'])) {
                    $visit($node['children']);
                }
            }
        };
        $visit($document['page']['sections'] ?? []);

        $language = ($document['page']['settings']['language'] ?? 'bn') === 'en' ? 'en' : 'bn';
        $props = $form['props'] ?? [];
        $configured = [];
        foreach ($props['fields'] ?? [] as $field) {
            if (isset($field['key'])) {
                $configured[$field['key']] = $field;
            }
        }

        // No fields configured: the default form. Otherwise exactly what the page lists.
        $keys = $configured ? array_keys($configured) : self::DEFAULT_FIELDS;
        $forced = (array) config('funnel_blocks.required_checkout_fields', ['name', 'phone', 'address']);
        $forced[] = 'district'; // the delivery charge is priced by district

        $fields = [];
        foreach (array_unique(array_merge($keys, $forced)) as $key) {
            $field = $configured[$key] ?? [];
            $isForced = in_array($key, $forced, true);
            $fields[$key] = [
                'key' => $key,
                'enabled' => $isForced ? true : ($field['enabled'] ?? in_array($key, self::DEFAULT_FIELDS, true)),
                'required' => $isForced ? true : (bool) ($field['required'] ?? false),
                'type' => $field['type'] ?? ($key === 'notes' || $key === 'address' ? 'textarea' : 'text'),
                'label' => $field['label'] ?? null,
                'placeholder' => $field['placeholder'] ?? null,
                'width' => $field['width'] ?? 'full',
                'options' => array_values($field['options'] ?? []),
            ];
        }

        return new self(
            $form !== null,
            $fields,
            (bool) ($props['requireConsent'] ?? false),
            (string) ($props['consentText'] ?? ''),
            array_values($selector['props']['allowed'] ?? []),
            $language
        );
    }

    /** Whether the page has a checkout block at all. */
    public function present(): bool
    {
        return $this->present;
    }

    /** @return array<string, array<string, mixed>> every field, enabled or not */
    public function fields(): array
    {
        return $this->fields;
    }

    /** @return array<string, array<string, mixed>> only the ones the customer sees */
    public function enabledFields(): array
    {
        return array_filter($this->fields, fn ($field) => $field['enabled']);
    }

    public function field(string $key): ?array
    {
        return $this->fields[$key] ?? null;
    }

    public function requireConsent(): bool
    {
        return $this->requireConsent;
    }

    public function consentText(): string
    {
        return $this->consentText;
    }

    /** Payment keys the merchant allowed on this page; empty means every method the store offers. */
    public function paymentsAllowed(): array
    {
        return $this->paymentsAllowed;
    }

    public function language(): string
    {
        return $this->language;
    }

    /** Safe description for the page: no server internals. */
    public function toArray(): array
    {
        return [
            'fields' => array_values($this->enabledFields()),
            'require_consent' => $this->requireConsent,
            'consent_text' => $this->consentText,
            'language' => $this->language,
        ];
    }
}
