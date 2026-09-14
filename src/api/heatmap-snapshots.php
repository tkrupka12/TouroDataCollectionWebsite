<?php

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once APP_ROOT . '/src/heatmap/CrazyEggClient.php';
require_once APP_ROOT . '/src/heatmap/CrazyEggStore.php';

$config = require APP_ROOT . '/config/app.php';
$store = new CrazyEggStore($config['heatmap_storage']);
$minutes = $store->refreshMinutes();
$cached = $store->readSnapshots();
$hasKey = env_value('CRAZY_EGG_API_KEY') !== null && env_value('CRAZY_EGG_API_SECRET') !== null;

$id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$lookupUrl = isset($_GET['url']) ? trim((string) $_GET['url']) : '';

try {
    if ($id !== '') {
        $payload = null;
        $path = $store->snapshotPath($id);
        if (is_file($path)) {
            $payload = json_decode((string) file_get_contents($path), true);
        }
        if (!is_array($payload) && $hasKey) {
            $snapshot = CrazyEggClient::fromEnv()->getSnapshot($id);
            $store->saveSnapshot($snapshot);
            $payload = ['fetched_at' => gmdate('c'), 'snapshot' => $snapshot];
        }
        if (!is_array($payload)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Snapshot JSON not saved yet. Run php bin/crazyegg.php get ' . $id]);
            exit;
        }
        echo json_encode(['ok' => true, 'refresh_minutes' => $minutes, ...$payload], JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ((!$cached || !$store->isFresh($minutes)) && $hasKey) {
        $snapshots = CrazyEggClient::fromEnv()->listSnapshots();
        $store->saveSnapshots($snapshots);
        $cached = $store->readSnapshots();
    }

    $snapshots = $cached['snapshots'] ?? [];
    if ($lookupUrl !== '') {
        $snapshots = $store->snapshotsForUrl($snapshots, $lookupUrl);
    }

    echo json_encode([
        'ok' => true,
        'configured' => $hasKey,
        'refresh_minutes' => $minutes,
        'needs_credentials' => !$hasKey && $cached === null,
        'lookup_url' => $lookupUrl !== '' ? $lookupUrl : null,
        'fetched_at' => $cached['fetched_at'] ?? null,
        'count' => count($snapshots),
        'total_count' => $cached['count'] ?? 0,
        'snapshots' => $snapshots,
        'note' => 'Metadata only. Export click-level CSV/JSON from the Crazy Egg dashboard for visual heatmap data.',
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'configured' => $hasKey,
        'refresh_minutes' => $minutes,
        'error' => $e->getMessage(),
        'fetched_at' => $cached['fetched_at'] ?? null,
        'snapshots' => $cached['snapshots'] ?? [],
    ], JSON_UNESCAPED_SLASHES);
}
