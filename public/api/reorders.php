<?php
require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}
$token = (string) ($_SERVER['HTTP_X_APP_TOOL_TOKEN'] ?? '');
$expected = trim((string) config('APP_TOOL_TOKEN', ''));
if ($expected === '' || !hash_equals($expected, $token)) {
    json_response(['ok' => false, 'error' => 'Unauthorized tool request.'], 401);
}
$input = read_json_body();
// Bland's tool collects one phone model per confirmed request. Keep the app API's
// multi-item format for the dashboard and adapt the single-item tool payload here.
if (!isset($input['items']) && isset($input['sku'], $input['quantity_units'])) {
    $input['items'] = [[
        'sku' => $input['sku'],
        'quantity_units' => $input['quantity_units'],
    ]];
}
try {
    $result = ReorderService::submit($input);
    json_response($result, 200);
} catch (InvalidArgumentException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('Brand X reorder error: ' . $e->getMessage());
    json_response(['ok' => false, 'error' => 'The request could not be saved. A representative should review this call.'], 500);
}
