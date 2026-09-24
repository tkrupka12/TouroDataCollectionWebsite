<?php

class SemrushClient
{
    private const OVERVIEW_URL = 'https://api.semrush.com/apis/v4/backlinks/v1/overview';

    public function __construct(
        private string $apiKey,
        private int $timeoutSeconds = 30,
    ) {
    }

    public static function fromEnv(): self
    {
        $apiKey = env_value('SEMRUSH_API_KEY');
        if ($apiKey === null) {
            throw new RuntimeException('Set SEMRUSH_API_KEY in .env.');
        }

        return new self($apiKey);
    }

    public function backlinksOverview(string $domain): array
    {
        $query = http_build_query([
            'url' => $domain,
            'scope' => 'SUBDOMAIN',
            'fields' => 'backlinks_count,domains_count,score,follows_count,nofollows_count',
        ]);
        $ch = curl_init(self::OVERVIEW_URL . '?' . $query);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Apikey ' . $this->apiKey,
            ],
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            throw new RuntimeException('Semrush request failed: ' . curl_error($ch));
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
            throw new RuntimeException("Semrush HTTP {$status}: {$message}");
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('Semrush returned invalid JSON.');
        }

        return $decoded;
    }
}
