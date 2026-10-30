<?php

/*
|--------------------------------------------------------------------------
| Sales Funnel module
|--------------------------------------------------------------------------
|
| Everything the funnel module reads from the environment lives here, so
| application code never calls env() directly. The block registry (which
| component types exist and what props they accept) is in funnel_blocks.php.
|
*/

return [

    // Version written into every new page document. Raise it together with a
    // step in App\Services\Funnel\SchemaMigrator whenever block props change.
    'schema_version' => 1,

    // Public storefront (Next.js) origin that serves /funnel/{slug}.
    'public_url' => rtrim(env('FUNNEL_PUBLIC_URL', env('FRONTEND_URL', '')), '/'),

    // Origin of the visual editor (a route of the Next.js app). Empty means the
    // editor is not deployed yet and the admin list hides the Edit button.
    'editor_url' => rtrim(env('FUNNEL_EDITOR_URL', ''), '/'),

    'statuses' => ['draft', 'published', 'scheduled', 'unpublished', 'archived'],

    'slug' => [
        'min' => 3,
        'max' => 80,
        'reserved' => ['preview', 'new', 'api', 'admin', 'builder', 'checkout', 'confirm', 'thank-you'],
    ],

    // Hard ceilings for a page document. They protect the database and the
    // renderer from pathological payloads, not from ordinary pages.
    'limits' => [
        'max_document_bytes' => 1024 * 1024,
        'max_nodes' => 600,
        'max_depth' => 8,
        'max_string' => 5000,
        'max_rich_text' => 20000,
        'max_product_bindings' => 40,
    ],

    // Editor session handed over from the Laravel admin to the Next.js editor.
    'editor_session' => [
        'handoff_ttl_seconds' => 60,
        'token_ttl_minutes' => 480,
        'token_ability' => 'funnel:edit',
        // Editor openings per minute per address (one-time code exchanges).
        'exchanges_per_minute' => (int) env('FUNNEL_EDITOR_EXCHANGES_PER_MINUTE', 10),
    ],

    // Customers can only order from a page that has a checkout form (Phase 4).
    // While false, a page with product blocks but no checkout form publishes
    // with a warning instead of an error, so offers can be built and published
    // ahead of checkout.
    'require_checkout' => (bool) env('FUNNEL_REQUIRE_CHECKOUT', false),

    // Pricing and quote limits.
    'pricing' => [
        'max_quantity' => 99,
        'max_lines' => 40,
    ],

    // Taking real orders from a funnel page. Off until the stock-table changes
    // (sqlupdates/inventory_v1_*.sql) and the order columns (sales_funnel_v2_orders.sql,
    // sales_funnel_v3_checkout.sql) are installed and tested together. While it is
    // off every checkout request is refused and the page says ordering is not open.
    'checkout' => [
        'enabled' => (bool) env('FUNNEL_CHECKOUT_ENABLED', false),
    ],

    'versions' => [
        // Published snapshots are kept forever. Restore points taken before a
        // rollback are pruned to this many per funnel.
        'max_restore_points' => 30,
    ],

    // Images uploaded from the editor (raster formats only).
    'media' => [
        'max_kb' => (int) env('FUNNEL_MEDIA_MAX_KB', 5120),
    ],

    // Secret draft links. A link lives for the chosen time (at most max_days); a funnel keeps
    // at most max_active_links unexpired, unrevoked links at once.
    'preview' => [
        'default_hours' => 72,
        'max_days' => 30,
        'max_active_links' => 20,
    ],

    // Cache refresh of the public page in the Next.js app. url is the storefront's
    // revalidation endpoint (e.g. https://shop.example/api/funnel/revalidate);
    // secret is shared with the storefront (FUNNEL_REVALIDATE_SECRET there too).
    // Leave empty to rely on the short cache lifetime alone.
    'revalidate' => [
        'url' => env('FUNNEL_REVALIDATE_URL', ''),
        'secret' => env('FUNNEL_REVALIDATE_SECRET', ''),
        'timeout_seconds' => 3,
        'seconds' => 60,
    ],

    // Funnel analytics and conversion tracking (Phase 6).
    'analytics' => [
        // Orders from these phone numbers (comma separated, 01XXXXXXXXX) count as test
        // orders: left out of the analytics and never reported to ad platforms.
        'test_phones' => array_values(array_filter(array_map('trim', explode(',', (string) env('FUNNEL_TEST_PHONES', ''))))),
        // Lifetime of the editor's "Place a test order" link.
        'test_link_hours' => 24,
        // Whether the hashed phone number and email of a buyer may be sent to Meta with the
        // Purchase event (better ad matching). Off unless the store's privacy notice covers it.
        'share_customer_data' => (bool) env('FUNNEL_TRACKING_SHARE_CUSTOMER_DATA', false),
        // Events older than this are removed by funnel:prune-events. Orders are never removed.
        'event_retention_days' => (int) env('FUNNEL_EVENT_RETENTION_DAYS', 400),
    ],

    // Unpaid online-payment orders (website and funnel orders alike). Their stock is held from
    // the moment the order is stored; orders:expire-unpaid gives it back once the gateway
    // confirms that no payment went through. Off until switched on (see the deployment guide).
    'unpaid_online_orders' => [
        'expire' => (bool) env('ONLINE_ORDER_EXPIRY_ENABLED', false),
        // An order is looked at once it is this old and still unpaid.
        'expire_after_minutes' => (int) env('ONLINE_ORDER_EXPIRE_MINUTES', 60),
        // Payment methods whose orders may be expired. Only gateways the store can ask
        // about a payment afterwards belong here.
        'gateways' => ['paystation'],
        // When the gateway cannot answer for this long, the order is left for staff
        // instead of being retried forever.
        'give_up_after_hours' => 48,
    ],
];
