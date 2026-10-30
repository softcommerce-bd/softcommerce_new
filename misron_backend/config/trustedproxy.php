<?php

/*
|--------------------------------------------------------------------------
| Trusted proxies
|--------------------------------------------------------------------------
|
| The storefront calls this API through its own /api/v2 rewrite, so without
| this setting every shopper appears to come from the storefront server's
| address: per-address rate limits are shared by all shoppers, a blocked
| address blocks everyone, and orders record the server's address.
|
| Read by App\Http\Middleware\TrustProxies (Laravel's own fallback). Set
| TRUSTED_PROXIES to the storefront server's outgoing addresses
| (comma separated, CIDR allowed). Use "*" only when this API is reachable
| solely through trusted proxies: otherwise anyone can claim any address with
| an X-Forwarded-For header. Empty keeps the old behaviour (trust no proxy).
|
*/

return [
    'proxies' => env('TRUSTED_PROXIES') ?: null,
];
