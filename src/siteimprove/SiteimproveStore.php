<?php

class SiteimproveStore
{
    public function __construct(private string $dir)
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
    }

    public function reportPath(): string
    {
        return $this->dir . '/sites-overview.json';
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
            throw new RuntimeException('Could not encode Siteimprove JSON.');
        }

        $path = $this->reportPath();
        $temporary = $path . '.tmp';
        file_put_contents($temporary, $json);
        rename($temporary, $path);
        return $path;
    }

    public function normalizeReport(array $raw): array
    {
        $site = $raw['site'] ?? [];
        $qa = $raw['quality_assurance'] ?? [];
        $seo = $raw['seo'] ?? [];
        $a11y = $raw['accessibility'] ?? [];
        $a11yNext = $raw['a11y'] ?? [];

        return [
            'id' => (string) ($site['id'] ?? ''),
            'name' => (string) ($site['site_name'] ?? $site['name'] ?? 'Untitled site'),
            'url' => (string) ($site['url'] ?? ''),
            'products' => $site['product'] ?? $site['products'] ?? [],
            'pages' => $qa['pages'] ?? $site['pages'] ?? null,
            'broken_links' => $qa['broken_links'] ?? $qa['broken_links_excluding_documents'] ?? null,
            'misspellings' => $qa['misspellings'] ?? null,
            'potential_misspellings' => $qa['potential_misspellings'] ?? null,
            'seo_issues' => $seo['issues'] ?? $seo['issue_count'] ?? null,
            'seo_pages' => $seo['pages'] ?? null,
            'accessibility_issues' => $a11y['issues'] ?? $a11yNext['issues'] ?? $a11y['issue_count'] ?? null,
            'accessibility_occurrences' => $a11y['occurrences'] ?? $a11yNext['occurrences'] ?? null,
            'raw' => $raw,
        ];
    }

    public function isFresh(): bool
    {
        return CacheRefresh::isFresh($this->reportPath(), $this->refreshHours());
    }

    public function refreshHours(): int
    {
        return max(1, (int) (env_value('SITEIMPROVE_REFRESH_HOURS', '6') ?? '6'));
    }
}
