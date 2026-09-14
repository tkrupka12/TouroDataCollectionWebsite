<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once APP_ROOT . '/src/site-tracker/Tracker.php';

$config = require APP_ROOT . '/config/app.php';
$tracker = new Tracker($config);

try {
    echo json_encode($tracker->probe(), JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Tracker failed',
        'checked_at' => gmdate('c'),
        'measurement_id' => $config['measurement_id'],
        'target_name' => $config['target_name'],
        'target_url' => $config['target_url'],
        'history' => [],
        'averages' => ['uptime' => 0, 'avg_ms' => 0, 'checks' => 0],
    ], JSON_UNESCAPED_SLASHES);
}
