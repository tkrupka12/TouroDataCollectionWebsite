<?php

class AhrefsStore
{
    public function __construct(private string $dir)
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
    }

    public function reportPath(): string
    {
        return $this->dir . '/domain-overview.json';
    }

    public function read(): ?array
    {
        $path = $this->reportPath();
        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }

    public function save(array $payload): string
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Could not encode Ahrefs JSON.');
        }

        $path = $this->reportPath();
        $temporary = $path . '.tmp';
        file_put_contents($temporary, $json);
        rename($temporary, $path);
        return $path;
    }

    public function domainsFromSnapshots(array $snapshots): array
    {
        $domains = [];
        foreach ($snapshots as $snapshot) {
            $url = trim((string) ($snapshot['url'] ?? ''));
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $host = preg_replace('/^www\./', '', $host) ?? $host;
            if ($host === '') {
                continue;
            }

            if (!isset($domains[$host])) {
                $domains[$host] = [
                    'domain' => $host,
                    'url' => $url !== '' ? $url : ('https://' . $host . '/'),
                    'schools' => [],
                ];
            }

            $name = trim((string) ($snapshot['name'] ?? ''));
            if ($name !== '' && !in_array($name, $domains[$host]['schools'], true)) {
                $domains[$host]['schools'][] = $name;
            }
        }

        ksort($domains);
        return array_values($domains);
    }

    public function normalizeReport(array $domain, array $raw, string $date): array
    {
        $rating = $raw['domain_rating']['domain_rating'] ?? [];
        $metrics = $raw['metrics']['metrics'] ?? [];
        $backlinks = $raw['backlinks_stats']['metrics'] ?? [];
        $pages = $raw['pages_by_traffic']['pages'] ?? [];
        $outlinks = $raw['outlinks_stats']['metrics'] ?? $raw['outlinks_stats'] ?? [];
        $keywords = $raw['organic_keywords']['keywords']
            ?? $raw['organic_keywords']['organic_keywords']
            ?? $raw['organic_keywords'] ?? [];

        $orgCostCents = $metrics['org_cost'] ?? null;

        return [
            'id' => $domain['domain'],
            'name' => $domain['schools'][0] ?? $domain['domain'],
            'url' => $domain['url'],
            'domain' => $domain['domain'],
            'schools' => $domain['schools'],
            'date' => $date,
            'domain_rating' => $rating['domain_rating'] ?? null,
            'ahrefs_rank' => $rating['ahrefs_rank'] ?? null,
            'live_backlinks' => $backlinks['live'] ?? null,
            'live_refdomains' => $backlinks['live_refdomains'] ?? null,
            'all_time_backlinks' => $backlinks['all_time'] ?? null,
            'all_time_refdomains' => $backlinks['all_time_refdomains'] ?? null,
            'organic_keywords' => $metrics['org_keywords'] ?? null,
            'organic_keywords_top3' => $metrics['org_keywords_1_3'] ?? null,
            'organic_traffic' => $metrics['org_traffic'] ?? null,
            'organic_value_usd' => is_numeric($orgCostCents) ? ((int) $orgCostCents) / 100 : null,
            'paid_keywords' => $metrics['paid_keywords'] ?? null,
            'paid_traffic' => $metrics['paid_traffic'] ?? null,
            'pages_by_traffic' => is_array($pages) ? $pages : null,
            'outlinks' => is_array($outlinks) ? $outlinks : null,
            'top_keywords' => is_array($keywords) ? array_values($keywords) : [],
            'raw' => $raw,
        ];
    }

    public function isFresh(): bool
    {
        return CacheRefresh::isFresh($this->reportPath(), $this->refreshHours());
    }

    public function refreshHours(): int
    {
        return max(1, (int) (env_value('AHREFS_REFRESH_HOURS', '6') ?? '6'));
    }
}
