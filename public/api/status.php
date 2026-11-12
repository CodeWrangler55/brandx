<?php
require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require_admin();

$numbers = JsonStorage::read('test-numbers.json', []);
$baseUrl = trim((string) config('APP_BASE_URL', ''));
$ready = trim((string) config('BLAND_API_KEY', '')) !== ''
    && trim((string) config('BLAND_AGENT_ID', '')) !== ''
    && is_https_url($baseUrl)
    && count($numbers) > 0
    && trim((string) config('APP_TOOL_TOKEN', '')) !== ''
    && trim((string) config('BLAND_WEBHOOK_SECRET', '')) !== '';
json_response([
    'ok' => true,
    'mode' => strtolower((string) config('APP_MODE', 'mock')) === 'live' ? 'live' : 'mock',
    'live_ready' => $ready,
    'checks' => [
        'api_key' => trim((string) config('BLAND_API_KEY', '')) !== '',
        'agent_id' => trim((string) config('BLAND_AGENT_ID', '')) !== '',
        'https_webhook' => is_https_url($baseUrl),
        'tool_token' => trim((string) config('APP_TOOL_TOKEN', '')) !== '',
        'approved_numbers' => count($numbers) > 0,
        'webhook_signature' => trim((string) config('BLAND_WEBHOOK_SECRET', '')) !== '',
    ],
    'csrf_token' => csrf_token(),
]);
