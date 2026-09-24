<?php

class AhrefsClient
{
    private const BASE = 'https://api.ahrefs.com/v3/site-explorer';

    public function __construct(
        private string $apiKey,
        private int $timeoutSeconds = 40,
    ) {
    }

    public static function fromEnv(): self
    {
        $apiKey = env_value('AHREFS_API_KEY');
        if ($apiKey === null) {
            throw new RuntimeException('Set AHREFS_API_KEY in .env.');
        }

        return new self($apiKey);
    }

    public function domainOverview(string $domain, string $date): array
    {
        $params = [
            'target' => $domain,
            'date' => $date,
            'mode' => 'domain',
            'protocol' => 'both',
        ];

        $rating = $this->getJson('/domain-rating', [
            'target' => $domain,
            'date' => $date,
        ]);
        $metrics = $this->getJson('/metrics', $params);
        $backlinks = $this->getJson('/backlinks-stats', $params);

        return [
            'domain_rating' => $rating,
            'metrics' => $metrics,
            'backlinks_stats' => $backlinks,
            'pages_by_traffic' => $this->tryJson('/pages-by-traffic', [
                'target' => $domain,
                'mode' => 'domain',
                'protocol' => 'both',
            ]),
            'outlinks_stats' => $this->tryJson('/outlinks-stats', [
                'target' => $domain,
                'mode' => 'domain',
                'protocol' => 'both',
            ]),
            'organic_keywords' => $this->tryJson('/organic-keywords', [
                'target' => $domain,
                'date' => $date,
                'mode' => 'domain',
                'protocol' => 'both',
                'country' => 'us',
                'limit' => 10,
                'order_by' => 'sum_traffic:desc',
                'select' => 'keyword,best_position,volume,sum_traffic,keyword_difficulty,best_position_url',
            ]),
        ];
    }

    private function tryJson(string $path, array $params): array
    {
        try {
            return $this->getJson($path, $params);
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getJson(string $path, array $params): array
    {
        $url = self::BASE . $path . '?' . http_build_query($params);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            throw new RuntimeException('Ahrefs request failed: ' . curl_error($ch));
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $decoded = json_decode((string) $body, true);
        if ($status < 200 || $status >= 300) {
            $details = is_array($decoded)
                ? ($decoded['error'] ?? $decoded['message'] ?? $decoded)
                : $body;
            $message = is_string($details)
                ? $details
                : (json_encode($details, JSON_UNESCAPED_SLASHES) ?: 'Unknown API error');
            throw new RuntimeException("Ahrefs HTTP {$status} {$path}: {$message}");
        }
        if (!is_array($decoded)) {
            throw new RuntimeException("Ahrefs returned invalid JSON for {$path}.");
        }

        return $decoded;
    }
}
