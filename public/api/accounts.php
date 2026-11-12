<?php
require_once dirname(__DIR__, 2) . '/src/bootstrap.php';
require_admin();
json_response(['ok' => true, 'stores' => RetailService::stores()]);
