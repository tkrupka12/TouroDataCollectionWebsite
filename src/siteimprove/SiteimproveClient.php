<?php

class SiteimproveClient
{
    private const US_BASE = 'https://api.siteimprove.com/v2';
    private const EU_BASE = 'https://api.eu.siteimprove.com/v2';

    public function __construct(
        private string $username,
        private string $apiKey,
        private string $baseUrl = self::US_BASE,
        private int $timeoutSeconds = 40,
    ) {
    }

    public static function fromEnv(): self
    {
        $apiKey = env_value('SITEIMPROVE_API_KEY');
        $username = env_value('SITEIMPROVE_USERNAME');
        if ($apiKey === null) {
            throw new RuntimeException('Set SITEIMPROVE_API_KEY in .env.');
        }
        if ($username === null) {
            throw new RuntimeException('Set SITEIMPROVE_USERNAME in .env. It is the API username shown next to the key in Siteimprove (Settings → Integrations → API → API Keys).');
        }

        $client = new self($username, $apiKey);
        try {
            $client->listSites(1, 1);
            return $client;
        } catch (Throwable $usError) {
            $eu = new self($username, $apiKey, self::EU_BASE);
            try {
                $eu->listSites(1, 1);
                return $eu;
            } catch (Throwable $euError) {
                throw new RuntimeException($usError->getMessage());
            }
        }
    }

    public function listAllSites(): array
    {
        $items = [];
        $page = 1;
        do {
            $payload = $this->listSites($page, 100);
            foreach ($payload['items'] ?? [] as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
            $totalPages = (int) ($payload['total_pages'] ?? 1);
            $page++;
        } while ($page <= $totalPages);

        return $items;
    }

    public function listSites(int $page = 1, int $pageSize = 100): array
    {
        return $this->getJson('/sites', [
            'page' => $page,
            'page_size' => $pageSize,
        ]);
    }

    public function siteOverview(int $siteId): array
    {
        $site = $this->getJson('/sites/' . $siteId);
        return [
            'site' => $site,
            'quality_assurance' => $this->tryJson('/sites/' . $siteId . '/quality_assurance/overview/summary'),
            'seo' => $this->tryJson('/sites/' . $siteId . '/seo/overview/summary'),
            'accessibility' => $this->tryJson('/sites/' . $siteId . '/accessibility/overview/summary'),
            'a11y' => $this->tryJson('/sites/' . $siteId . '/a11y/overview/summary'),
        ];
    }

    private function tryJson(string $path, array $params = []): array
    {
        try {
            return $this->getJson($path, $params);
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getJson(string $path, array $params = []): array
    {
        $url = $this->baseUrl . $path;
        if ($params !== []) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->username . ':' . $this->apiKey,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            throw new RuntimeException('Siteimprove request failed: ' . curl_error($ch));
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $decoded = json_decode((string) $body, true);
        if ($status < 200 || $status >= 300) {
            $details = is_array($decoded)
                ? ($decoded['message'] ?? $decoded['error'] ?? $decoded)
                : $body;
            $message = is_string($details)
                ? $details
                : (json_encode($details, JSON_UNESCAPED_SLASHES) ?: 'Unknown API error');
            throw new RuntimeException("Siteimprove HTTP {$status} {$path}: {$message}");
        }
        if (!is_array($decoded)) {
            throw new RuntimeException("Siteimprove returned invalid JSON for {$path}.");
        }

        return $decoded;
    }
}
