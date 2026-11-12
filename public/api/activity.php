<?php
require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require_admin();
$calls = array_reverse(JsonStorage::read('calls.json', []));
$reorders = array_reverse(JsonStorage::read('reorders.json', []));
json_response(['ok' => true, 'calls' => array_slice($calls, 0, 20), 'reorders' => array_slice($reorders, 0, 20)]);
