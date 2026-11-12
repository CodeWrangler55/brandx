<?php
declare(strict_types=1);

final class BlandClient
{
    public function sendCall(array $payload): array
    {
        return $this->request('POST', '/v1/calls', $payload);
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        $apiKey = trim((string) config('BLAND_API_KEY', ''));
        if ($apiKey === '') {
            throw new RuntimeException('Bland API key is not configured.');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL is required for live Bland calls.');
        }
        $curl = curl_init('https://api.bland.ai' . $path);
        $headers = ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'];
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($payload !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if (!is_string($body)) {
            throw new RuntimeException('Could not reach Bland: ' . ($error !== '' ? $error : 'network request failed'));
        }
        $decoded = json_decode($body, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($decoded) ? (string) ($decoded['message'] ?? $decoded['error'] ?? 'Bland rejected the call request.') : 'Bland rejected the call request.';
            throw new RuntimeException('Bland API error (' . $status . '): ' . substr($message, 0, 300));
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('Bland returned an unreadable response.');
        }
        return $decoded;
    }
}

final class RetailService
{
    public static function stores(): array
    {
        return JsonStorage::read('stores.json', []);
    }

    public static function products(): array
    {
        return JsonStorage::read('products.json', []);
    }

    public static function storeById(string $id): ?array
    {
        foreach (self::stores() as $store) {
            if (($store['store_id'] ?? null) === $id) {
                return $store;
            }
        }
        return null;
    }

    public static function productBySku(string $sku): ?array
    {
        foreach (self::products() as $product) {
            if (($product['sku'] ?? null) === $sku) {
                return $product;
            }
        }
        return null;
    }
}

final class ReorderService
{
    public static function submit(array $input): array
    {
        $storeId = trim((string) ($input['store_id'] ?? ''));
        $callId = trim((string) ($input['call_id'] ?? ''));
        if (RetailService::storeById($storeId) === null) {
            throw new InvalidArgumentException('Unknown demonstration store.');
        }
        if ($callId === '' || strlen($callId) > 120) {
            throw new InvalidArgumentException('A valid call ID is required.');
        }
        if (!in_array($input['confirmed_by_retailer'] ?? false, [true, 1, '1', 'true'], true)) {
            throw new InvalidArgumentException('Retailer confirmation is required before submission.');
        }
        $items = $input['items'] ?? null;
        if (!is_array($items) || count($items) < 1 || count($items) > 10) {
            throw new InvalidArgumentException('Add between one and ten requested products.');
        }
        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Invalid reorder item.');
            }
            $sku = strtoupper(trim((string) ($item['sku'] ?? '')));
            $quantity = filter_var($item['quantity_units'] ?? null, FILTER_VALIDATE_INT);
            $product = RetailService::productBySku($sku);
            if ($product === null) {
                throw new InvalidArgumentException('Unknown product SKU: ' . $sku . '.');
            }
            if (($product['available'] ?? false) !== true) {
                throw new InvalidArgumentException($product['name'] . ' is currently unavailable.');
            }
            if ($quantity === false || $quantity < 1 || $quantity > 100) {
                throw new InvalidArgumentException('Phone quantities must be whole numbers from 1 to 100.');
            }
            $normalized[] = ['sku' => $sku, 'brand' => $product['brand'], 'model' => $product['model'], 'name' => $product['name'], 'quantity_units' => $quantity, 'storage_gb' => $product['storage_gb']];
        }
        $created = null;
        $duplicate = false;
        JsonStorage::update('reorders.json', function (array $orders) use ($storeId, $callId, $normalized, &$created, &$duplicate): array {
            foreach ($orders as $existing) {
                if (($existing['call_id'] ?? '') === $callId) {
                    $created = $existing;
                    $duplicate = true;
                    return $orders;
                }
            }
            $created = [
                'request_id' => 'REQ-' . strtoupper(bin2hex(random_bytes(4))),
                'store_id' => $storeId,
                'items' => $normalized,
                'call_id' => $callId,
                'status' => 'Pending human review',
                'confirmed_by_retailer' => true,
                'created_at' => gmdate(DATE_ATOM),
            ];
            $orders[] = $created;
            return $orders;
        }, []);
        return ['ok' => true, 'duplicate' => $duplicate, 'reorder' => $created];
    }
}

function verify_bland_signature(string $rawBody, string $signature, string $secret): bool
{
    if ($rawBody === '' || $signature === '' || $secret === '') {
        return false;
    }
    return hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature);
}

final class CallService
{
    public static function add(array $call): array
    {
        $call['created_at'] = $call['created_at'] ?? gmdate(DATE_ATOM);
        JsonStorage::update('calls.json', function (array $calls) use ($call): array {
            $calls[] = $call;
            return array_slice($calls, -100);
        }, []);
        return $call;
    }

    public static function updateByBlandId(string $blandId, array $patch): ?array
    {
        $updated = null;
        JsonStorage::update('calls.json', function (array $calls) use ($blandId, $patch, &$updated): array {
            foreach ($calls as $index => $call) {
                if (($call['bland_call_id'] ?? '') === $blandId) {
                    $updated = array_merge($call, $patch);
                    $calls[$index] = $updated;
                    break;
                }
            }
            return $calls;
        }, []);
        return $updated;
    }
}

final class DemoService
{
    private const SCENARIOS = [
        'confirmed_reorder' => [
            ['speaker' => 'Alex · automated assistant', 'text' => 'Hi, this is Alex, Brand X Mobile’s automated assistant. This is a demo about a fictional store. How is your iPhone 17 inventory?'],
            ['speaker' => 'Retailer · simulated', 'text' => 'We are almost out. I would like three iPhone 17 units.'],
            ['speaker' => 'Alex · automated assistant', 'text' => 'I have three Apple iPhone 17 units for Northside Wireless. Should I submit that request for your account representative to review?'],
            ['speaker' => 'Retailer · simulated', 'text' => 'Yes, please.'],
        ],
        'no_reorder' => [
            ['speaker' => 'Alex · automated assistant', 'text' => 'How is your iPhone 17 inventory?'],
            ['speaker' => 'Retailer · simulated', 'text' => 'We have plenty and do not need a reorder.'],
            ['speaker' => 'Alex · automated assistant', 'text' => 'Understood. No reorder request was created. Thanks for your time.'],
        ],
        'product_unavailable' => [
            ['speaker' => 'Retailer · simulated', 'text' => 'Can I get three iPhone 17 Pro units?'],
            ['speaker' => 'Alex · automated assistant', 'text' => 'Apple iPhone 17 Pro is listed as unavailable. I will not submit a reorder for it. I can ask a representative to follow up.'],
        ],
        'human_follow_up' => [
            ['speaker' => 'Retailer · simulated', 'text' => 'Can you give me a discount or guarantee tomorrow delivery?'],
            ['speaker' => 'Alex · automated assistant', 'text' => 'I cannot confirm pricing or delivery. I will note that you need a representative to follow up.'],
        ],
        'retailer_busy' => [
            ['speaker' => 'Retailer · simulated', 'text' => 'I am busy right now.'],
            ['speaker' => 'Alex · automated assistant', 'text' => 'No problem. I will note that a representative should follow up. I will not promise a specific callback time.'],
        ],
    ];

    public static function run(string $storeId, string $scenario): array
    {
        $store = RetailService::storeById($storeId);
        if ($store === null) {
            throw new InvalidArgumentException('Select a valid demonstration store.');
        }
        if (!isset(self::SCENARIOS[$scenario])) {
            throw new InvalidArgumentException('Choose one of the listed demo scenarios.');
        }
        $localId = 'MOCK-' . strtoupper(bin2hex(random_bytes(4)));
        $call = CallService::add([
            'local_call_id' => $localId,
            'bland_call_id' => $localId,
            'store_id' => $store['store_id'],
            'store_name' => $store['store_name'],
            'phone_number' => 'SIMULATED',
            'mode' => 'mock',
            'status' => 'completed',
            'outcome' => $scenario,
            'transcript' => self::SCENARIOS[$scenario],
            'follow_up_required' => in_array($scenario, ['human_follow_up', 'retailer_busy', 'product_unavailable'], true),
        ]);
        $reorder = null;
        if ($scenario === 'confirmed_reorder') {
            $result = ReorderService::submit([
                'store_id' => $store['store_id'],
                'call_id' => $localId,
                'confirmed_by_retailer' => true,
                'items' => [['sku' => 'BX-PH-001', 'quantity_units' => 3]],
            ]);
            $reorder = $result['reorder'];
        }
        return ['call' => $call, 'reorder' => $reorder];
    }
}

