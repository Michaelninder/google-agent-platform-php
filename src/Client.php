<?php

namespace GoogleAgentPlatform;

class Client
{
    private string $baseUrl = 'https://aiplatform.googleapis.com/v1';
    private ?string $apiKey;
    private ?string $accessToken;
    private ?string $projectId;
    private string $location;

    /**
     * Initialize the Agent Platform Client.
     * * Accepts an array with either 'api_key' (Express Mode) OR ('access_token', 'project_id').
     * 'location' defaults to 'global'.
     */
    public function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? null;
        $this->accessToken = $config['access_token'] ?? null;
        $this->projectId = $config['project_id'] ?? null;
        $this->location = $config['location'] ?? 'global';

        if (!$this->apiKey && (!$this->accessToken || !$this->projectId)) {
            throw new \InvalidArgumentException(
                "You must provide either an 'api_key' OR both 'access_token' and 'project_id'."
            );
        }
    }

    /**
     * Standard content generation.
     * Defaults to 'gemini-3.1-flash-lite-preview'
     */
    public function generateContent(array $contents, string $modelId = 'gemini-3.1-flash-lite-preview'): array
    {
        return $this->request($modelId, 'generateContent', ['contents' => $contents]);
    }

    /**
     * Streamed content generation.
     * Defaults to 'gemini-3.1-flash-lite-preview'
     */
    public function streamGenerateContent(array $contents, string $modelId = 'gemini-3.1-flash-lite-preview'): array
    {
        return $this->request($modelId, 'streamGenerateContent', ['contents' => $contents]);
    }

    /**
     * Send a raw prediction/generation request (Useful for TTS and non-standard endpoints).
     */
    public function predict(array $payload, string $modelId, string $action = 'predict'): array
    {
        return $this->request($modelId, $action, $payload);
    }

    /**
     * Core request handler. Supports dynamic publishers (e.g., Google vs ElevenLabs).
     */
    private function request(string $modelId, string $action, array $payload): array
    {
        // Dynamically resolve the publisher (Google is default)
        $publisher = 'google';
        $model = $modelId;
        
        if (strpos($modelId, '/') !== false) {
            [$publisher, $model] = explode('/', $modelId, 2);
        }

        $ch = curl_init();
        $headers = ['Content-Type: application/json'];

        // Determine Endpoint and Auth Headers based on config (Express vs Cloud mode)
        if ($this->apiKey) {
            $url = sprintf(
                "%s/publishers/%s/models/%s:%s?key=%s",
                $this->baseUrl,
                $publisher,
                $model,
                $action,
                $this->apiKey
            );
        } else {
            $url = sprintf(
                "%s/projects/%s/locations/%s/publishers/%s/models/%s:%s",
                $this->baseUrl,
                $this->projectId,
                $this->location,
                $publisher,
                $model,
                $action
            );
            $headers[] = 'Authorization: Bearer ' . $this->accessToken;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("cURL Error: " . $error);
        }

        $decodedResponse = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMessage = $decodedResponse['error']['message'] ?? 'Unknown API Error';
            throw new \RuntimeException("API Error ({$httpCode}): " . $errorMessage);
        }

        return $decodedResponse;
    }
}
