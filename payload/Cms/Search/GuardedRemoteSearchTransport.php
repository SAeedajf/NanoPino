<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Search;

use App\com_pinoox_cms\Cms\Security\Network\NetworkEndpointPolicy;
use App\com_pinoox_cms\Cms\Security\Network\SsrfGuard;

final readonly class GuardedRemoteSearchTransport implements RemoteSearchTransportInterface
{
    public function __construct(
        private RemoteSearchConfiguration $config,
        private SsrfGuard $guard,
        private int $timeoutSeconds = 5,
        private int $maxResponseBytes = 2_000_000,
    ) {}

    public function request(string $driver, string $operation, array $payload): array
    {
        if ($driver !== $this->config->driver) {
            throw new \RuntimeException('Remote search driver/configuration mismatch.');
        }
        if (!in_array($operation, ['index', 'delete', 'search', 'health'], true)) {
            throw new \InvalidArgumentException('Unsupported remote search operation.');
        }
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('Remote search requires the PHP cURL extension.');
        }

        [$method, $url, $body, $headers] = $driver === 'meilisearch'
            ? $this->meilisearch($operation, $payload)
            : $this->typesense($operation, $payload);

        $parts = parse_url($this->config->endpoint);
        $host = strtolower(rtrim((string)($parts['host'] ?? ''), '.'));
        $port = isset($parts['port']) ? (int)$parts['port'] : 443;
        $policy = new NetworkEndpointPolicy([$host], [$port], true);
        $approved = $this->guard->assertAllowed($url, $policy);

        $response = $this->send(
            $method,
            $approved['url'],
            $approved['host'],
            $approved['port'],
            $approved['addresses'],
            $body,
            $headers,
        );

        return $this->normalize($driver, $operation, $response);
    }

    /** @return array{0:string,1:string,2:?array<string,mixed>|list<array<string,mixed>>,3:list<string>} */
    private function meilisearch(string $operation, array $payload): array
    {
        $base = $this->config->endpoint;
        $index = rawurlencode($this->config->index);
        $headers = $this->config->apiKey !== '' ? ['Authorization: Bearer ' . $this->config->apiKey] : [];

        return match ($operation) {
            'health' => ['GET', $base . '/health', null, $headers],
            'index' => ['POST', $base . '/indexes/' . $index . '/documents?primaryKey=id', [$payload], $headers],
            'delete' => ['DELETE', $base . '/indexes/' . $index . '/documents/' . rawurlencode($this->documentKey($payload)), null, $headers],
            'search' => ['POST', $base . '/indexes/' . $index . '/search', [
                'q' => (string)($payload['text'] ?? ''),
                'filter' => $this->meiliFilter($payload),
                'limit' => (int)($payload['limit'] ?? 20),
                'offset' => (int)($payload['offset'] ?? 0),
            ], $headers],
        };
    }

    /** @return array{0:string,1:string,2:?array<string,mixed>,3:list<string>} */
    private function typesense(string $operation, array $payload): array
    {
        $base = $this->config->endpoint;
        $collection = rawurlencode($this->config->index);
        $headers = $this->config->apiKey !== '' ? ['X-TYPESENSE-API-KEY: ' . $this->config->apiKey] : [];

        return match ($operation) {
            'health' => ['GET', $base . '/health', null, $headers],
            'index' => ['POST', $base . '/collections/' . $collection . '/documents?action=upsert', $payload, $headers],
            'delete' => ['DELETE', $base . '/collections/' . $collection . '/documents/' . rawurlencode($this->documentKey($payload)), null, $headers],
            'search' => ['GET', $base . '/collections/' . $collection . '/documents/search?' . http_build_query([
                'q' => (string)($payload['text'] ?? '*'),
                'query_by' => 'title,body',
                'filter_by' => $this->typesenseFilter($payload),
                'per_page' => (int)($payload['limit'] ?? 20),
                'page' => intdiv((int)($payload['offset'] ?? 0), max(1, (int)($payload['limit'] ?? 20))) + 1,
            ], '', '&', PHP_QUERY_RFC3986), null, $headers],
        };
    }

    /** @param list<string> $addresses @param list<string> $headers @param array<string,mixed>|list<array<string,mixed>>|null $body */
    private function send(string $method, string $url, string $host, int $port, array $addresses, ?array $body, array $headers): array
    {
        $ch = curl_init();
        if ($ch === false) throw new \RuntimeException('Unable to initialize outbound search request.');

        $responseBody = '';
        $responseBytes = 0;
        $headers[] = 'Accept: application/json';
        if ($body !== null) $headers[] = 'Content-Type: application/json';

        $resolve = array_map(
            static fn (string $ip): string => $host . ':' . $port . ':' . $ip,
            $addresses,
        );

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(3, $this->timeoutSeconds),
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_RESOLVE => $resolve,
            CURLOPT_HEADER => false,
            CURLOPT_WRITEFUNCTION => function ($curl, string $chunk) use (&$responseBody, &$responseBytes): int {
                $length = strlen($chunk);
                $responseBytes += $length;
                if ($responseBytes > $this->maxResponseBytes) return 0;
                $responseBody .= $chunk;
                return $length;
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($ok === false || $responseBytes > $this->maxResponseBytes) {
            throw new \RuntimeException($responseBytes > $this->maxResponseBytes
                ? 'Remote search response exceeded the configured size limit.'
                : 'Remote search transport failed: ' . ($error !== '' ? $error : 'unknown error'));
        }
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('Remote search returned HTTP ' . $status . '.');
        }
        if ($responseBody === '') return [];

        $decoded = json_decode($responseBody, true, 128, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    }

    private function normalize(string $driver, string $operation, array $response): array
    {
        if ($operation === 'health') {
            $ok = $driver === 'typesense'
                ? (($response['ok'] ?? false) === true)
                : (($response['status'] ?? 'available') === 'available');
            return ['status' => $ok ? 'ok' : 'error', 'message' => $driver . ' health probe completed.'];
        }
        if ($operation !== 'search') return $response;

        if ($driver === 'meilisearch') {
            return [
                'hits' => array_map(fn (array $hit): array => $this->hit($hit, (float)($hit['_rankingScore'] ?? 0)), array_values(array_filter($response['hits'] ?? [], 'is_array'))),
                'total' => (int)($response['estimatedTotalHits'] ?? count($response['hits'] ?? [])),
            ];
        }

        $rows = [];
        foreach (array_values(array_filter($response['hits'] ?? [], 'is_array')) as $entry) {
            $document = is_array($entry['document'] ?? null) ? $entry['document'] : [];
            $rows[] = $this->hit($document, (float)($entry['text_match'] ?? 0));
        }
        return ['hits' => $rows, 'total' => (int)($response['found'] ?? count($rows))];
    }

    private function hit(array $document, float $score): array
    {
        return [
            'id' => (string)($document['source_id'] ?? $document['id'] ?? ''),
            'type' => (string)($document['type'] ?? ''),
            'title' => (string)($document['title'] ?? ''),
            'url' => isset($document['url']) ? (string)$document['url'] : null,
            'score' => $score,
            'excerpt' => substr((string)($document['body'] ?? ''), 0, 500),
            'metadata' => is_array($document['metadata'] ?? null) ? $document['metadata'] : [],
        ];
    }

    private function documentKey(array $payload): string
    {
        return implode('__', [
            (string)($payload['site_id'] ?? ''),
            (string)($payload['type'] ?? ''),
            (string)($payload['locale'] ?? ''),
            (string)($payload['id'] ?? ''),
        ]);
    }

    private function meiliFilter(array $payload): ?string
    {
        $filters = ['site_id = ' . max(1, (int)($payload['site_id'] ?? 1))];
        $locale = trim((string)($payload['locale'] ?? ''));
        if ($locale !== '') $filters[] = 'locale = "' . addcslashes($locale, "\\"") . '"';
        $types = array_values(array_filter($payload['types'] ?? [], 'is_string'));
        if ($types !== []) $filters[] = 'type IN [' . implode(',', array_map(static fn(string $v): string => '"' . addcslashes($v, "\\"") . '"', $types)) . ']';
        return implode(' AND ', $filters);
    }

    private function typesenseFilter(array $payload): string
    {
        $filters = ['site_id:=' . max(1, (int)($payload['site_id'] ?? 1))];
        $locale = trim((string)($payload['locale'] ?? ''));
        if ($locale !== '') $filters[] = 'locale:=' . preg_replace('/[^A-Za-z0-9._-]/', '', $locale);
        $types = array_values(array_filter($payload['types'] ?? [], 'is_string'));
        if ($types !== []) $filters[] = 'type:=[' . implode(',', array_map(static fn(string $v): string => preg_replace('/[^A-Za-z0-9._-]/', '', $v), $types)) . ']';
        return implode(' && ', $filters);
    }
}
