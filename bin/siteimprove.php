<?php

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once APP_ROOT . '/src/siteimprove/SiteimproveClient.php';
require_once APP_ROOT . '/src/siteimprove/SiteimproveStore.php';

$command = $argv[1] ?? 'help';
$config = require APP_ROOT . '/config/app.php';
$store = new SiteimproveStore($config['siteimprove_storage']);

try {
    match ($command) {
        'fetch' => run_fetch($store),
        'show' => run_show($store),
        'watch' => run_watch($store),
        default => print_help(),
    };
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

function run_fetch(SiteimproveStore $store): void
{
    $client = SiteimproveClient::fromEnv();
    $sites = $client->listAllSites();
    $reports = [];
    $errors = [];

    foreach ($sites as $index => $site) {
        $id = (int) ($site['id'] ?? 0);
        $name = (string) ($site['site_name'] ?? $site['url'] ?? $id);
        fwrite(STDERR, sprintf("[%d/%d] %s\n", $index + 1, count($sites), $name));
        if ($id === 0) {
            continue;
        }
        try {
            $raw = $client->siteOverview($id);
            $reports[] = $store->normalizeReport($raw);
        } catch (Throwable $e) {
            $errors[] = [
                'id' => $id,
                'name' => $name,
                'message' => $e->getMessage(),
            ];
        }
    }

    $path = $store->save([
        'fetched_at' => gmdate('c'),
        'count' => count($reports),
        'error_count' => count($errors),
        'reports' => $reports,
        'errors' => $errors,
    ]);

    echo json_encode([
        'ok' => $reports !== [],
        'saved' => $path,
        'count' => count($reports),
        'error_count' => count($errors),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

function run_show(SiteimproveStore $store): void
{
    $data = $store->read();
    if ($data === null) {
        throw new RuntimeException('No Siteimprove JSON saved yet. Run php bin/siteimprove.php fetch.');
    }
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

function run_watch(SiteimproveStore $store): void
{
    $hours = $store->refreshHours();
    fwrite(STDERR, "Fetching Siteimprove data every {$hours} hour(s). Ctrl+C to stop.\n");
    while (true) {
        run_fetch($store);
        sleep($hours * 3600);
    }
}

function print_help(): void
{
    echo <<<TXT
Cached Siteimprove site summaries

  php bin/siteimprove.php fetch
  php bin/siteimprove.php show
  php bin/siteimprove.php watch

Writes storage/siteimprove/sites-overview.json.

TXT;
}
