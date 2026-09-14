<?php

class CrazyEggStore
{
    public function __construct(private string $dir)
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
    }

    public function snapshotsPath(): string
    {
        return $this->dir . '/snapshots.json';
    }

    public function snapshotPath(string $id): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $id) ?: 'unknown';
        return $this->dir . '/snapshot-' . $safe . '.json';
    }

    public function saveSnapshots(array $snapshots): string
    {
        return $this->write($this->snapshotsPath(), [
            'fetched_at' => gmdate('c'),
            'count' => count($snapshots),
            'snapshots' => $snapshots,
        ]);
    }

    public function saveSnapshot(array $snapshot): string
    {
        $id = (string) ($snapshot['id'] ?? 'unknown');
        return $this->write($this->snapshotPath($id), [
            'fetched_at' => gmdate('c'),
            'snapshot' => $snapshot,
        ]);
    }

    public function readSnapshots(): ?array
    {
        return $this->read($this->snapshotsPath());
    }

    public function snapshotsForUrl(array $snapshots, string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return $snapshots;
        }

        $needle = self::normalizeLookupUrl($query);
        if ($needle === '') {
            return [];
        }

        $hostOnly = self::isHostOnlyLookup($query);
        $needleHost = explode('/', $needle, 2)[0];
        $matches = [];

        foreach ($snapshots as $row) {
            $hay = self::normalizeLookupUrl((string) ($row['url'] ?? ''));
            if ($hay === '') {
                continue;
            }
            $hayHost = explode('/', $hay, 2)[0];
            if ($hostOnly) {
                if ($hayHost === $needleHost) {
                    $matches[] = $row;
                }
                continue;
            }
            if ($hay === $needle) {
                $matches[] = $row;
            }
        }

        return $matches;
    }

    public static function isHostOnlyLookup(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        $path = parse_url($url, PHP_URL_PATH);
        return $path === null || $path === '';
    }

    public static function normalizeLookupUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $path = $parts['path'] ?? '/';
        $path = rtrim($path, '/') ?: '/';

        return $host . $path;
    }

    public function isFresh(int $minutes): bool
    {
        $path = $this->snapshotsPath();
        if (!is_file($path)) {
            return false;
        }
        return (time() - filemtime($path)) < ($minutes * 60);
    }

    public function refreshMinutes(): int
    {
        $minutes = (int) (env_value('CRAZY_EGG_REFRESH_MINUTES', '15') ?? '15');
        return max(1, $minutes);
    }

    private function write(string $path, array $data): string
    {
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $path;
    }

    private function read(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : null;
    }
}
