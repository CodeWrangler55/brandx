<?php
require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}
$token = (string) ($_SERVER['HTTP_X_APP_TOOL_TOKEN'] ?? '');
$expected = trim((string) config('APP_TOOL_TOKEN', ''));
if ($expected === '' || !hash_equals($expected, $token)) {
    json_response(['ok' => false, 'error' => 'Unauthorized tool request.'], 401);
}
$products = array_map(static fn (array $product): array => [
    'sku' => $product['sku'],
    'brand' => $product['brand'],
    'model' => $product['model'],
    'name' => $product['name'],
    'storage_gb' => $product['storage_gb'],
    'available' => $product['available'],
], RetailService::products());
json_response(['ok' => true, 'products' => $products]);
