<?php

namespace App\Services\Funnel\Checkout;

use App\Services\Funnel\Analytics\TrafficSource;

/**
 * A checkout submission after it has been read, cleaned and checked against what
 * the published page asks for. Nothing in here is a price: the customer sends
 * which offers they chose (key, variation, quantity) and their details, and the
 * server prices the rest.
 */
class CheckoutInput
{
    public string $idempotencyKey;
    /** @var mixed untrusted; the offer engine validates it */
    public $selections;
    public ?string $quoteFingerprint;
    public string $name;
    public string $phone;
    public ?string $email;
    public string $address;
    public int $cityId;
    public ?string $area;
    public ?string $notes;
    /** @var array<string, string> label => answer, for the enabled custom fields */
    public array $custom = [];
    public string $paymentType;
    public bool $consent;
    /** @var array<string, string|null> where the visit came from; see attribution() */
    public array $attribution = [];
    /** Signed test-order token from the editor's "Place a test order" link, unverified. */
    public ?string $testToken = null;
    /** Set by the controller; used only to classify the device. */
    public ?string $userAgent = null;

    private const BENGALI_DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

    /**
     * @param  array  $body              decoded JSON body
     * @param  string[] $allowedPayments  payment keys this page may take right now
     * @throws CheckoutException
     */
    public static function fromBody($body, CheckoutConfig $config, array $allowedPayments): self
    {
        $errors = [];
        $body = is_array($body) ? $body : [];
        $customer = is_array($body['customer'] ?? null) ? $body['customer'] : [];

        // A field only people-who-are-not-people fill in: the page keeps it out of sight.
        if (isset($body['website']) && trim((string) $body['website']) !== '') {
            throw new CheckoutException('rejected', 'This submission could not be accepted.', 422);
        }

        $input = new self();

        $key = $body['idempotency_key'] ?? null;
        if (!is_string($key) || !preg_match('/^[A-Za-z0-9_\-:.]{16,100}$/', $key)) {
            $errors['idempotency_key'] = self::problem('invalid_idempotency_key', 'The page did not send a submission id. Reload and try again.');
        }
        $input->idempotencyKey = is_string($key) ? $key : '';

        $input->selections = $body['selections'] ?? [];
        if (!is_array($input->selections) || $input->selections === []) {
            $errors['selections'] = self::problem('empty_selection', 'Choose at least one product.');
        }

        $fingerprint = $body['quote_fingerprint'] ?? null;
        $input->quoteFingerprint = is_string($fingerprint) && preg_match('/^[a-f0-9]{40}$/', $fingerprint) ? $fingerprint : null;

        // Required text fields.
        $input->name = self::text($customer['name'] ?? '', 100);
        if (mb_strlen($input->name) < 2) {
            $errors['name'] = self::problem('required', 'Enter your name.');
        }

        $input->phone = self::phone($customer['phone'] ?? '');
        if (!preg_match('/^01[3-9][0-9]{8}$/', $input->phone)) {
            $errors['phone'] = self::problem('invalid_phone', 'Enter an 11 digit mobile number such as 017XXXXXXXX.');
        }

        $input->address = self::text($customer['address'] ?? '', 300);
        if (mb_strlen($input->address) < 5) {
            $errors['address'] = self::problem('required', 'Enter your full delivery address.');
        }

        $cityId = $customer['district_id'] ?? null;
        $input->cityId = is_int($cityId) || (is_string($cityId) && ctype_digit($cityId)) ? (int) $cityId : 0;
        if ($input->cityId < 1) {
            $errors['district'] = self::problem('required', 'Choose your district.');
        }

        // Optional fields the page can switch on, require, or hide.
        $input->email = self::optional($customer, 'email', $config, 120, $errors);
        if ($input->email !== null && !filter_var($input->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = self::problem('invalid_email', 'Enter a valid email address.');
            $input->email = null;
        }
        $input->area = self::optional($customer, 'area', $config, 100, $errors);
        $input->notes = self::optional($customer, 'notes', $config, 500, $errors);

        foreach ($config->enabledFields() as $key => $field) {
            if (!preg_match('/^custom_[1-5]$/', $key)) {
                continue;
            }
            $label = $field['label'] ?: $key;
            if ($field['type'] === 'checkbox') {
                $ticked = filter_var($customer[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
                if ($field['required'] && !$ticked) {
                    $errors[$key] = self::problem('required', 'This box must be ticked.');
                }
                $input->custom[$label] = $ticked ? 'Yes' : 'No';
                continue;
            }
            $value = self::text($customer[$key] ?? '', 300);
            if ($field['type'] === 'select' && $value !== '' && !in_array($value, $field['options'], true)) {
                $errors[$key] = self::problem('invalid_choice', 'Choose one of the listed options.');
                continue;
            }
            if ($field['required'] && $value === '') {
                $errors[$key] = self::problem('required', 'This field is required.');
                continue;
            }
            if ($value !== '') {
                $input->custom[$label] = $value;
            }
        }

        // Payment.
        $payment = $body['payment_type'] ?? null;
        if (!is_string($payment) || !in_array($payment, $allowedPayments, true)) {
            $errors['payment_type'] = self::problem('invalid_payment', 'Choose one of the available payment methods.');
        }
        $input->paymentType = is_string($payment) ? $payment : '';

        // Delivery: home delivery is the only method the funnel offers.
        $delivery = $body['delivery_method'] ?? 'home_delivery';
        if ($delivery !== 'home_delivery') {
            $errors['delivery_method'] = self::problem('invalid_delivery', 'Home delivery is the only delivery method on this page.');
        }

        $input->consent = filter_var($body['consent'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($config->requireConsent() && !$input->consent) {
            $errors['consent'] = self::problem('consent_required', 'Please tick the box to continue.');
        }

        // Where the visit came from. Optional and never a reason to refuse an order:
        // anything malformed is dropped, not reported.
        $input->attribution = self::attribution($body['attribution'] ?? null);
        $test = $body['test_token'] ?? null;
        $input->testToken = is_string($test) && strlen($test) <= 120 ? $test : null;

        if ($errors !== []) {
            throw CheckoutException::invalid($errors);
        }

        return $input;
    }

    /**
     * Campaign parameters and ad click ids as the page captured them on arrival.
     * Click ids are kept only when the visitor's tracking consent allowed it.
     *
     * @return array<string, string|null>
     */
    public static function attribution($raw): array
    {
        $raw = is_array($raw) ? $raw : [];
        $consent = in_array($raw['consent'] ?? null, ['granted', 'denied'], true) ? $raw['consent'] : 'unknown';
        $click = fn ($value) => is_string($value) && preg_match('/^[A-Za-z0-9_\-.]{4,255}$/', $value) ? $value : null;

        $out = [
            'visitor_id' => TrafficSource::id($raw['visitor_id'] ?? null),
            'session_id' => TrafficSource::id($raw['session_id'] ?? null),
            'utm_source' => TrafficSource::label($raw['utm_source'] ?? null, 150),
            'utm_medium' => TrafficSource::label($raw['utm_medium'] ?? null, 150),
            'utm_campaign' => TrafficSource::label($raw['utm_campaign'] ?? null, 150),
            'utm_content' => TrafficSource::label($raw['utm_content'] ?? null, 150),
            'utm_term' => TrafficSource::label($raw['utm_term'] ?? null, 150),
            'fbclid' => $consent === 'denied' ? null : $click($raw['fbclid'] ?? null),
            'gclid' => $consent === 'denied' ? null : $click($raw['gclid'] ?? null),
            'referrer_host' => TrafficSource::host($raw['referrer_host'] ?? null),
            'consent' => $consent,
        ];
        $kind = $out['fbclid'] ? 'fbclid' : ($out['gclid'] ? 'gclid' : null);
        $out['source'] = TrafficSource::source($out['utm_source'], $out['referrer_host'], $kind);

        return $out;
    }

    /**
     * Hash of everything that decides what is ordered and for whom. The same
     * idempotency key with a different hash is refused instead of replayed.
     */
    public function requestHash(): string
    {
        $selections = is_array($this->selections) ? $this->selections : [];
        $clean = array_map(fn ($s) => is_array($s) ? [(string) ($s['key'] ?? ''), (string) ($s['variant'] ?? ''), (int) ($s['quantity'] ?? 0)] : [], $selections);
        sort($clean);

        return hash('sha256', json_encode([$clean, $this->phone, $this->name, $this->address, $this->cityId, $this->paymentType]));
    }

    // ── cleaning ────────────────────────────────────────────────────────────

    /** Trimmed, without control characters, spaces collapsed. */
    public static function text($value, int $max): string
    {
        if (!is_scalar($value)) {
            return '';
        }
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $value);
        $text = preg_replace('/[ \t]+/u', ' ', (string) $text);
        $text = trim((string) $text);

        return mb_substr($text, 0, $max);
    }

    /** Bangladeshi mobile number as 01XXXXXXXXX: Bengali digits, spaces, dashes, +88 and 88 handled. */
    public static function phone($value): string
    {
        if (!is_scalar($value)) {
            return '';
        }
        $digits = str_replace(self::BENGALI_DIGITS, ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], (string) $value);
        $digits = preg_replace('/\D+/', '', $digits);
        if (strlen($digits) === 13 && strpos($digits, '880') === 0) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 10 && $digits[0] === '1') {
            $digits = '0' . $digits;
        }

        return $digits;
    }

    private static function optional(array $customer, string $key, CheckoutConfig $config, int $max, array &$errors): ?string
    {
        $field = $config->field($key);
        if (!$field || !$field['enabled']) {
            return null;
        }
        $value = self::text($customer[$key] ?? '', $max);
        if ($value === '' && $field['required']) {
            $errors[$key] = self::problem('required', 'This field is required.');
        }

        return $value === '' ? null : $value;
    }

    private static function problem(string $code, string $message): array
    {
        return ['code' => $code, 'message' => $message];
    }
}
