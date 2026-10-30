<?php

namespace Tests\Unit\Funnel;

use App\Services\Funnel\PageValidator;
use App\Services\Funnel\UrlRule;
use Tests\TestCase;

class PageValidatorTest extends TestCase
{
    private function validator(): PageValidator
    {
        return app(PageValidator::class);
    }

    private function page(array $sections, array $settings = []): array
    {
        $page = ['sections' => $sections];
        if ($settings !== []) {
            $page['settings'] = $settings;
        }

        return ['schemaVersion' => 1, 'page' => $page];
    }

    private function codes($result): array
    {
        return array_column($result->errors(), 'code');
    }

    public function test_valid_document_is_normalized_and_unknown_props_are_dropped()
    {
        $result = $this->validator()->validate($this->page([
            [
                'id' => 'sec_001',
                'type' => 'section',
                'props' => ['contentWidth' => 'boxed', 'onclick' => 'alert(1)'],
                'style' => ['mobile' => ['paddingTop' => '16', 'fontWeight' => 700], 'watch' => ['x' => 1]],
                'children' => [
                    ['id' => 'head_001', 'type' => 'heading', 'props' => ['text' => '  ব্লেন্ডার অফার  ', 'level' => 'h1']],
                ],
            ],
        ], ['accentColor' => '#FF6600']));

        $this->assertTrue($result->isValid(), json_encode($result->errors()));
        $section = $result->document['page']['sections'][0];
        $this->assertSame(['contentWidth' => 'boxed'], $section['props']);
        $this->assertSame(['mobile' => ['paddingTop' => 16, 'fontWeight' => '700']], $section['style']);
        $this->assertSame('ব্লেন্ডার অফার', $section['children'][0]['props']['text']);
        $this->assertSame('#ff6600', $result->document['page']['settings']['accentColor']);
        $this->assertContains('unknown_prop', array_column($result->warnings(), 'code'));
        $this->assertContains('unknown_breakpoint', array_column($result->warnings(), 'code'));
    }

    public function test_unknown_types_bad_nesting_and_duplicate_ids_are_errors()
    {
        $result = $this->validator()->validate($this->page([
            ['id' => 'x_001', 'type' => 'script_block'],
            ['id' => 'h_001', 'type' => 'heading'],
            ['id' => 'sec_001', 'type' => 'section', 'children' => [
                ['id' => 'col_001', 'type' => 'column'],
                ['id' => 'sec_001', 'type' => 'text'],
            ]],
            ['id' => 'img_001', 'type' => 'image', 'children' => [['id' => 'text_001', 'type' => 'text']]],
        ]));

        $codes = $this->codes($result);
        $this->assertContains('unknown_block', $codes);
        $this->assertContains('invalid_nesting', $codes);
        $this->assertContains('duplicate_id', $codes);
        $this->assertFalse($result->isValid());
    }

    public function test_depth_node_count_and_size_limits()
    {
        $node = ['id' => 'leaf_000', 'type' => 'text', 'props' => ['text' => 'deep']];
        for ($i = 1; $i <= 9; $i++) {
            $node = ['id' => "cont_00{$i}", 'type' => 'container', 'children' => [$node]];
        }
        $deep = $this->validator()->validate($this->page([['id' => 'sec_001', 'type' => 'section', 'children' => [$node]]]));
        $this->assertContains('too_deep', $this->codes($deep));

        $many = [];
        for ($i = 0; $i < 700; $i++) {
            $many[] = ['id' => 'text_' . $i, 'type' => 'text'];
        }
        $wide = $this->validator()->validate($this->page([['id' => 'sec_001', 'type' => 'section', 'children' => $many]]));
        $this->assertContains('too_many_blocks', $this->codes($wide));

        $huge = $this->validator()->validate(str_repeat(' ', 1024 * 1024 + 1));
        $this->assertContains('too_large', $this->codes($huge));
    }

    public function test_rich_text_is_sanitized_and_keeps_bengali()
    {
        $html = '<p onclick="steal()">দাম <b>৳১৯৯০</b><script>alert(1)</script>'
            . '<a href="javascript:alert(1)">bad</a> <a href="https://misron.com" target="_blank">ok</a>'
            . '<img src=x onerror=alert(1)><iframe src="https://evil.test"></iframe><span style="x">s</span></p>';

        $result = $this->validator()->validate($this->page([
            ['id' => 'sec_001', 'type' => 'section', 'children' => [
                ['id' => 'rich_001', 'type' => 'rich_text', 'props' => ['html' => $html]],
            ]],
        ]));

        $clean = $result->document['page']['sections'][0]['children'][0]['props']['html'];
        $this->assertStringContainsString('দাম <b>৳১৯৯০</b>', $clean);
        $this->assertStringContainsString('<a href="https://misron.com" target="_blank" rel="noopener noreferrer nofollow">ok</a>', $clean);
        foreach (['script', 'onclick', 'javascript:', '<img', 'onerror', 'iframe', 'style='] as $needle) {
            $this->assertStringNotContainsString($needle, $clean);
        }
    }

    public function test_url_rule_rejects_script_schemes_and_protocol_relative_urls()
    {
        foreach (['https://misron.com/x?y=1', '/funnel/offer', '#checkout', 'tel:+8801700000000', 'mailto:help@misron.com'] as $ok) {
            $this->assertTrue(UrlRule::isSafe($ok), $ok);
        }
        foreach (['javascript:alert(1)', 'JaVaScRiPt:alert(1)', "java\tscript:alert(1)", 'data:text/html,x', '//evil.test', '/\\evil.test', 'vbscript:x', 'https://user:pw@evil.test'] as $bad) {
            $this->assertFalse(UrlRule::isSafe($bad), $bad);
        }
    }

    public function test_required_checkout_fields_are_forced_on()
    {
        $result = $this->validator()->validate($this->page([
            ['id' => 'checkout_001', 'type' => 'checkout_form', 'props' => ['fields' => [
                ['key' => 'phone', 'enabled' => false, 'required' => false, 'label' => 'মোবাইল নম্বর'],
                ['key' => 'notes', 'enabled' => true, 'required' => false],
            ]]],
        ]));

        $fields = collect($result->document['page']['sections'][0]['props']['fields'])->keyBy('key');
        foreach (['name', 'phone', 'address'] as $key) {
            $this->assertTrue($fields[$key]['enabled'], $key);
            $this->assertTrue($fields[$key]['required'], $key);
        }
        $this->assertFalse($fields['notes']['required']);
        $this->assertSame('মোবাইল নম্বর', $fields['phone']['label']);
    }

    public function test_publish_mode_requires_a_complete_page()
    {
        $empty = $this->validator()->validate($this->page([]), PageValidator::MODE_PUBLISH);
        $this->assertContains('empty_page', $this->codes($empty));

        $noCheckout = $this->validator()->validate($this->page([
            ['id' => 'offers_001', 'type' => 'product_offer_selector', 'productBindings' => []],
            ['id' => 'timer_001', 'type' => 'countdown_timer'],
        ]), PageValidator::MODE_PUBLISH);
        $codes = $this->codes($noCheckout);
        // Checkout forms arrive in a later phase: until then it is a warning.
        $this->assertNotContains('missing_checkout', $codes);
        $this->assertContains('missing_checkout', array_column($noCheckout->warnings(), 'code'));
        $this->assertContains('missing_products', $codes);
        $this->assertContains('countdown_without_end', $codes);

        config(['funnel.require_checkout' => true]);
        $strict = $this->validator()->validate($this->page([
            ['id' => 'offers_001', 'type' => 'product_offer_selector', 'productBindings' => []],
        ]), PageValidator::MODE_PUBLISH);
        $this->assertContains('missing_checkout', $this->codes($strict));
        config(['funnel.require_checkout' => false]);

        // The same page is still a valid draft: merchants save before finishing.
        $draft = $this->validator()->validate($this->page([
            ['id' => 'offers_001', 'type' => 'product_offer_selector', 'productBindings' => []],
            ['id' => 'timer_001', 'type' => 'countdown_timer'],
        ]));
        $this->assertTrue($draft->isValid(), json_encode($draft->errors()));
    }

    public function test_offer_rules()
    {
        $binding = function (array $offer, string $key = 'offer_one') {
            return ['key' => $key, 'items' => [['productId' => 5]], 'offer' => $offer];
        };

        $result = $this->validator()->validate($this->page([
            ['id' => 'offers_001', 'type' => 'product_offer_selector', 'productBindings' => [
                $binding(['type' => 'percent', 'value' => 150], 'offer_a'),
                $binding(['type' => 'fixed_price', 'value' => 0], 'offer_b'),
                $binding(['type' => 'none', 'startsAt' => '2026-12-01T00:00:00+06:00', 'endsAt' => '2026-11-01T00:00:00+06:00'], 'offer_c'),
                $binding(['type' => 'none'], 'offer_c'),
                $binding(['type' => 'free_gift', 'giftProductId' => 9], 'offer_d'),
            ]],
            ['id' => 'sec_001', 'type' => 'section', 'children' => [
                ['id' => 'price_001', 'type' => 'product_price', 'props' => ['offerKey' => 'offer_missing']],
            ]],
        ]));

        $codes = $this->codes($result);
        $this->assertSame(2, count(array_keys($codes, 'invalid_discount')));
        $this->assertContains('invalid_period', $codes);
        $this->assertContains('duplicate_offer_key', $codes);
        $this->assertContains('unknown_offer', array_column($result->warnings(), 'code'));
        $this->assertSame([5, 9], $result->productIdList());
    }

    public function test_quantity_discount_tiers_are_validated_and_sorted()
    {
        $page = function (array $tiers) {
            return $this->page([['id' => 'sel_001', 'type' => 'product_offer_selector', 'productBindings' => [
                ['key' => 'offer_a', 'items' => [['productId' => 5]], 'offer' => ['type' => 'quantity_discount', 'tiers' => $tiers]],
            ]]]);
        };

        $ok = $this->validator()->validate($page([
            ['minQty' => 5, 'type' => 'percent', 'value' => 15],
            ['minQty' => 3, 'type' => 'fixed_amount', 'value' => 50],
        ]), PageValidator::MODE_PUBLISH);
        $this->assertTrue($ok->isValid(), json_encode($ok->errors()));
        $tiers = $ok->document['page']['sections'][0]['productBindings'][0]['offer']['tiers'];
        $this->assertSame([3, 5], array_column($tiers, 'minQty'));

        $bad = $this->validator()->validate($page([
            ['minQty' => 3, 'type' => 'percent', 'value' => 150],
            ['minQty' => 3, 'type' => 'fixed_amount', 'value' => 0],
            ['minQty' => 1, 'type' => 'percent', 'value' => 5],
        ]));
        $codes = $this->codes($bad);
        $this->assertContains('invalid_discount', $codes);
        $this->assertContains('duplicate_tier', $codes);
        $this->assertContains('out_of_range', $codes);

        $empty = $this->validator()->validate($page([]), PageValidator::MODE_PUBLISH);
        $this->assertContains('missing_discount', $this->codes($empty));
    }

    public function test_offer_settings_that_do_not_belong_to_the_type_are_dropped()
    {
        $result = $this->validator()->validate($this->page([['id' => 'card_001', 'type' => 'product_card', 'productBindings' => [
            ['key' => 'offer_a', 'items' => [['productId' => 5, 'lockVariant' => true, 'variant' => 'M']], 'offer' => [
                'type' => 'bxgy', 'buyQty' => 2, 'getQty' => 1, 'value' => 999, 'giftProductId' => 9, 'freeDelivery' => false, 'maxPerOrder' => 4,
            ]],
        ]]]));

        $offer = $result->document['page']['sections'][0]['productBindings'][0]['offer'];
        $this->assertEqualsCanonicalizing(['type' => 'bxgy', 'maxPerOrder' => 4, 'buyQty' => 2, 'getQty' => 1], $offer);
        $item = $result->document['page']['sections'][0]['productBindings'][0]['items'][0];
        $this->assertTrue($item['lockVariant']);
        // A product card takes exactly one offer.
        $two = $this->validator()->validate($this->page([['id' => 'card_001', 'type' => 'product_card', 'productBindings' => [
            ['key' => 'offer_a', 'items' => [['productId' => 5]]], ['key' => 'offer_b', 'items' => [['productId' => 6]]],
        ]]]));
        $this->assertContains('too_many_offers', $this->codes($two));
    }

    public function test_binding_lists_validate_for_the_live_preview()
    {
        $ok = $this->validator()->validateBindingList([['key' => 'offer_a', 'items' => [['productId' => 5]], 'offer' => ['type' => 'percent', 'value' => 10]]]);
        $this->assertTrue($ok->isValid());
        $this->assertSame('offer_a', $ok->document['bindings'][0]['key']);

        $twice = $this->validator()->validateBindingList([
            ['key' => 'offer_a', 'items' => [['productId' => 5]]],
            ['key' => 'offer_b', 'items' => [['productId' => 5]]],
        ]);
        $this->assertContains('overlapping_offers', array_column($twice->warnings(), 'code'));

        $this->assertFalse($this->validator()->validateBindingList('nope')->isValid());
        $this->assertFalse($this->validator()->validateBindingList([['key' => 'x', 'items' => []]])->isValid());
    }

    public function test_countdown_linked_to_offer_with_end_date_can_publish()
    {
        $result = $this->validator()->validate($this->page([
            ['id' => 'timer_001', 'type' => 'countdown_timer', 'props' => ['offerKey' => 'offer_one']],
            ['id' => 'card_001', 'type' => 'product_card', 'productBindings' => [
                ['key' => 'offer_one', 'items' => [['productId' => 3]], 'offer' => ['type' => 'percent', 'value' => 20, 'endsAt' => '2026-12-31T23:59:00+06:00']],
            ]],
            ['id' => 'checkout_001', 'type' => 'checkout_form', 'children' => [['id' => 'buy_001', 'type' => 'purchase_button']]],
        ]), PageValidator::MODE_PUBLISH);

        $this->assertTrue($result->isValid(), json_encode($result->errors()));
    }

    public function test_an_order_form_needs_an_order_button_inside_it_to_publish()
    {
        $page = fn (array $form) => $this->page([
            ['id' => 'card_001', 'type' => 'product_card', 'productBindings' => [['key' => 'offer_one', 'items' => [['productId' => 3]], 'offer' => ['type' => 'none']]]],
            $form,
        ]);

        $without = $this->validator()->validate($page(['id' => 'checkout_001', 'type' => 'checkout_form', 'children' => []]), PageValidator::MODE_PUBLISH);
        $this->assertContains('checkout_no_button', $this->codes($without));

        // A button elsewhere on the page does not submit the form.
        $outside = $this->validator()->validate($this->page([
            ['id' => 'card_001', 'type' => 'product_card', 'productBindings' => [['key' => 'offer_one', 'items' => [['productId' => 3]], 'offer' => ['type' => 'none']]]],
            ['id' => 'checkout_001', 'type' => 'checkout_form', 'children' => []],
            ['id' => 'buy_001', 'type' => 'section', 'children' => [['id' => 'buy_002', 'type' => 'purchase_button']]],
        ]), PageValidator::MODE_PUBLISH);
        $this->assertContains('checkout_no_button', $this->codes($outside));

        // Next to other parts of the form it counts too.
        $beside = $this->validator()->validate($page(['id' => 'checkout_001', 'type' => 'checkout_form', 'children' => [
            ['id' => 'pay_001', 'type' => 'payment_method_selector'],
            ['id' => 'buy_001', 'type' => 'purchase_button'],
        ]]), PageValidator::MODE_PUBLISH);
        $this->assertNotContains('checkout_no_button', $this->codes($beside));

        // Saving a draft never demands it.
        $draft = $this->validator()->validate($page(['id' => 'checkout_001', 'type' => 'checkout_form', 'children' => []]));
        $this->assertNotContains('checkout_no_button', $this->codes($draft));
    }

    public function test_only_one_checkout_and_sticky_cta_per_page()
    {
        $result = $this->validator()->validate($this->page([
            ['id' => 'checkout_001', 'type' => 'checkout_form'],
            ['id' => 'checkout_002', 'type' => 'checkout_form'],
            ['id' => 'sticky_001', 'type' => 'sticky_cta'],
            ['id' => 'sticky_002', 'type' => 'sticky_cta'],
        ]));

        $this->assertSame(2, count(array_keys($this->codes($result), 'too_many_of_type')));
    }

    public function test_schema_versions()
    {
        $newer = $this->validator()->validate(['schemaVersion' => 99, 'page' => ['sections' => []]]);
        $this->assertContains('unsupported_schema', $this->codes($newer));

        // A pre-versioning document (bare section list) is upgraded.
        $legacy = $this->validator()->validate([
            ['id' => 'sec_001', 'type' => 'section', 'children' => []],
        ]);
        $this->assertTrue($legacy->isValid(), json_encode($legacy->errors()));
        $this->assertSame(1, $legacy->document['schemaVersion']);
        $this->assertSame('sec_001', $legacy->document['page']['sections'][0]['id']);
    }

    public function test_bad_values_are_reported_with_their_path()
    {
        $result = $this->validator()->validate($this->page([
            ['id' => 'sec_001', 'type' => 'section', 'style' => ['desktop' => ['paddingTop' => 9999, 'color' => 'red; background:url(x)']], 'children' => [
                ['id' => 'btn_001', 'type' => 'button', 'props' => ['text' => str_repeat('x', 121), 'link' => 'javascript:void(0)', 'action' => 'hack']],
                ['id' => 'img_001', 'type' => 'image', 'props' => ['image' => ['uploadId' => -3, 'url' => 'data:image/png;base64,xx']]],
            ]],
        ]));

        $paths = array_column($result->errors(), 'path');
        $this->assertContains('page.sections[0].style.desktop.paddingTop', $paths);
        $this->assertContains('page.sections[0].style.desktop.color', $paths);
        $this->assertContains('page.sections[0].children[0].props.text', $paths);
        $this->assertContains('page.sections[0].children[0].props.link', $paths);
        $this->assertContains('page.sections[0].children[0].props.action', $paths);
        $this->assertContains('page.sections[0].children[1].props.image.uploadId', $paths);
    }
}
