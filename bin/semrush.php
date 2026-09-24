<?php

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once APP_ROOT . '/src/semrush/SemrushClient.php';
require_once APP_ROOT . '/src/semrush/SemrushStore.php';

$command = $argv[1] ?? 'help';
$config = require APP_ROOT . '/config/app.php';
$store = new SemrushStore($config['semrush_storage']);

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

function run_fetch(SemrushStore $store, array $config): void
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

    $client = SemrushClient::fromEnv();
    $reports = [];
    $errors = [];

    foreach ($domains as $index => $domain) {
        $host = $domain['domain'];
        fwrite(STDERR, sprintf("[%d/%d] %s\n", $index + 1, count($domains), $host));
        try {
            $response = $client->backlinksOverview($host);
            $reports[] = [
                'domain' => $host,
                'schools' => $domain['schools'],
                'authority_score' => $response['data']['score'] ?? null,
                'backlinks' => $response['data']['backlinks_count'] ?? null,
                'referring_domains' => $response['data']['domains_count'] ?? null,
                'follow_links' => $response['data']['follows_count'] ?? null,
                'nofollow_links' => $response['data']['nofollows_count'] ?? null,
            ];
        } catch (Throwable $e) {
            $errors[] = [
                'domain' => $host,
                'message' => $e->getMessage(),
            ];
        }
    }

    $path = $store->save($reports, $errors);
    echo json_encode([
        'ok' => $reports !== [],
        'saved' => $path,
        'count' => count($reports),
        'error_count' => count($errors),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

function run_show(SemrushStore $store): void
{
    $data = $store->read();
    if ($data === null) {
        throw new RuntimeException('No Semrush JSON saved yet. Run php bin/semrush.php fetch.');
    }
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

function run_watch(SemrushStore $store, array $config): void
{
    $hours = $store->refreshHours();
    fwrite(STDERR, "Fetching Semrush data every {$hours} hour(s). Ctrl+C to stop.\n");
    while (true) {
        run_fetch($store, $config);
        sleep($hours * 3600);
    }
}

function print_help(): void
{
    echo <<<TXT
Cached Semrush backlink summaries

  php bin/semrush.php fetch
  php bin/semrush.php show
  php bin/semrush.php watch

Reads school domains from Crazy Egg snapshots and writes storage/semrush/domain-overview.json.

TXT;
}
