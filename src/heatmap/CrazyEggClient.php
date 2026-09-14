<?php

class CrazyEggClient
{
    private const BASE = 'https://app.crazyegg.com/api/v2';

    public function __construct(
        private string $apiKey,
        private string $apiSecret,
        private int $timeoutSeconds = 20
    ) {
    }

    public static function fromEnv(): self
    {
        $key = env_value('CRAZY_EGG_API_KEY');
        $secret = env_value('CRAZY_EGG_API_SECRET');
        if ($key === null || $secret === null) {
            throw new RuntimeException('Set CRAZY_EGG_API_KEY and CRAZY_EGG_API_SECRET in .env (Heatmap Management credentials, not Conversion Tracking).');
        }

        return new self($key, $secret);
    }

    public function listSnapshots(): array
    {
        $payload = $this->getJson('/snapshots.json', ['api_key' => $this->apiKey]);
        return $this->normalizeList($payload);
    }

    public function getSnapshot(string $id): array
    {
        try {
            $payload = $this->getJson('/snapshots/' . rawurlencode($id) . '.json', ['api_key' => $this->apiKey]);
            $normalized = $this->normalizeSnapshot($payload);
            if (($normalized['id'] ?? '') !== '') {
                return $normalized;
            }
        } catch (RuntimeException $e) {
            // Fall back to the documented list endpoint if get-by-id is not available.
        }

        foreach ($this->listSnapshots() as $snapshot) {
            if ((string) ($snapshot['id'] ?? '') === (string) $id) {
                return $snapshot;
            }
        }

        throw new RuntimeException('Snapshot not found: ' . $id);
    }

    /**
     * HMAC-SHA256 request signing used by Crazy Egg API v2.
     * In-dashboard docs: Options → site Settings → API (also referenced at /v2/options/api).
     * signed is query-only and is not part of the hashed string.
     */
    public function sign(array $params): string
    {
        $parts = [];
        foreach ($params as $name => $value) {
            if ($name === 'signed') {
                continue;
            }
            $parts[] = $name . $value;
        }
        sort($parts, SORT_STRING);
        return hash_hmac('sha256', implode('', $parts), $this->apiSecret);
    }

    private function getJson(string $path, array $params): mixed
    {
        $params['signed'] = $this->sign($params);
        $url = self::BASE . $path . '?' . http_build_query($params);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            throw new RuntimeException('Crazy Egg request failed: ' . curl_error($ch));
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Crazy Egg HTTP ' . $status . ': ' . mb_strimwidth((string) $body, 0, 300, '…', 'UTF-8'));
        }

        $decoded = json_decode((string) $body, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Crazy Egg returned non-JSON.');
        }

        return $decoded;
    }

    private function normalizeList(mixed $payload): array
    {
        if (!is_array($payload)) {
            return [];
        }

        $rows = $payload;
        foreach (['snapshots', 'data', 'results'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $rows = $payload[$key];
                break;
            }
        }

        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $this->normalizeSnapshot($row);
            }
        }
        return $out;
    }

    private function normalizeSnapshot(mixed $row): array
    {
        if (!is_array($row)) {
            return ['id' => '', 'raw' => $row];
        }

        $url = $row['source_url'] ?? $row['url'] ?? $row['page_url'] ?? '';
        $visits = $row['total_visits'] ?? $row['visits'] ?? $row['visit_count'] ?? null;
        $clicks = $row['total_clicks'] ?? $row['clicks'] ?? null;

        return [
            'id' => (string) ($row['id'] ?? $row['snapshot_id'] ?? ''),
            'name' => (string) ($row['name'] ?? $row['title'] ?? ''),
            'url' => (string) $url,
            'status' => (string) ($row['status'] ?? ''),
            'visits' => is_numeric($visits) ? (int) $visits : null,
            'clicks' => is_numeric($clicks) ? (int) $clicks : null,
            'created_at' => $row['created_at'] ?? $row['created'] ?? null,
            'started_at' => $row['started_at'] ?? $row['start_date'] ?? $row['starts_at'] ?? null,
            'ended_at' => $row['ended_at'] ?? $row['end_date'] ?? $row['expires_at'] ?? null,
            'raw' => $row,
        ];
    }
}
