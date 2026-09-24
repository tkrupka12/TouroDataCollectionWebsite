<?php

class SemrushStore
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

    public function save(array $reports, array $errors = []): string
    {
        $payload = [
            'fetched_at' => gmdate('c'),
            'count' => count($reports),
            'error_count' => count($errors),
            'reports' => array_values($reports),
            'errors' => array_values($errors),
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Could not encode Semrush JSON.');
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

    public function isFresh(): bool
    {
        return CacheRefresh::isFresh($this->reportPath(), $this->refreshHours());
    }

    public function refreshHours(): int
    {
        return max(1, (int) (env_value('SEMRUSH_REFRESH_HOURS', '6') ?? '6'));
    }
}
