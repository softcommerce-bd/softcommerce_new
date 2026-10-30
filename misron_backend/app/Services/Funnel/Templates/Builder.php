<?php

namespace App\Services\Funnel\Templates;

/**
 * Small helper for writing page documents in code: it numbers block ids, picks
 * the Bengali or English wording, and has the pieces every template shares
 * (announcement bar, order form, FAQ, sticky button).
 *
 * Templates only ever describe blocks that the server registry knows
 * (config/funnel_blocks.php); a test builds all of them and runs each through
 * the page validator, so a typo here cannot ship.
 *
 * Wording that must be replaced by the merchant (real customer reviews, a
 * video link) is wrapped in double square brackets, e.g. "[[Write a real
 * review]]". The publish check refuses any page that still contains such text.
 */
class Builder
{
    public const END_TOKEN = '@@END_IN_7_DAYS@@';

    /** @var string "bn" or "en" */
    public $lang;

    private $counters = [];

    public function __construct(string $lang)
    {
        $this->lang = $lang === 'en' ? 'en' : 'bn';
    }

    public function t(string $bn, string $en): string
    {
        return $this->lang === 'en' ? $en : $bn;
    }

    // ── Generic node helpers ───────────────────────────────────────────────

    public function id(string $type): string
    {
        $this->counters[$type] = ($this->counters[$type] ?? 0) + 1;

        return sprintf('%s_%03d', $type, $this->counters[$type]);
    }

    /**
     * @param array|null $children null for leaf blocks
     * @param array $more extra node entries: advanced, productBindings
     */
    public function node(string $type, array $props = [], array $style = [], ?array $children = null, array $more = []): array
    {
        $node = ['id' => $this->id($type), 'type' => $type];
        if ($props !== []) {
            $node['props'] = $props;
        }
        if ($style !== []) {
            $node['style'] = $style;
        }
        foreach ($more as $key => $value) {
            $node[$key] = $value;
        }
        if ($children !== null) {
            $node['children'] = $children;
        }

        return $node;
    }

    public function section(array $children, array $style = [], array $props = []): array
    {
        return $this->node('section', $props + ['contentWidth' => 'boxed'], $style, $children);
    }

    public function heading(string $text, string $level = 'h2', array $style = []): array
    {
        return $this->node('heading', ['text' => $text, 'level' => $level], $style);
    }

    public function text(string $text, array $style = []): array
    {
        return $this->node('text', ['text' => $text], $style);
    }

    public function button(string $text, string $action = 'checkout', array $style = [], string $variant = 'solid'): array
    {
        return $this->node('button', ['text' => $text, 'action' => $action, 'variant' => $variant], $style);
    }

    public function badge(string $text, string $variant = 'soft'): array
    {
        return $this->node('badge', ['text' => $text, 'variant' => $variant]);
    }

    public function list(array $items, string $icon = 'check-circle'): array
    {
        return $this->node('list', [
            'icon' => $icon,
            'items' => array_map(function ($text) {
                return ['text' => $text];
            }, $items),
        ]);
    }

    /** @param array[] $columns children of each column */
    public function columns(array $columns, array $style = [], array $props = []): array
    {
        $cols = [];
        foreach ($columns as $children) {
            $cols[] = $this->node('column', [], [], $children);
        }

        return $this->node('columns', $props + ['stackOn' => 'mobile'], $style, $cols);
    }

    /** An empty offer: its product is chosen later (see slots in Template). */
    public function binding(string $key, string $title, array $offer = ['type' => 'none'], array $display = []): array
    {
        return [
            'key' => $key,
            'items' => [],
            'offer' => $offer,
            // No title: the card shows the product's own name, read live from the store.
            'display' => ($title === '' ? [] : ['title' => $title]) + $display,
        ];
    }

    // ── Shared pieces ──────────────────────────────────────────────────────

    public function announcement(string $text, string $icon = 'truck'): array
    {
        return $this->node('announcement_ribbon', ['text' => $text, 'icon' => $icon]);
    }

    public function usp(array $items): array
    {
        return $this->node('usp_highlights', [
            'layout' => 'row',
            'items' => array_map(function ($item) {
                return ['icon' => $item[0], 'title' => $item[1]];
            }, $items),
        ]);
    }

    /** @param array[] $items [icon, title, text] */
    public function benefits(string $heading, array $items, ?string $sub = null): array
    {
        $props = ['heading' => $heading, 'items' => array_map(function ($item) {
            return ['icon' => $item[0], 'title' => $item[1], 'text' => $item[2]];
        }, $items)];
        if ($sub !== null) {
            $props['subheading'] = $sub;
        }

        return $this->node('benefits_grid', $props);
    }

    /** @param array[] $items [question, answer] */
    public function faq(string $heading, array $items): array
    {
        return $this->node('faq_accordion', ['heading' => $heading, 'openFirst' => true, 'items' => array_map(function ($item) {
            return ['question' => $item[0], 'answer' => $item[1]];
        }, $items)]);
    }

    /** Three questions every store can answer the same way. */
    public function orderFaq(): array
    {
        return $this->faq($this->t('সাধারণ প্রশ্ন', 'Common questions'), [
            [
                $this->t('কীভাবে অর্ডার করব?', 'How do I order?'),
                $this->t('নিচের ফর্মে আপনার নাম, মোবাইল নম্বর ও ঠিকানা দিয়ে “অর্ডার কনফার্ম করুন” চাপুন।', 'Fill in your name, mobile number and address in the form below and tap the order button.'),
            ],
            [
                $this->t('টাকা কীভাবে দেব?', 'How do I pay?'),
                $this->t('ফর্মে যে পেমেন্ট পদ্ধতিগুলো দেখানো আছে তার যেকোনোটি বেছে নিতে পারবেন।', 'Choose any of the payment methods shown in the form.'),
            ],
            [
                $this->t('অর্ডার করার পর কী হবে?', 'What happens after I order?'),
                $this->t('অর্ডার করার সাথে সাথে আপনি একটি অর্ডার কোড পাবেন। আমরা আপনার অর্ডার প্রস্তুত করে পাঠিয়ে দেব।', 'You get an order code right away. We then prepare and send your order.'),
            ],
        ]);
    }

    public function guarantee(string $heading, string $text, array $items = []): array
    {
        $props = ['heading' => $heading, 'text' => $text, 'icon' => 'shield-check'];
        if ($items !== []) {
            $props['items'] = array_map(function ($item) {
                return ['icon' => $item[0], 'title' => $item[1]];
            }, $items);
        }

        return $this->node('guarantee_section', $props);
    }

    /**
     * The order form with its summary, payment choice and button. The form
     * carries the "checkout" anchor that every order button scrolls to.
     */
    public function orderForm(?string $title = null, array $style = []): array
    {
        $fields = [
            ['key' => 'name', 'label' => $this->t('আপনার নাম', 'Your name'), 'placeholder' => $this->t('পুরো নাম লিখুন', 'Full name'), 'enabled' => true, 'required' => true, 'width' => 'full', 'type' => 'text'],
            ['key' => 'phone', 'label' => $this->t('মোবাইল নম্বর', 'Mobile number'), 'placeholder' => '01XXXXXXXXX', 'enabled' => true, 'required' => true, 'width' => 'full', 'type' => 'text'],
            ['key' => 'address', 'label' => $this->t('সম্পূর্ণ ঠিকানা', 'Full address'), 'placeholder' => $this->t('বাসা/রোড/এলাকা লিখুন', 'House, road, area'), 'enabled' => true, 'required' => true, 'width' => 'full', 'type' => 'textarea'],
            ['key' => 'district', 'label' => $this->t('ডেলিভারি এলাকা', 'Delivery area'), 'placeholder' => $this->t('এলাকা বাছাই করুন', 'Choose your area'), 'enabled' => true, 'required' => true, 'width' => 'full', 'type' => 'select'],
            ['key' => 'notes', 'label' => $this->t('অর্ডার নোট (ঐচ্ছিক)', 'Order note (optional)'), 'placeholder' => $this->t('কোনো বিশেষ নির্দেশনা থাকলে লিখুন', 'Anything we should know'), 'enabled' => true, 'required' => false, 'width' => 'full', 'type' => 'textarea'],
        ];

        $children = [
            $this->node('order_summary', ['title' => $this->t('অর্ডারের সারাংশ', 'Order summary'), 'showImages' => true]),
            $this->node('payment_method_selector', ['label' => $this->t('পেমেন্ট পদ্ধতি', 'Payment method'), 'allowed' => []]),
            $this->node('purchase_button', [
                'text' => $this->t('অর্ডার কনফার্ম করুন', 'Confirm my order'),
                'showTotal' => true,
                'icon' => 'check-circle',
            ]),
        ];

        return $this->node('checkout_form', [
            'title' => $title ?? $this->t('অর্ডার করতে আপনার তথ্য দিন', 'Enter your details to order'),
            'layout' => 'one_column',
            'requireConsent' => false,
            'fields' => $fields,
        ], $style, $children, ['advanced' => ['anchor' => 'checkout']]);
    }

    /** A band holding a heading and the order form. */
    public function checkoutSection(?string $heading = null, array $style = [], ?string $formTitle = null): array
    {
        $children = [];
        if ($heading !== null) {
            $children[] = $this->heading($heading, 'h2', ['desktop' => ['textAlign' => 'center', 'marginBottom' => 16]]);
        }
        $children[] = $this->orderForm($formTitle, ['desktop' => ['maxWidth' => 640], 'mobile' => []]);

        return $this->section($children, $style + ['desktop' => ['paddingTop' => 48, 'paddingBottom' => 56], 'mobile' => ['paddingTop' => 28, 'paddingBottom' => 36]]);
    }

    public function sticky(string $text, ?string $subtext = null, ?string $offerKey = null): array
    {
        $props = ['text' => $text, 'action' => 'checkout', 'position' => 'bottom', 'showOn' => 'all', 'hideNearCheckout' => true];
        if ($subtext !== null) {
            $props['subtext'] = $subtext;
        }
        if ($offerKey !== null) {
            $props['offerKey'] = $offerKey;
            $props['showPrice'] = true;
        }

        return $this->node('sticky_cta', $props);
    }

    public function confirmation(): array
    {
        return $this->node('order_confirmation', [
            'title' => $this->t('ধন্যবাদ! আপনার অর্ডার পেয়েছি', 'Thank you! We have your order'),
            'message' => $this->t('অর্ডার কোডটি সংরক্ষণ করুন। কোনো প্রশ্ন থাকলে এই কোডসহ আমাদের জানান।', 'Keep your order code. Quote it if you have any question.'),
            'showOrderCode' => true,
            'showSummary' => true,
        ]);
    }

    public function countdown(?string $heading = null, ?string $offerKey = null, array $style = []): array
    {
        $props = [
            'endsAt' => self::END_TOKEN,
            'expiredAction' => 'message',
            'expiredMessage' => $this->t('অফারটি শেষ হয়ে গেছে', 'This offer has ended'),
            'labelDays' => $this->t('দিন', 'Days'),
            'labelHours' => $this->t('ঘণ্টা', 'Hours'),
            'labelMinutes' => $this->t('মিনিট', 'Minutes'),
            'labelSeconds' => $this->t('সেকেন্ড', 'Seconds'),
        ];
        if ($heading !== null) {
            $props['heading'] = $heading;
        }
        if ($offerKey !== null) {
            $props['offerKey'] = $offerKey;
        }

        return $this->node('countdown_timer', $props, $style);
    }

    /** Page settings shared by all templates; each template overrides colours and font. */
    public function settings(array $settings = []): array
    {
        return $settings + ['language' => $this->lang, 'fontFamily' => 'hind_siliguri'];
    }
}
