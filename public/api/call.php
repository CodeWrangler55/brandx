<?php
require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require_admin();
require_csrf();
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}
if (strtolower((string) config('APP_MODE', 'mock')) !== 'live') {
    json_response(['ok' => false, 'error' => 'Live calling is off. Switch APP_MODE=live only after finishing the setup checklist.'], 409);
}
$input = read_json_body();
$store = RetailService::storeById((string) ($input['store_id'] ?? ''));
if ($store === null) {
    json_response(['ok' => false, 'error' => 'Select a valid demonstration store.'], 422);
}
$numbers = JsonStorage::read('test-numbers.json', []);
$approved = null;
foreach ($numbers as $number) {
    if (($number['id'] ?? '') === (string) ($input['test_number_id'] ?? '') && ($number['consent_confirmed'] ?? false) === true) {
        $approved = $number;
        break;
    }
}
if ($approved === null) {
    json_response(['ok' => false, 'error' => 'Choose a number that is on the approved demo list.'], 422);
}
$agentId = trim((string) config('BLAND_AGENT_ID', ''));
$baseUrl = rtrim(trim((string) config('APP_BASE_URL', '')), '/');
if ($agentId === '' || trim((string) config('BLAND_API_KEY', '')) === '' || trim((string) config('APP_TOOL_TOKEN', '')) === '' || trim((string) config('BLAND_WEBHOOK_SECRET', '')) === '' || !is_https_url($baseUrl)) {
    json_response(['ok' => false, 'error' => 'Live setup is incomplete. Set the API key, published agent ID, tool token, webhook signing secret, and public HTTPS app URL in .env.'], 409);
}
if (!function_exists('curl_init')) {
    json_response(['ok' => false, 'error' => 'PHP cURL is required for Bland calls.'], 500);
}
$cooldown = (int) config('LIVE_CALL_COOLDOWN_SECONDS', '15');
$now = time();
try {
    JsonStorage::update('rate-limits.json', function (array $limits) use ($now, $cooldown): array {
        $last = (int) ($limits['last_live_call'] ?? 0);
        if ($last > 0 && $now - $last < $cooldown) {
            throw new DomainException('Please wait before starting another live demo call.');
        }
        $limits['last_live_call'] = $now;
        return $limits;
    }, []);
} catch (DomainException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 429);
}
$localId = 'CALL-' . strtoupper(bin2hex(random_bytes(6)));
$payload = [
    'phone_number' => $approved['phone_number'],
    'agent_id' => $agentId,
    'agent_version' => 'production',
    'max_duration' => 5,
    'record' => false,
    'request_data' => [
        'store_id' => $store['store_id'],
        'store_name' => $store['store_name'],
        'contact_name' => $store['contact_name'],
        'location' => $store['location'],
        'previous_order_date' => $store['previous_order_date'],
        'products_previously_purchased' => $store['products_previously_purchased'],
        'demo_disclosure' => 'This is a demonstration call about fictional mobile phone retail inventory.',
    ],
    'metadata' => ['local_call_id' => $localId, 'store_id' => $store['store_id'], 'demo' => true],
    'webhook' => $baseUrl . '/api/webhook.php',
];
try {
    $response = (new BlandClient())->sendCall($payload);
    $blandId = (string) ($response['call_id'] ?? $response['id'] ?? '');
    if ($blandId === '') {
        throw new RuntimeException('Bland did not return a call ID. The call status is unknown; check the Bland dashboard before retrying.');
    }
    $call = CallService::add([
        'local_call_id' => $localId,
        'bland_call_id' => $blandId,
        'store_id' => $store['store_id'],
        'store_name' => $store['store_name'],
        'phone_number' => $approved['phone_number'],
        'phone_label' => $approved['label'],
        'mode' => 'live',
        'status' => 'queued',
        'outcome' => 'in_progress',
        'transcript' => [],
        'follow_up_required' => false,
    ]);
    json_response(['ok' => true, 'call' => $call, 'message' => 'Bland accepted the call request. Call status and any reorder result will update after its webhook arrives.']);
} catch (Throwable $e) {
    error_log('Brand X Bland call error; details withheld from server log.');
    json_response(['ok' => false, 'error' => $e->getMessage()], 502);
}
