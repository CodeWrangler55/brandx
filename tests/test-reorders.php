<?php
declare(strict_types=1);

$testDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'brandx-tests-' . bin2hex(random_bytes(6));
mkdir($testDir, 0777, true);
putenv('APP_STORAGE_DIR=' . $testDir);
require_once dirname(__DIR__) . '/src/bootstrap.php';

foreach (['stores.json', 'products.json'] as $file) {
    copy(APP_ROOT . '/storage/' . $file, $testDir . DIRECTORY_SEPARATOR . $file);
}
file_put_contents($testDir . DIRECTORY_SEPARATOR . 'reorders.json', '[]');
file_put_contents($testDir . DIRECTORY_SEPARATOR . 'calls.json', '[]');

$passed = 0;
$failed = 0;
$failures = [];
$check = static function (string $name, callable $test) use (&$passed, &$failed, &$failures): void {
    try {
        $test();
        $passed++;
        echo "PASS  {$name}\n";
    } catch (Throwable $error) {
        $failed++;
        $failures[] = $name . ': ' . $error->getMessage();
        echo "FAIL  {$name}\n";
    }
};
$expectThrows = static function (callable $callback, string $messagePart): void {
    try { $callback(); } catch (Throwable $error) {
        if (str_contains($error->getMessage(), $messagePart)) return;
        throw new RuntimeException('Unexpected error: ' . $error->getMessage());
    }
    throw new RuntimeException('Expected rejection was not raised.');
};

$check('Confirmed reorder is saved for human review', static function () use ($expectThrows): void {
    $result = ReorderService::submit(['store_id' => 'STORE-001', 'call_id' => 'CALL-OK', 'confirmed_by_retailer' => true, 'items' => [['sku' => 'BX-PH-001', 'quantity_units' => 3]]]);
    if ($result['reorder']['status'] !== 'Pending human review' || $result['reorder']['request_id'] === '') throw new RuntimeException('Request status or ID incorrect.');
});
$check('Retailer decline creates no reorder', static function (): void {
    $before = count(JsonStorage::read('reorders.json', []));
    $result = DemoService::run('STORE-001', 'no_reorder');
    $after = count(JsonStorage::read('reorders.json', []));
    if ($result['reorder'] !== null || $after !== $before) throw new RuntimeException('Decline changed the reorder queue.');
});
$check('Invalid SKU is rejected', static function () use ($expectThrows): void {
    $expectThrows(fn () => ReorderService::submit(['store_id' => 'STORE-001', 'call_id' => 'CALL-BAD-SKU', 'confirmed_by_retailer' => true, 'items' => [['sku' => 'BX-PH-999', 'quantity_units' => 1]]]), 'Unknown product SKU');
});
$check('Unavailable product is rejected', static function () use ($expectThrows): void {
    $expectThrows(fn () => ReorderService::submit(['store_id' => 'STORE-001', 'call_id' => 'CALL-UNAVAILABLE', 'confirmed_by_retailer' => true, 'items' => [['sku' => 'BX-PH-003', 'quantity_units' => 1]]]), 'unavailable');
});
$check('Duplicate call submission is idempotent', static function (): void {
    $payload = ['store_id' => 'STORE-001', 'call_id' => 'CALL-DUPLICATE', 'confirmed_by_retailer' => true, 'items' => [['sku' => 'BX-PH-001', 'quantity_units' => 1]]];
    $first = ReorderService::submit($payload);
    $second = ReorderService::submit($payload);
    if ($first['duplicate'] || !$second['duplicate'] || count(array_filter(JsonStorage::read('reorders.json', []), fn ($item) => $item['call_id'] === 'CALL-DUPLICATE')) !== 1) throw new RuntimeException('Duplicate was not suppressed.');
});
$check('Missing explicit confirmation is rejected', static function () use ($expectThrows): void {
    $expectThrows(fn () => ReorderService::submit(['store_id' => 'STORE-001', 'call_id' => 'CALL-NO-CONFIRM', 'confirmed_by_retailer' => false, 'items' => [['sku' => 'BX-PH-001', 'quantity_units' => 1]]]), 'confirmation is required');
});
$check('Duplicate webhook delivery updates one call only', static function (): void {
    CallService::add(['local_call_id' => 'LOCAL-1', 'bland_call_id' => 'BLAND-1', 'store_id' => 'STORE-001', 'status' => 'queued']);
    $patch = ['status' => 'completed', 'summary' => 'Demo done'];
    CallService::updateByBlandId('BLAND-1', $patch);
    CallService::updateByBlandId('BLAND-1', $patch);
    $calls = JsonStorage::read('calls.json', []);
    if (count(array_filter($calls, fn ($call) => $call['bland_call_id'] === 'BLAND-1')) !== 1) throw new RuntimeException('Webhook produced duplicate call records.');
});
$check('Invalid webhook signature is rejected', static function (): void {
    if (verify_bland_signature('{"call_id":"x"}', 'wrong-signature', 'test-secret')) throw new RuntimeException('Invalid signature was accepted.');
});
$check('Missing API key fails before network access', static function () use ($expectThrows): void {
    putenv('BLAND_API_KEY=');
    $expectThrows(fn () => (new BlandClient())->sendCall([]), 'not configured');
});
$check('Concurrent duplicate calls persist only one reorder', static function () use ($testDir): void {
    if (!function_exists('proc_open')) throw new RuntimeException('PHP process creation is unavailable.');
    $before = count(JsonStorage::read('reorders.json', []));
    $processes = [];
    for ($i = 0; $i < 4; $i++) {
        $command = [PHP_BINARY, APP_ROOT . '/tests/concurrent-reorder-worker.php', $testDir];
        $pipes = [];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, APP_ROOT);
        if (!is_resource($process)) throw new RuntimeException('Could not start concurrent worker.');
        fclose($pipes[0]);
        $processes[] = [$process, $pipes];
    }
    foreach ($processes as [$process, $pipes]) {
        $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
        $status = proc_close($process);
        if ($status !== 0) throw new RuntimeException('Worker failed: ' . $stdout . $stderr);
    }
    $after = count(JsonStorage::read('reorders.json', []));
    $matches = array_filter(JsonStorage::read('reorders.json', []), fn ($item) => $item['call_id'] === 'CONCURRENT-CALL-001');
    if ($after !== $before + 1 || count($matches) !== 1) throw new RuntimeException('Concurrent submissions created more than one request.');
});

foreach (glob($testDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) { if (is_file($file)) unlink($file); }
rmdir($testDir);
echo "\n{$passed} passed; {$failed} failed.\n";
if ($failed > 0) {
    foreach ($failures as $failure) echo " - {$failure}\n";
    exit(1);
}
