<?php

declare(strict_types=1);

namespace ThuliumBridge\Infrastructure\Thulium;

use JsonException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class ThuliumHttpClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $authUser,
        private readonly string $authPassword,
        private readonly int $timeoutSeconds,
        private readonly LoggerInterface $logger
    ) {
    }

    /** @return array<string,mixed> */
    public function request(string $method, string $path, array $payload = [], array $query = []): array
    {
        $method = strtoupper($method);
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Cannot initialize cURL.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $requestBody = null;
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $requestBody = $this->encodeJson($payload);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => min($this->timeoutSeconds, 5),
            CURLOPT_USERPWD => $this->authUser . ':' . $this->authPassword,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_HEADER => true,
        ]);

        if ($requestBody !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('HTTP transport failure: ' . $curlError);
        }

        $rawHeaders = substr($raw, 0, $headerSize);
        $rawBody = substr($raw, $headerSize);
        $requestId = $this->extractRequestId($rawHeaders);

        $this->logger->info('thulium_http', [
            'method' => $method,
            'path' => $path,
            'status_code' => $status,
            'request_id' => $requestId,
            'query' => $query,
            'payload_meta' => $this->payloadMeta($payload),
        ]);

        if ($status >= 400) {
            throw new ThuliumHttpException(
                sprintf('Thulium API error %d for %s %s', $status, $method, $path),
                $status,
                $requestId,
            );
        }

        if (trim($rawBody) === '') {
            return [];
        }

        $decoded = json_decode($rawBody, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function encodeJson(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('JSON encoding failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function extractRequestId(string $rawHeaders): ?string
    {
        foreach (explode("\r\n", $rawHeaders) as $headerLine) {
            if (stripos($headerLine, 'X-Request-Id:') === 0) {
                return trim(substr($headerLine, strlen('X-Request-Id:')));
            }
        }

        return null;
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function payloadMeta(array $payload): array
    {
        return [
            'keys' => array_values(array_map('strval', array_keys($payload))),
            'values_count' => count($payload),
            'contains_values_array' => isset($payload['values']) && is_array($payload['values']),
        ];
    }
}
