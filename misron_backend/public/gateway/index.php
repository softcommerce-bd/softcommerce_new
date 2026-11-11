<?php

// The Softtech messaging gateway running INSIDE this backend (served at https://<your domain>/gateway/...).
// Same code as the standalone service (../../gateway), mounted under the /gateway prefix. Its data lives in ../../gateway/storage (not web-accessible)
// or in the database named by GATEWAY_DSN.
require __DIR__ . '/../../gateway/src/bootstrap.php';

$raw = file_get_contents('php://input') ?: '';
$headers = function_exists('getallheaders') ? getallheaders() : [];
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/' . ltrim(preg_replace('#^/gateway(?=/|$)#', '', $path), '/'); // strip the mount prefix
[$status, $out, $body] = $app->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $path, $_GET, $headers, $raw);
http_response_code($status);
foreach ($out as $k => $v) {
    header("$k: $v");
}
echo $body;
