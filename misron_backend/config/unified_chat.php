<?php

return [
    // direct  = provider webhooks hit this installation (needs the provider app configured for this domain)
    // gateway = a central Softtech Messaging Integration Gateway receives provider events and forwards them signed
    'mode' => env('CHAT_MODE', 'gateway'),

    'gateway' => [
        'url' => env('CHAT_GATEWAY_URL'),                 // e.g. https://integration.example.com
        'installation_id' => env('CHAT_INSTALLATION_ID'),
        'secret' => env('CHAT_INSTALLATION_SECRET'),      // shared HMAC secret, rotate through the gateway
        'tolerance' => 300,                               // seconds of clock skew accepted on signed requests
    ],

    'graph_version' => env('META_GRAPH_VERSION', 'v25.0'),

    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
    ],
    'whatsapp' => [
        // Template messages are for answering existing customers. Marketing-category templates stay hidden (they are
        // billed differently and this product has no bulk/marketing sending) unless you opt in.
        'allow_marketing_templates' => (bool) env('CHAT_WA_ALLOW_MARKETING_TEMPLATES', false),
    ],
    'tiktok' => [
        'client_key' => env('TIKTOK_CLIENT_KEY'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'enabled' => env('TIKTOK_MESSAGING_ENABLED', false), // only true after TikTok approves Business Messaging access
    ],

    // Provider messaging windows (hours after the customer's last message in which free-form replies are allowed).
    'windows' => ['facebook' => 24, 'instagram' => 24, 'whatsapp' => 24, 'tiktok' => 48],

    'attachment_disk' => env('CHAT_ATTACHMENT_DISK', 'local'),
    'poll_seconds' => 5,                                  // fallback when WebSockets are unavailable
    'retention_days' => (int) env('CHAT_RETENTION_DAYS', 0), // 0 = keep forever
    'max_send_attempts' => 5,
    'max_discount_percent' => (float) env('CHAT_MAX_DISCOUNT_PERCENT', 10), // for non-Super-Admin staff creating chat orders
];
