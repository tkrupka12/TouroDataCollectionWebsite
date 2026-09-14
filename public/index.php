<?php

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $uri;

if ($uri !== '/' && is_file($file)) {
    return false;
}

require_once dirname(__DIR__) . '/src/bootstrap.php';

if ($uri === '/api/status' || $uri === '/api/status.php') {
    require APP_ROOT . '/src/api/site-status.php';
    return true;
}

if ($uri === '/api/heatmap' || $uri === '/api/heatmap.php') {
    require APP_ROOT . '/src/api/heatmap-snapshots.php';
    return true;
}

require __DIR__ . '/views/dashboard.php';
