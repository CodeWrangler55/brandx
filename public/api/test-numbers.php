<?php
require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require_admin();
start_app_session();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response(['ok' => true, 'numbers' => JsonStorage::read('test-numbers.json', [])]);
}
require_csrf();
$input = read_json_body();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
    $number = preg_replace('/[\s().-]/', '', (string) ($input['phone_number'] ?? ''));
    $label = trim((string) ($input['label'] ?? 'Demo caller'));
    if (!preg_match('/^\+[1-9][0-9]{7,14}$/', (string) $number)) {
        json_response(['ok' => false, 'error' => 'Enter a phone number in international format, such as +14155550123.'], 422);
    }
    if (($input['consent_confirmed'] ?? false) !== true) {
        json_response(['ok' => false, 'error' => 'Confirm that this number belongs to you or its owner agreed to this demo call.'], 422);
    }
    if ($label === '' || strlen($label) > 60) {
        json_response(['ok' => false, 'error' => 'Add a short label for this approved demo number.'], 422);
    }
    $entry = ['id' => bin2hex(random_bytes(8)), 'phone_number' => $number, 'label' => $label, 'consent_confirmed' => true, 'created_at' => gmdate(DATE_ATOM)];
    try {
        $numbers = JsonStorage::update('test-numbers.json', function (array $numbers) use ($entry): array {
            foreach ($numbers as $existing) {
                if (($existing['phone_number'] ?? '') === $entry['phone_number']) {
                    throw new DomainException('This number is already on the approved demo list.');
                }
            }
            $numbers[] = $entry;
            return $numbers;
        }, []);
    } catch (DomainException $e) {
        json_response(['ok' => false, 'error' => $e->getMessage()], 409);
    }
    json_response(['ok' => true, 'numbers' => $numbers]);
}
if ($method === 'DELETE') {
    $id = trim((string) ($input['id'] ?? ''));
    $numbers = JsonStorage::update('test-numbers.json', fn (array $numbers): array => array_values(array_filter($numbers, fn ($number) => ($number['id'] ?? '') !== $id)), []);
    json_response(['ok' => true, 'numbers' => $numbers]);
}
header('Allow: GET, POST, DELETE');
json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
