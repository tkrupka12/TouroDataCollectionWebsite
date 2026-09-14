<?php

class Tracker
{
    public function __construct(private array $config)
    {
    }

    public function probe(): array
    {
        $url = $this->config['target_url'];
        $started = microtime(true);
        $error = null;
        $statusCode = 0;
        $finalUrl = $url;
        $contentType = '';
        $bytes = 0;
        $redirects = 0;
        $title = '';
        $snippet = '';
        $headers = [];
        $ssl = null;

        $first = $this->fetch($url);
        $error = $first['error'];
        $statusCode = $first['status_code'];
        $finalUrl = $first['final_url'];
        $contentType = $first['content_type'];
        $bytes = $first['bytes'];
        $redirects = $first['redirects'];
        $headers = $first['headers'];
        $body = $first['body'];
        $title = $this->extractTitle($body);
        $snippet = $this->summarize($body, $finalUrl, $headers);
        $ssl = $this->sslInfo($finalUrl);

        if ($error === null && $this->looksLikeJsRedirect($body ?? '')) {
            $redirectTarget = $this->absoluteUrl($finalUrl, '/lander');
            $follow = $this->fetch($redirectTarget);
            if ($follow['error'] === null) {
                $finalUrl = $follow['final_url'];
                $statusCode = $follow['status_code'];
                $contentType = $follow['content_type'];
                $bytes = $follow['bytes'];
                $redirects += 1;
                $headers = $follow['headers'];
                $title = $this->extractTitle($follow['body']);
                $snippet = $this->summarize($follow['body'], $finalUrl, $headers);
                $ssl = $this->sslInfo($finalUrl);
            }
        }

        $ms = (int) round((microtime(true) - $started) * 1000);
        $online = $error === null && $statusCode >= 200 && $statusCode < 400;

        $result = [
            'ok' => $online,
            'checked_at' => gmdate('c'),
            'target_name' => $this->config['target_name'],
            'target_url' => $url,
            'final_url' => $finalUrl,
            'status_code' => $statusCode,
            'response_ms' => $ms,
            'redirects' => $redirects,
            'bytes' => $bytes,
            'content_type' => $contentType,
            'title' => $title,
            'summary' => $snippet,
            'ssl' => $ssl,
            'error' => $error,
            'measurement_id' => $this->config['measurement_id'],
        ];

        $history = $this->store($result);
        $result['history'] = $history;
        $result['averages'] = $this->averages($history);

        return $result;
    }

    private function fetch(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 8,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => (int) $this->config['timeout_seconds'],
            CURLOPT_USERAGENT => 'TouroTracker/1.0 (+practice dashboard)',
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => '',
        ]);

        $raw = curl_exec($ch);
        $result = [
            'error' => null,
            'status_code' => 0,
            'final_url' => $url,
            'content_type' => '',
            'bytes' => 0,
            'redirects' => 0,
            'headers' => [],
            'body' => '',
        ];

        if ($raw === false) {
            $result['error'] = curl_error($ch);
            return $result;
        }

        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($raw, $headerSize);
        $result['status_code'] = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $result['final_url'] = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $result['content_type'] = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $result['redirects'] = (int) curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
        $result['bytes'] = strlen($body);
        $result['headers'] = $this->parseResponseHeaders(substr($raw, 0, $headerSize));
        $result['body'] = $body;

        return $result;
    }

    private function looksLikeJsRedirect(string $body): bool
    {
        return (bool) preg_match('/location\.href\s*=\s*[\'"]\/lander[\'"]/', $body);
    }

    private function absoluteUrl(string $from, string $path): string
    {
        $parts = parse_url($from);
        return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'www.touro.org') . $path;
    }

    private function parseResponseHeaders(string $headerText): array
    {
        $interesting = [];
        foreach (explode("\n", $headerText) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $key = strtolower(trim($name));
            if (in_array($key, ['server', 'x-powered-by', 'cache-control', 'location'], true)) {
                $interesting[$key] = trim($value);
            }
        }
        return $interesting;
    }

    private function extractTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $match)) {
            $title = html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return trim(preg_replace('/\s+/', ' ', $title) ?? '');
        }
        return '';
    }

    private function summarize(string $body, string $finalUrl, array $headers): string
    {
        $lower = strtolower($body . $finalUrl . json_encode($headers));
        if (str_contains($lower, 'parking-lander') || str_contains($lower, 'lander_system') || str_contains($finalUrl, '/lander')) {
            return 'Domain parking / lander page is being served.';
        }
        if (str_contains($lower, 'touro university')) {
            return 'Touro University site content detected.';
        }
        if (str_contains($lower, 'touro infirmary') || str_contains($lower, 'lcmc')) {
            return 'Touro Infirmary / LCMC Health content detected.';
        }
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');
        if ($text === '') {
            return 'Page returned no readable text.';
        }
        return mb_strimwidth($text, 0, 140, '…', 'UTF-8');
    }

    private function sslInfo(string $url): ?array
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return null;
        }

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $client = @stream_socket_client(
            'ssl://' . $host . ':443',
            $errno,
            $errstr,
            6,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$client) {
            return ['host' => $host, 'valid' => false, 'error' => $errstr];
        }

        $params = stream_context_get_params($client);
        fclose($client);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if (!$cert) {
            return ['host' => $host, 'valid' => false, 'error' => 'No certificate'];
        }

        $info = openssl_x509_parse($cert);
        if ($info === false) {
            return ['host' => $host, 'valid' => false, 'error' => 'Could not parse certificate'];
        }
        $expires = isset($info['validTo_time_t']) ? (int) $info['validTo_time_t'] : 0;

        return [
            'host' => $host,
            'valid' => $expires > time(),
            'issuer' => $info['issuer']['O'] ?? ($info['issuer']['CN'] ?? 'Unknown'),
            'expires' => $expires ? gmdate('Y-m-d', $expires) : null,
        ];
    }

    private function store(array $result): array
    {
        $file = $this->config['history_file'];
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $history = [];
        if (is_file($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                $history = $decoded;
            }
        }

        $history[] = [
            'checked_at' => $result['checked_at'],
            'ok' => $result['ok'],
            'status_code' => $result['status_code'],
            'response_ms' => $result['response_ms'],
        ];

        $history = array_slice($history, -1 * (int) $this->config['history_limit']);
        file_put_contents($file, json_encode($history, JSON_PRETTY_PRINT));

        return $history;
    }

    private function averages(array $history): array
    {
        if ($history === []) {
            return ['uptime' => 0, 'avg_ms' => 0];
        }

        $ok = 0;
        $totalMs = 0;
        foreach ($history as $row) {
            $ok += !empty($row['ok']) ? 1 : 0;
            $totalMs += (int) ($row['response_ms'] ?? 0);
        }

        return [
            'uptime' => round(($ok / count($history)) * 100),
            'avg_ms' => (int) round($totalMs / count($history)),
            'checks' => count($history),
        ];
    }
}
