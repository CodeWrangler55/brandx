<?php
require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require_admin();
require_csrf();
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}
$input = read_json_body();
try {
    $result = DemoService::run((string) ($input['store_id'] ?? ''), (string) ($input['scenario'] ?? 'confirmed_reorder'));
    json_response(['ok' => true, 'call' => $result['call'], 'reorder' => $result['reorder']]);
} catch (InvalidArgumentException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 422);
}
