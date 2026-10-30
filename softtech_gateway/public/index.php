<?php

require __DIR__ . '/../src/bootstrap.php';

$raw = file_get_contents('php://input') ?: '';
$headers = function_exists('getallheaders') ? getallheaders() : [];
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
// PHP rewrites "hub.mode" to "hub_mode" in $_GET, which is what the handler expects.
[$status, $out, $body] = $app->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $path, $_GET, $headers, $raw);
http_response_code($status);
foreach ($out as $k => $v) {
    header("$k: $v");
}
echo $body;
