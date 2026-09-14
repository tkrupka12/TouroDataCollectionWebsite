<?php

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once APP_ROOT . '/src/heatmap/CrazyEggClient.php';
require_once APP_ROOT . '/src/heatmap/CrazyEggStore.php';

$command = $argv[1] ?? 'help';
$id = $argv[2] ?? null;
$config = require APP_ROOT . '/config/app.php';
$store = new CrazyEggStore($config['heatmap_storage']);

try {
    match ($command) {
        'list' => run_list($store),
        'get' => run_get($store, $id),
        'lookup' => run_lookup($store, $id),
        'fetch' => run_fetch($store),
        'watch' => run_watch($store),
        default => print_help(),
    };
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

function run_list(CrazyEggStore $store): void
{
    $snapshots = CrazyEggClient::fromEnv()->listSnapshots();
    $path = $store->saveSnapshots($snapshots);
    echo json_encode(['count' => count($snapshots), 'saved' => $path, 'snapshots' => $snapshots], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

function run_get(CrazyEggStore $store, ?string $id): void
{
    if ($id === null || $id === '') {
        throw new RuntimeException('Usage: php bin/crazyegg.php get <snapshot-id>');
    }
    $snapshot = CrazyEggClient::fromEnv()->getSnapshot($id);
    $path = $store->saveSnapshot($snapshot);
    echo json_encode(['saved' => $path, 'snapshot' => $snapshot], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

function run_lookup(CrazyEggStore $store, ?string $url): void
{
    if ($url === null || trim($url) === '') {
        throw new RuntimeException('Usage: php bin/crazyegg.php lookup <url>');
    }

    $cached = $store->readSnapshots();
    $snapshots = $cached['snapshots'] ?? CrazyEggClient::fromEnv()->listSnapshots();
    if ($cached === null) {
        $store->saveSnapshots($snapshots);
    }

    $matches = $store->snapshotsForUrl($snapshots, $url);
    echo json_encode([
        'lookup_url' => $url,
        'count' => count($matches),
        'snapshots' => $matches,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

function run_fetch(CrazyEggStore $store): void
{
    run_list($store);
}

function run_watch(CrazyEggStore $store): void
{
    $minutes = $store->refreshMinutes();
    fwrite(STDERR, "Fetching Crazy Egg snapshots every {$minutes} minute(s). Ctrl+C to stop.\n");
    while (true) {
        run_fetch($store);
        sleep($minutes * 60);
    }
}

function print_help(): void
{
    echo <<<TXT
Crazy Egg heatmap snapshots (Heatmap Management API key + secret)

  php bin/crazyegg.php list
  php bin/crazyegg.php get <snapshot-id>
  php bin/crazyegg.php lookup <url>
  php bin/crazyegg.php fetch
  php bin/crazyegg.php watch

Copies JSON into storage/heatmap-snapshots/. Use Heatmap Management credentials, not Conversion Tracking.

TXT;
}
