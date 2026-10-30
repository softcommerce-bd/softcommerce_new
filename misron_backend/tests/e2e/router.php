<?php

/*
|--------------------------------------------------------------------------
| Backend for the funnel editor's end-to-end tests
|--------------------------------------------------------------------------
|
| Router for PHP's built-in server. It pins the test database before Laravel
| boots, so the browser tests can never write to the development or
| production database, whatever the shell environment says. Start it from
| the project root:
|
|   php -S 127.0.0.1:8001 -t public tests/e2e/router.php
|
| `php artisan serve` is not used: it strips variables such as DB_DATABASE
| from the child process and would fall back to .env.
|
*/

$overrides = [
    'DB_DATABASE' => getenv('E2E_DB_DATABASE') ?: 'misron_e2e_test',
    'APP_URL' => getenv('E2E_APP_URL') ?: 'http://127.0.0.1:8001',
    // A cache folder of its own: settings read from the test database
    // must not leak into the development site, nor the other way round.
    'CACHE_DRIVER' => 'file',
    'CACHE_FILE_PATH' => dirname(__DIR__, 2) . '/storage/framework/cache/e2e',
    'FUNNEL_EDITOR_URL' => getenv('E2E_EDITOR_URL') ?: 'http://localhost:3100',
    'FUNNEL_PUBLIC_URL' => getenv('E2E_EDITOR_URL') ?: 'http://localhost:3100',
    // The storefront is told to refresh a page the moment it changes.
    'FUNNEL_REVALIDATE_URL' => (getenv('E2E_EDITOR_URL') ?: 'http://localhost:3100') . '/api/funnel/revalidate',
    'FUNNEL_REVALIDATE_SECRET' => 'e2e-revalidate-secret',
    // The suite opens the editor dozens of times a minute from one address.
    'FUNNEL_EDITOR_EXCHANGES_PER_MINUTE' => '600',
    // The suite publishes pages without an order form; a developer's .env must not change that.
    'FUNNEL_REQUIRE_CHECKOUT' => 'false',
];

if (substr($overrides['DB_DATABASE'], -5) !== '_test') {
    http_response_code(500);
    exit('The end-to-end test server only runs against a *_test database.');
}

foreach ($overrides as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

$publicPath = dirname(__DIR__, 2) . '/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

if ($uri !== '/' && file_exists($publicPath . $uri)) {
    return false;
}

require_once $publicPath . '/index.php';
