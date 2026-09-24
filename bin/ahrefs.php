<?php

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once APP_ROOT . '/src/ahrefs/AhrefsClient.php';
require_once APP_ROOT . '/src/ahrefs/AhrefsStore.php';

$command = $argv[1] ?? 'help';
$config = require APP_ROOT . '/config/app.php';
$store = new AhrefsStore($config['ahrefs_storage']);

try {
    match ($command) {
        'fetch' => run_fetch($store, $config),
        'show' => run_show($store),
        'watch' => run_watch($store, $config),
        default => print_help(),
    };
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

function run_fetch(AhrefsStore $store, array $config): void
{
    $snapshotPath = $config['heatmap_storage'] . '/snapshots.json';
    if (!is_file($snapshotPath)) {
        throw new RuntimeException('Crazy Egg snapshots are missing. Run php bin/crazyegg.php fetch first.');
    }

    $snapshotData = json_decode((string) file_get_contents($snapshotPath), true);
    if (!is_array($snapshotData)) {
        throw new RuntimeException('Crazy Egg snapshots JSON is invalid.');
    }

    $domains = $store->domainsFromSnapshots($snapshotData['snapshots'] ?? []);
    if ($domains === []) {
        throw new RuntimeException('No domains were found in the Crazy Egg snapshots.');
    }

    $client = AhrefsClient::fromEnv();
    $date = gmdate('Y-m-d', strtotime('-1 day'));
    $reports = [];
    $errors = [];

    foreach ($domains as $index => $domain) {
        $host = $domain['domain'];
        fwrite(STDERR, sprintf("[%d/%d] %s\n", $index + 1, count($domains), $host));
        try {
            $raw = $client->domainOverview($host, $date);
            $reports[] = $store->normalizeReport($domain, $raw, $date);
        } catch (Throwable $e) {
            $errors[] = [
                'domain' => $host,
                'message' => $e->getMessage(),
            ];
        }
    }

    $path = $store->save([
        'fetched_at' => gmdate('c'),
        'date' => $date,
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

function run_show(AhrefsStore $store): void
{
    $data = $store->read();
    if ($data === null) {
        throw new RuntimeException('No Ahrefs JSON saved yet. Run php bin/ahrefs.php fetch.');
    }
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

function run_watch(AhrefsStore $store, array $config): void
{
    $hours = $store->refreshHours();
    fwrite(STDERR, "Fetching Ahrefs data every {$hours} hour(s). Ctrl+C to stop.\n");
    while (true) {
        run_fetch($store, $config);
        sleep($hours * 3600);
    }
}

function print_help(): void
{
    echo <<<TXT
Cached Ahrefs Site Explorer summaries

  php bin/ahrefs.php fetch
  php bin/ahrefs.php show
  php bin/ahrefs.php watch

Reads school domains from Crazy Egg snapshots and writes storage/ahrefs/domain-overview.json.

TXT;
}
