<?php
declare(strict_types=1);
putenv('APP_STORAGE_DIR=' . ($argv[1] ?? ''));
require_once dirname(__DIR__) . '/src/bootstrap.php';
ReorderService::submit([
    'store_id' => 'STORE-001',
    'call_id' => 'CONCURRENT-CALL-001',
    'confirmed_by_retailer' => true,
    'items' => [['sku' => 'BX-PH-001', 'quantity_units' => 2]],
]);
