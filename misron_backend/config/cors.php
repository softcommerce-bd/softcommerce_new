<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.web.orghttps://developer.web.org/docs/Web/HTTP/CORS
    |
    */

    'paths' => [
        'api/*',
        'api/v2/*',           // Explicitly add v2 routes
        'v2/*',               // If you have routes without api prefix
        'sanctum/csrf-cookie',
        'login',
        'logout',
        'register',
        'user',
        'broadcasting/auth',
        'header-details',     // Add specific endpoints your frontend calls
        'categories',
        'seo-settings',
        'seo/*',
    ],

    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
    ],

    // Storefront addresses allowed to call the API from the browser. Add more without editing this file:
    // CORS_ALLOWED_ORIGINS=https://shop.example.com,https://www.example.com (comma separated, no trailing slash).
    'allowed_origins' => array_values(array_unique(array_merge([
        'http://localhost:3000',
        'http://localhost:3001',
        'http://127.0.0.1:3000',
        'https://misron-frontend.vercel.app',
        'https://test.misronshop.com',
        'https://misronshop.com',
        'https://www.misronshop.com',
    ], array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Accept-Language',
        'Content-Language',
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
        'Origin',
        'Referer',
        'User-Agent',
        'Cache-Control',
        'Pragma',
        'Access-Control-Request-Headers',
        'Access-Control-Request-Method',
    ],

    'exposed_headers' => [
        'Link',
        'X-Debug-Token',
        'X-Debug-Token-Link',
    ],

    'max_age' => 86400,  // Cache preflight requests for 24 hours (better performance)

    'supports_credentials' => true, // Important for sessions/auth

];
