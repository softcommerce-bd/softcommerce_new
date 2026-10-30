<?php

/*
|--------------------------------------------------------------------------
| Funnel block registry (server side)
|--------------------------------------------------------------------------
|
| Every component a funnel page may contain, where it may be placed and which
| props it accepts. The validator rejects any type not listed here and strips
| any prop not listed for its type, so this file is the allowlist for what can
| reach the public page. The editor (Next.js) keeps the visual side of each
| block — defaults, controls, renderer — keyed by the same type names.
|
| Prop rule syntax:
|   string:N        single line of plain text, at most N characters
|   text:N          multi-line plain text
|   rich            sanitized HTML (see RichTextSanitizer)
|   int:MIN:MAX     integer in range
|   num:MIN:MAX     number in range
|   bool
|   enum:a,b,c
|   color           #rgb, #rrggbb, #rrggbbaa or "transparent"
|   url             http(s), site-relative path, #anchor, tel: or mailto:
|   image           { uploadId, url, alt }
|   icon            icon name from the editor's icon set
|   key             reference to an offer key on the same page
|   datetime        ISO 8601 date-time
|   phone           digits, spaces, dashes and a leading +
|   strings:N:M     list of at most N plain strings of at most M characters
|   list:ITEM:N     list of at most N objects shaped by items[ITEM]
|
| "parents" lists where a block may sit; "root" is the top level of the page.
| "children" is null for leaf blocks, "*" for containers that accept any block
| whose own "parents" allows them, or an explicit list.
|
*/

$elementParents = ['section', 'container', 'column', 'grid', 'checkout_form'];
$blockParents = ['root', 'section', 'container', 'column'];
$checkoutParts = ['section', 'container', 'column', 'checkout_form'];
$productParts = ['section', 'container', 'column', 'grid'];

$cta = [
    'ctaText' => 'string:120',
    'ctaAction' => 'enum:scroll,checkout,link',
    'ctaLink' => 'url',
];

return [

    'blocks' => [

        // ── Layout ─────────────────────────────────────────────────────────
        'section' => [
            'category' => 'basic',
            'parents' => ['root'],
            'children' => '*',
            'props' => [
                'contentWidth' => 'enum:boxed,full',
                'backgroundImage' => 'image',
                'backgroundOverlay' => 'color',
                'tag' => 'enum:section,header,footer',
            ],
        ],
        'container' => [
            'category' => 'basic',
            'parents' => ['section', 'container', 'column'],
            'children' => '*',
            'props' => [
                'direction' => 'enum:vertical,horizontal',
                'wrap' => 'bool',
            ],
        ],
        'grid' => [
            'category' => 'basic',
            'parents' => ['section', 'container', 'column'],
            'children' => '*',
            'props' => [],
        ],
        'columns' => [
            'category' => 'basic',
            'parents' => ['section', 'container'],
            'children' => ['column'],
            'min_children' => 1,
            'max_children' => 6,
            'props' => [
                'stackOn' => 'enum:none,mobile,tablet',
                'reverseOnStack' => 'bool',
            ],
        ],
        'column' => [
            'category' => 'basic',
            'parents' => ['columns'],
            'children' => '*',
            'props' => [],
        ],

        // ── Basic elements ─────────────────────────────────────────────────
        'heading' => [
            'category' => 'basic',
            'parents' => $elementParents,
            'children' => null,
            'props' => [
                'text' => 'string:300',
                'level' => 'enum:h1,h2,h3,h4,h5,h6',
                'link' => 'url',
            ],
        ],
        'text' => [
            'category' => 'basic',
            'parents' => $elementParents,
            'children' => null,
            'props' => [
                'text' => 'text:5000',
            ],
        ],
        'rich_text' => [
            'category' => 'basic',
            'parents' => $elementParents,
            'children' => null,
            'props' => [
                'html' => 'rich',
            ],
        ],
        'image' => [
            'category' => 'basic',
            'parents' => $elementParents,
            'children' => null,
            'props' => [
                'image' => 'image',
                'link' => 'url',
                'caption' => 'string:300',
            ],
        ],
        'image_gallery' => [
            'category' => 'basic',
            'parents' => $elementParents,
            'children' => null,
            'props' => [
                'images' => 'list:gallery_image:30',
                'layout' => 'enum:grid,carousel',
                'lightbox' => 'bool',
            ],
        ],
        'video' => [
            'category' => 'basic',
            'parents' => $elementParents,
            'children' => null,
            'props' => [
                'provider' => 'enum:youtube,facebook,vimeo,file',
                'url' => 'url',
                'poster' => 'image',
                'autoplay' => 'bool',
                'muted' => 'bool',
                'loop' => 'bool',
                'controls' => 'bool',
            ],
        ],
        'button' => [
            'category' => 'basic',
            'parents' => $elementParents,
            'children' => null,
            'props' => [
                'text' => 'string:120',
                'action' => 'enum:link,scroll,checkout,whatsapp,call',
                'link' => 'url',
                'target' => 'enum:_self,_blank',
                'icon' => 'icon',
                'variant' => 'enum:solid,outline,ghost',
            ],
        ],
        'icon' => [
            'category' => 'basic',
            'parents' => $elementParents,
            'children' => null,
            'props' => [
                'icon' => 'icon',
                'link' => 'url',
            ],
        ],
        'divider' => [
            'category' => 'basic',
            'parents' => $elementParents,
            'children' => null,
            'props' => [
                'lineStyle' => 'enum:solid,dashed,dotted',
            ],
        ],
        'spacer' => [
            'category' => 'basic',
            'parents' => $elementParents,
            'children' => null,
            'props' => [],
        ],
        'badge' => [
            'category' => 'basic',
            'parents' => $elementParents,
            'children' => null,
            'props' => [
                'text' => 'string:60',
                'variant' => 'enum:solid,soft,outline',
            ],
        ],
        'list' => [
            'category' => 'basic',
            'parents' => $elementParents,
            'children' => null,
            'props' => [
                'items' => 'list:list_item:50',
                'ordered' => 'bool',
                'icon' => 'icon',
            ],
        ],

        // ── Marketing ──────────────────────────────────────────────────────
        'promo_hero' => [
            'category' => 'marketing',
            'parents' => $blockParents,
            'children' => null,
            'props' => [
                'eyebrow' => 'string:120',
                'headline' => 'string:300',
                'subheadline' => 'text:1000',
                'image' => 'image',
                'backgroundImage' => 'image',
                'layout' => 'enum:image_right,image_left,image_top,background',
                'offerKey' => 'key',
                'showPrice' => 'bool',
                'secondaryText' => 'string:200',
            ] + $cta,
        ],
        'image_banner' => [
            'category' => 'marketing',
            'parents' => $blockParents,
            'children' => null,
            'props' => [
                'image' => 'image',
                'mobileImage' => 'image',
                'link' => 'url',
            ],
        ],
        'video_hero' => [
            'category' => 'marketing',
            'parents' => $blockParents,
            'children' => null,
            'props' => [
                'provider' => 'enum:youtube,facebook,vimeo,file',
                'url' => 'url',
                'poster' => 'image',
                'headline' => 'string:300',
                'subheadline' => 'text:1000',
            ] + $cta,
        ],
        'announcement_ribbon' => [
            'category' => 'marketing',
            'parents' => ['root', 'section'],
            'children' => null,
            'props' => [
                'text' => 'string:300',
                'link' => 'url',
                'icon' => 'icon',
                'scrolling' => 'bool',
            ],
        ],
        // Counts down to a real, configured moment only. There is deliberately
        // no "evergreen" mode that restarts per visitor.
        'countdown_timer' => [
            'category' => 'marketing',
            'parents' => array_merge($blockParents, ['grid', 'checkout_form']),
            'children' => null,
            'props' => [
                'endsAt' => 'datetime',
                'offerKey' => 'key',
                'expiredAction' => 'enum:hide,message',
                'expiredMessage' => 'string:200',
                'labelDays' => 'string:20',
                'labelHours' => 'string:20',
                'labelMinutes' => 'string:20',
                'labelSeconds' => 'string:20',
                'heading' => 'string:200',
            ],
        ],
        'discount_badge' => [
            'category' => 'marketing',
            'parents' => $productParts,
            'children' => null,
            'props' => [
                'text' => 'string:60',
                'offerKey' => 'key',
                'shape' => 'enum:circle,ribbon,pill',
            ],
        ],
        'usp_highlights' => [
            'category' => 'marketing',
            'parents' => $blockParents,
            'children' => null,
            'props' => [
                'items' => 'list:icon_item:12',
                'layout' => 'enum:row,grid',
            ],
        ],
        'trust_icons' => [
            'category' => 'marketing',
            'parents' => $blockParents,
            'children' => null,
            'props' => [
                'items' => 'list:icon_item:12',
            ],
        ],
        'benefits_grid' => [
            'category' => 'marketing',
            'parents' => $blockParents,
            'children' => null,
            'props' => [
                'heading' => 'string:200',
                'subheading' => 'text:600',
                'items' => 'list:benefit_item:24',
            ],
        ],
        'testimonial_cards' => [
            'category' => 'marketing',
            'parents' => $blockParents,
            'children' => null,
            'props' => [
                'heading' => 'string:200',
                'items' => 'list:testimonial_item:24',
                'layout' => 'enum:grid,carousel',
            ],
        ],
        // Reads approved reviews from the existing reviews table for a bound
        // product; nothing is typed in by hand.
        'customer_reviews' => [
            'category' => 'marketing',
            'parents' => $blockParents,
            'children' => null,
            'props' => [
                'heading' => 'string:200',
                'offerKey' => 'key',
                'limit' => 'int:1:20',
                'minRating' => 'int:1:5',
            ],
        ],
        'faq_accordion' => [
            'category' => 'marketing',
            'parents' => $blockParents,
            'children' => null,
            'props' => [
                'heading' => 'string:200',
                'items' => 'list:faq_item:40',
                'openFirst' => 'bool',
            ],
        ],
        'before_after_slider' => [
            'category' => 'marketing',
            'parents' => $blockParents,
            'children' => null,
            'props' => [
                'before' => 'image',
                'after' => 'image',
                'beforeLabel' => 'string:40',
                'afterLabel' => 'string:40',
                'initial' => 'int:0:100',
            ],
        ],
        'comparison_table' => [
            'category' => 'marketing',
            'parents' => $blockParents,
            'children' => null,
            'props' => [
                'heading' => 'string:200',
                'headers' => 'strings:6:80',
                'rows' => 'list:comparison_row:30',
                'highlightColumn' => 'int:0:6',
            ],
        ],
        'sticky_cta' => [
            'category' => 'marketing',
            'parents' => ['root'],
            'children' => null,
            'max_per_page' => 1,
            'props' => [
                'text' => 'string:120',
                'subtext' => 'string:200',
                'action' => 'enum:scroll,checkout,link,whatsapp',
                'link' => 'url',
                'offerKey' => 'key',
                'showPrice' => 'bool',
                'position' => 'enum:bottom,top',
                'showOn' => 'enum:all,mobile,desktop',
                'hideNearCheckout' => 'bool',
            ],
        ],
        'floating_whatsapp' => [
            'category' => 'marketing',
            'parents' => ['root'],
            'children' => null,
            'max_per_page' => 1,
            'props' => [
                'phone' => 'phone',
                'message' => 'string:500',
                'position' => 'enum:left,right',
                'label' => 'string:60',
            ],
        ],
        'guarantee_section' => [
            'category' => 'marketing',
            'parents' => $blockParents,
            'children' => null,
            'props' => [
                'heading' => 'string:200',
                'text' => 'text:2000',
                'icon' => 'icon',
                'items' => 'list:icon_item:8',
            ],
        ],

        // ── E-commerce ─────────────────────────────────────────────────────
        // Blocks with "bindings" define offers: references to existing
        // products plus the promotion applied to them. Other product blocks
        // point at one of those offers through "offerKey".
        'product_card' => [
            'category' => 'ecommerce',
            'parents' => array_merge(['root'], $productParts),
            'children' => null,
            'bindings' => [1, 1],
            'props' => [
                'layout' => 'enum:vertical,horizontal',
                'showDescription' => 'bool',
                'showStock' => 'bool',
                'showOriginalPrice' => 'bool',
                'showSavings' => 'bool',
                'ctaText' => 'string:60',
                'ctaAction' => 'enum:select,select_and_scroll',
                'unavailableText' => 'string:200',
            ],
        ],
        'product_offer_selector' => [
            'category' => 'ecommerce',
            'parents' => $blockParents,
            'children' => null,
            'bindings' => [1, 40],
            'props' => [
                'heading' => 'string:200',
                'selectionMode' => 'enum:single,multiple',
                'minSelect' => 'int:0:40',
                'maxSelect' => 'int:1:40',
                'cardLayout' => 'enum:vertical,horizontal,compact',
                'showDescription' => 'bool',
                'showOriginalPrice' => 'bool',
                'showSavings' => 'bool',
                'showStock' => 'bool',
                'allowQuantity' => 'bool',
                'buttonText' => 'string:60',
                'selectedText' => 'string:60',
                'unavailableText' => 'string:200',
            ],
        ],
        'bundle_offer_selector' => [
            'category' => 'ecommerce',
            'parents' => $blockParents,
            'children' => null,
            'bindings' => [1, 10],
            'props' => [
                'heading' => 'string:200',
                'layout' => 'enum:stack,grid',
                'showSavings' => 'bool',
                'showPerUnit' => 'bool',
                'unavailableText' => 'string:200',
            ],
        ],
        'product_gallery' => [
            'category' => 'ecommerce',
            'parents' => $productParts,
            'children' => null,
            'props' => [
                'offerKey' => 'key',
                'layout' => 'enum:thumbs_bottom,thumbs_left,carousel',
            ],
        ],
        'product_price' => [
            'category' => 'ecommerce',
            'parents' => $productParts,
            'children' => null,
            'props' => [
                'offerKey' => 'key',
                'showOriginal' => 'bool',
                'showSavings' => 'bool',
            ],
        ],
        'product_variations' => [
            'category' => 'ecommerce',
            'parents' => $productParts,
            'children' => null,
            'props' => [
                'offerKey' => 'key',
                'display' => 'enum:buttons,dropdown',
                'label' => 'string:60',
            ],
        ],
        'quantity_selector' => [
            'category' => 'ecommerce',
            'parents' => $productParts,
            'children' => null,
            'props' => [
                'offerKey' => 'key',
                'label' => 'string:60',
                'max' => 'int:1:99',
            ],
        ],
        'discount_summary' => [
            'category' => 'ecommerce',
            'parents' => $checkoutParts,
            'children' => null,
            'props' => [
                'title' => 'string:120',
                'label' => 'string:120',
                'showItems' => 'bool',
                'showTax' => 'bool',
                'showWhenZero' => 'bool',
                'emptyText' => 'string:200',
            ],
        ],
        'delivery_charges' => [
            'category' => 'ecommerce',
            'parents' => $checkoutParts,
            'children' => null,
            'props' => [
                'label' => 'string:120',
                'note' => 'text:500',
            ],
        ],
        'coupon_input' => [
            'category' => 'ecommerce',
            'parents' => $checkoutParts,
            'children' => null,
            'props' => [
                'label' => 'string:120',
                'placeholder' => 'string:120',
                'buttonText' => 'string:60',
            ],
        ],
        'order_summary' => [
            'category' => 'ecommerce',
            'parents' => $checkoutParts,
            'children' => null,
            'props' => [
                'title' => 'string:120',
                'showImages' => 'bool',
                'collapsibleOnMobile' => 'bool',
            ],
        ],
        'checkout_form' => [
            'category' => 'ecommerce',
            'parents' => $blockParents,
            'children' => '*',
            'max_per_page' => 1,
            'props' => [
                'title' => 'string:200',
                'fields' => 'list:checkout_field:20',
                'layout' => 'enum:one_column,two_column',
                'consentText' => 'string:300',
                'requireConsent' => 'bool',
            ],
        ],
        'payment_method_selector' => [
            'category' => 'ecommerce',
            'parents' => $checkoutParts,
            'children' => null,
            'props' => [
                'label' => 'string:120',
                'allowed' => 'strings:10:40',
            ],
        ],
        'purchase_button' => [
            'category' => 'ecommerce',
            'parents' => $checkoutParts,
            'children' => null,
            'props' => [
                'text' => 'string:80',
                'subtext' => 'string:120',
                'showTotal' => 'bool',
                'icon' => 'icon',
            ],
        ],
        'order_confirmation' => [
            'category' => 'ecommerce',
            'parents' => ['root', 'section'],
            'children' => null,
            'max_per_page' => 1,
            'props' => [
                'title' => 'string:200',
                'message' => 'text:2000',
                'showOrderCode' => 'bool',
                'showSummary' => 'bool',
                'ctaText' => 'string:80',
                'ctaLink' => 'url',
            ],
        ],
    ],

    // Shapes of the objects inside "list:" props.
    'items' => [
        'gallery_image' => ['image' => 'image', 'caption' => 'string:200'],
        'list_item' => ['text' => 'string:300', 'icon' => 'icon'],
        'icon_item' => ['icon' => 'icon', 'image' => 'image', 'title' => 'string:120', 'text' => 'string:300'],
        'benefit_item' => ['icon' => 'icon', 'image' => 'image', 'title' => 'string:150', 'text' => 'text:600'],
        'testimonial_item' => [
            'name' => 'string:80',
            'role' => 'string:80',
            'text' => 'text:1000',
            'rating' => 'int:1:5',
            'avatar' => 'image',
        ],
        'faq_item' => ['question' => 'string:300', 'answer' => 'text:3000'],
        'offer_tier' => ['minQty' => 'int:2:999', 'type' => 'enum:percent,fixed_amount', 'value' => 'num:0:100000000'],
        'comparison_row' => ['label' => 'string:150', 'values' => 'strings:6:150'],
        'checkout_field' => [
            'key' => 'enum:name,phone,email,address,district,area,delivery_method,notes,custom_1,custom_2,custom_3,custom_4,custom_5',
            'label' => 'string:120',
            'placeholder' => 'string:160',
            'required' => 'bool',
            'enabled' => 'bool',
            'width' => 'enum:full,half',
            'type' => 'enum:text,textarea,select,checkbox',
            'options' => 'strings:20:80',
        ],
    ],

    // Checkout fields an order cannot be fulfilled without. The validator
    // forces them on and required whatever the stored props say.
    'required_checkout_fields' => ['name', 'phone', 'address'],

    // Style properties, allowed per breakpoint (desktop, tablet, mobile).
    // Values are tokens and numbers only; no raw CSS strings reach the page.
    'style' => [
        'paddingTop' => 'int:0:400',
        'paddingRight' => 'int:0:400',
        'paddingBottom' => 'int:0:400',
        'paddingLeft' => 'int:0:400',
        'marginTop' => 'int:-200:400',
        'marginBottom' => 'int:-200:400',
        'textAlign' => 'enum:left,center,right,justify',
        'fontSize' => 'int:8:120',
        'fontWeight' => 'enum:300,400,500,600,700,800,900',
        'lineHeight' => 'num:0.8:3',
        'letterSpacing' => 'num:-5:20',
        'color' => 'color',
        'backgroundColor' => 'color',
        'borderRadius' => 'int:0:200',
        'borderWidth' => 'int:0:20',
        'borderColor' => 'color',
        'borderStyle' => 'enum:none,solid,dashed,dotted',
        'shadow' => 'enum:none,sm,md,lg',
        'maxWidth' => 'int:0:2000',
        'widthPercent' => 'int:5:100',
        'height' => 'int:0:1200',
        'minHeight' => 'int:0:1200',
        'columns' => 'int:1:6',
        'gap' => 'int:0:120',
        'aspectRatio' => 'enum:auto,1/1,4/3,3/4,16/9,9/16,3/2,2/3',
        'objectFit' => 'enum:cover,contain',
        'justifyContent' => 'enum:start,center,end,between',
        'alignItems' => 'enum:start,center,end,stretch',
        'buttonSize' => 'enum:sm,md,lg,xl',
        'fullWidth' => 'bool',
        // Product cards.
        'cardBackground' => 'color',
        'cardBorderColor' => 'color',
        'cardRadius' => 'int:0:60',
        'cardShadow' => 'enum:none,sm,md,lg',
        'cardPadding' => 'int:0:60',
        'titleColor' => 'color',
        'titleSize' => 'int:8:60',
        'titleWeight' => 'enum:400,500,600,700,800',
        'priceColor' => 'color',
        'priceSize' => 'int:8:80',
        'savingColor' => 'color',
        'selectedColor' => 'color',
    ],

    'breakpoints' => ['desktop', 'tablet', 'mobile'],

    // Page-wide settings stored next to the sections.
    'page_settings' => [
        'backgroundColor' => 'color',
        'textColor' => 'color',
        'accentColor' => 'color',
        'fontFamily' => 'enum:hind_siliguri,system,serif',
        'maxWidth' => 'int:320:1600',
        'language' => 'enum:bn,en',
    ],

    // Offer engine vocabulary (see the bindings section of PageValidator).
    'offer_types' => ['none', 'percent', 'fixed_amount', 'fixed_price', 'quantity_discount', 'bxgy', 'free_gift'],
];
