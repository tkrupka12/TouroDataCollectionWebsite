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

if ($uri === '/api/semrush' || $uri === '/api/semrush.php') {
    require APP_ROOT . '/src/api/semrush.php';
    return true;
}

if ($uri === '/api/ahrefs' || $uri === '/api/ahrefs.php') {
    require APP_ROOT . '/src/api/ahrefs.php';
    return true;
}

if ($uri === '/api/siteimprove' || $uri === '/api/siteimprove.php') {
    require APP_ROOT . '/src/api/siteimprove.php';
    return true;
}

require __DIR__ . '/views/dashboard.php';
