<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once APP_ROOT . '/src/semrush/SemrushStore.php';

$config = require APP_ROOT . '/config/app.php';
$store = new SemrushStore($config['semrush_storage']);
$cached = $store->read();
if (env_value('SEMRUSH_API_KEY') !== null) {
    CacheRefresh::startIfStale('semrush', $store->reportPath(), $store->refreshHours());
}

if ($cached === null) {
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'configured' => env_value('SEMRUSH_API_KEY') !== null,
        'error' => 'No cached Semrush data yet. Run php bin/semrush.php fetch.',
        'reports' => [],
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'refresh_hours' => $store->refreshHours(),
    ...$cached,
], JSON_UNESCAPED_SLASHES);
