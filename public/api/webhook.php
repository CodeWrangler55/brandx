<?php
require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}
$secret = trim((string) config('BLAND_WEBHOOK_SECRET', ''));
$signature = (string) ($_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '');
$raw = file_get_contents('php://input');
if ($secret === '' || !is_string($raw) || $raw === '' || $signature === '') {
    json_response(['ok' => false, 'error' => 'Webhook signing is not configured.'], 401);
}
if (!verify_bland_signature($raw, $signature, $secret)) {
    json_response(['ok' => false, 'error' => 'Invalid webhook signature.'], 401);
}
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    json_response(['ok' => false, 'error' => 'Invalid JSON payload.'], 400);
}
$blandId = trim((string) ($payload['call_id'] ?? $payload['c_id'] ?? ''));
if ($blandId === '') {
    json_response(['ok' => false, 'error' => 'Webhook call ID is missing.'], 422);
}
$transcript = $payload['concatenated_transcript'] ?? $payload['transcript'] ?? $payload['pathway_logs'] ?? [];
$patch = [
    'status' => !empty($payload['completed']) ? 'completed' : (!empty($payload['error_message']) ? 'failed' : (string) ($payload['queue_status'] ?? 'completed')),
    'outcome' => (string) ($payload['disposition'] ?? $payload['outcome'] ?? $payload['queue_status'] ?? 'completed'),
    'duration_minutes' => isset($payload['call_length']) ? (float) $payload['call_length'] : null,
    'summary' => substr((string) ($payload['summary'] ?? ''), 0, 3000),
    'transcript' => $transcript,
    'follow_up_required' => !empty($payload['human_follow_up_required']),
    'completed_at' => (string) ($payload['created_at'] ?? gmdate(DATE_ATOM)),
];
$updated = CallService::updateByBlandId($blandId, $patch);
if ($updated === null) {
    // Duplicate and out-of-order delivery is safe: do not invent a call record.
    json_response(['ok' => true, 'matched' => false, 'message' => 'Call record not found; webhook safely acknowledged.']);
}
json_response(['ok' => true, 'matched' => true, 'call_id' => $blandId]);
