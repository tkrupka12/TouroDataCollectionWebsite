<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once APP_ROOT . '/src/siteimprove/SiteimproveStore.php';

$config = require APP_ROOT . '/config/app.php';
$store = new SiteimproveStore($config['siteimprove_storage']);
$cached = $store->read();
$hasKey = env_value('SITEIMPROVE_API_KEY') !== null;
$hasUser = env_value('SITEIMPROVE_USERNAME') !== null;
if ($hasKey && $hasUser) {
    CacheRefresh::startIfStale('siteimprove', $store->reportPath(), $store->refreshHours());
}

if ($cached === null) {
    http_response_code($hasKey && $hasUser ? 404 : 200);
    echo json_encode([
        'ok' => false,
        'configured' => $hasKey && $hasUser,
        'needs_credentials' => !$hasKey || !$hasUser,
        'error' => !$hasKey
            ? 'Add SITEIMPROVE_API_KEY to .env.'
            : (!$hasUser
                ? 'Add SITEIMPROVE_USERNAME to .env, then run php bin/siteimprove.php fetch.'
                : 'No cached Siteimprove data yet. Run php bin/siteimprove.php fetch.'),
        'reports' => [],
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'configured' => $hasKey && $hasUser,
    'needs_credentials' => false,
    'refresh_hours' => $store->refreshHours(),
    ...$cached,
], JSON_UNESCAPED_SLASHES);
