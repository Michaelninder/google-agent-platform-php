<?php

namespace GoogleAgentPlatform\Http;

/**
 * Low-level HTTP client.
 *
 * Handles all cURL communication with the Agent Platform API.
 * All higher-level resource classes receive an instance of this class
 * and call request() / requestRaw() / get() as needed.
 */
class HttpClient
{
    private const BASE_URL = 'https://aiplatform.googleapis.com/v1';

    private ?string $apiKey;
    private ?string $accessToken;
    private ?string $projectId;
    private string  $location;

    public function __construct(
        ?string $apiKey,
        ?string $accessToken,
        ?string $projectId,
        string  $location
    ) {
        $this->apiKey      = $apiKey;
        $this->accessToken = $accessToken;
        $this->projectId   = $projectId;
        $this->location    = $location;
    }

    // -------------------------------------------------------------------------
    // Public interface used by resource classes
    // -------------------------------------------------------------------------

    /**
     * POST to a model endpoint and return a decoded JSON array.
     */
    public function request(string $modelId, string $action, array $payload): array
    {
        $raw     = $this->requestRaw($modelId, $action, $payload);
        $decoded = \json_decode($raw, true);

        if (\json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Failed to decode API response as JSON. '
                . 'If this endpoint returns binary data, use requestRaw() instead.'
            );
        }

        if (isset($decoded['error'])) {
            $msg = $decoded['error']['message'] ?? 'Unknown API Error';
            throw new \RuntimeException("API Error: {$msg}");
        }

        return $decoded;
    }

    /**
     * POST to a model endpoint and return the raw response string.
     * Used for binary responses (audio, images) and as the base for request().
     */
    public function requestRaw(string $modelId, string $action, array $payload): string
    {
        [$publisher, $model] = $this->resolvePublisher($modelId);
        $url     = $this->buildModelUrl($publisher, $model, $action);
        $headers = $this->buildHeaders();

        return $this->execute($url, $headers, \json_encode($payload), 'POST');
    }

    /**
     * GET a URL and return a decoded JSON array.
     * Used for polling long-running operations.
     */
    public function get(string $url): array
    {
        $headers  = $this->buildHeaders();
        $raw      = $this->execute($url, $headers, null, 'GET');
        $decoded  = \json_decode($raw, true);

        if ($decoded === null) {
            throw new \RuntimeException('Failed to decode GET response as JSON.');
        }

        if (isset($decoded['error'])) {
            $msg = $decoded['error']['message'] ?? 'Unknown API Error';
            throw new \RuntimeException("API Error: {$msg}");
        }

        return $decoded;
    }

    /**
     * POST raw bytes (multipart or binary) to a URL and return decoded JSON.
     * Used by the File API upload.
     */
    public function postRaw(string $url, string $body, array $extraHeaders = []): array
    {
        $headers = \array_merge($this->buildAuthHeaders(), $extraHeaders);
        $raw     = $this->execute($url, $headers, $body, 'POST');
        $decoded = \json_decode($raw, true);

        if ($decoded === null) {
            throw new \RuntimeException('Failed to decode upload response as JSON.');
        }

        if (isset($decoded['error'])) {
            $msg = $decoded['error']['message'] ?? 'Unknown API Error';
            throw new \RuntimeException("API Error: {$msg}");
        }

        return $decoded;
    }

    // -------------------------------------------------------------------------
    // URL / header builders
    // -------------------------------------------------------------------------

    /**
     * Build the full model endpoint URL.
     */
    public function buildModelUrl(string $publisher, string $model, string $action): string
    {
        if ($this->apiKey) {
            return \sprintf(
                "%s/publishers/%s/models/%s:%s?key=%s",
                self::BASE_URL,
                $publisher,
                $model,
                $action,
                $this->apiKey
            );
        }

        return \sprintf(
            "%s/projects/%s/locations/%s/publishers/%s/models/%s:%s",
            self::BASE_URL,
            $this->projectId,
            $this->location,
            $publisher,
            $model,
            $action
        );
    }

    /**
     * Build the base URL for the File API (upload / metadata).
     */
    public function buildFileApiUrl(string $path = ''): string
    {
        $base = 'https://generativelanguage.googleapis.com/v1beta/files';

        if ($this->apiKey) {
            $sep = \str_contains($path, '?') ? '&' : '?';
            return $base . $path . $sep . 'key=' . $this->apiKey;
        }

        return $base . $path;
    }

    /**
     * Build the resumable upload initiation URL for the File API.
     */
    public function buildUploadUrl(): string
    {
        $base = 'https://generativelanguage.googleapis.com/upload/v1beta/files';

        if ($this->apiKey) {
            return $base . '?uploadType=resumable&key=' . $this->apiKey;
        }

        return $base . '?uploadType=resumable';
    }

    public function getProjectId(): ?string
    {
        return $this->projectId;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function getBaseUrl(): string
    {
        return self::BASE_URL;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve a modelId string into [publisher, model].
     * 'anthropic/claude-sonnet-4-6' → ['anthropic', 'claude-sonnet-4-6']
     * 'gemini-3.1-flash-lite-preview' → ['google', 'gemini-3.1-flash-lite-preview']
     */
    private function resolvePublisher(string $modelId): array
    {
        if (\strpos($modelId, '/') !== false) {
            return \explode('/', $modelId, 2);
        }

        return ['google', $modelId];
    }

    /**
     * Build Content-Type + auth headers for JSON API calls.
     */
    private function buildHeaders(): array
    {
        return \array_merge(
            ['Content-Type: application/json'],
            $this->buildAuthHeaders()
        );
    }

    /**
     * Build only the auth header (used when Content-Type is set separately).
     */
    private function buildAuthHeaders(): array
    {
        if ($this->accessToken) {
            return ["Authorization: Bearer {$this->accessToken}"];
        }

        return []; // API key is appended to the URL, not a header
    }

    /**
     * Execute a cURL request and return the raw response body.
     *
     * @param string      $url
     * @param string[]    $headers
     * @param string|null $body     POST body; null for GET
     * @param string      $method   'GET' or 'POST'
     */
    private function execute(string $url, array $headers, ?string $body, string $method): string
    {
        $ch = \curl_init();
        \curl_setopt($ch, CURLOPT_URL, $url);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            \curl_setopt($ch, CURLOPT_POST, true);
            \curl_setopt($ch, CURLOPT_POSTFIELDS, $body ?? '');
        }

        $response = \curl_exec($ch);
        $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = \curl_error($ch);

        if ($error) {
            throw new \RuntimeException("cURL Error: {$error}");
        }

        if ($httpCode >= 400) {
            $decoded = \json_decode($response, true);
            $msg     = $decoded['error']['message'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException("API Error ({$httpCode}): {$msg}");
        }

        return $response;
    }
}
