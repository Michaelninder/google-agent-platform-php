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
     *
     * Accepts an array with either 'api_key' (Express Mode) OR ('access_token', 'project_id').
     * 'location' defaults to 'global'. Use 'us-east5' for Anthropic models,
     * 'us-central1' for Veo video generation.
     */
    public function __construct(array $config)
    {
        $this->apiKey      = $config['api_key'] ?? null;
        $this->accessToken = $config['access_token'] ?? null;
        $this->projectId   = $config['project_id'] ?? null;
        $this->location    = $config['location'] ?? 'global';

        if (!$this->apiKey && (!$this->accessToken || !$this->projectId)) {
            throw new \InvalidArgumentException(
                "You must provide either an 'api_key' OR both 'access_token' and 'project_id'."
            );
        }
    }

    // -------------------------------------------------------------------------
    // Gemini — Standard generation
    // -------------------------------------------------------------------------

    /**
     * Standard (non-streaming) content generation.
     * Defaults to 'gemini-3.1-flash-lite-preview'.
     */
    public function generateContent(array $contents, string $modelId = 'gemini-3.1-flash-lite-preview'): array
    {
        return $this->request($modelId, 'generateContent', ['contents' => $contents]);
    }

    /**
     * Streamed content generation.
     * Defaults to 'gemini-3.1-flash-lite-preview'.
     */
    public function streamGenerateContent(array $contents, string $modelId = 'gemini-3.1-flash-lite-preview'): array
    {
        return $this->request($modelId, 'streamGenerateContent', ['contents' => $contents]);
    }

    // -------------------------------------------------------------------------
    // Anthropic Claude — Messages API
    // -------------------------------------------------------------------------

    /**
     * Send a request to an Anthropic Claude model hosted on Agent Platform.
     *
     * Key differences from the direct Anthropic API:
     *  - 'model' is NOT a valid body parameter; the model is part of the endpoint URL.
     *  - 'anthropic_version' MUST be set to 'vertex-2023-10-16'.
     *
     * Supported models (pass as $modelId):
     *  - 'anthropic/claude-sonnet-4-6'
     *  - 'anthropic/claude-opus-4-6'
     *
     * @param array  $messages    Array of message objects: [['role'=>'user','content'=>'...']]
     * @param string $modelId     Model identifier, e.g. 'anthropic/claude-sonnet-4-6'
     * @param int    $maxTokens   Maximum tokens to generate (default 1024)
     * @param bool   $stream      Whether to stream the response (default false)
     * @param array  $extra       Any additional top-level payload parameters
     */
    public function claudeMessages(
        array  $messages,
        string $modelId    = 'anthropic/claude-sonnet-4-6',
        int    $maxTokens  = 1024,
        bool   $stream     = false,
        array  $extra      = []
    ): array {
        $payload = array_merge([
            'anthropic_version' => 'vertex-2023-10-16',
            'messages'          => $messages,
            'max_tokens'        => $maxTokens,
            'stream'            => $stream,
        ], $extra);

        return $this->request($modelId, 'rawPredict', $payload);
    }

    // -------------------------------------------------------------------------
    // Veo — Video generation (long-running operations)
    // -------------------------------------------------------------------------

    /**
     * Submit a video generation job using Veo.
     *
     * This is a long-running operation. The returned array contains a 'name' field
     * with the operation ID. Use getOperation() to poll for completion.
     *
     * Supported models (pass as $modelId):
     *  - 'google/veo-3.1-generate-001'
     *
     * @param string      $prompt            Text prompt describing the video.
     * @param string      $modelId           Model identifier (default: 'google/veo-3.1-generate-001')
     * @param int         $sampleCount       Number of videos to generate (1–2)
     * @param string|null $outputStorageUri  GCS bucket URI, e.g. 'gs://bucket/output/'. If null,
     *                                       base64-encoded video bytes are returned in the response.
     * @param array       $additionalParams  Extra parameters, e.g. ['generateAudio'=>true, 'durationSeconds'=>8]
     */
    public function generateVideo(
        string  $prompt,
        string  $modelId          = 'google/veo-3.1-generate-001',
        int     $sampleCount      = 1,
        ?string $outputStorageUri = null,
        array   $additionalParams = []
    ): array {
        $instances = [
            ['prompt' => $prompt]
        ];

        $parameters = array_merge([
            'sampleCount' => $sampleCount,
        ], $additionalParams);

        if ($outputStorageUri !== null) {
            $parameters['storageUri'] = $outputStorageUri;
        }

        $payload = [
            'instances'  => $instances,
            'parameters' => $parameters,
        ];

        return $this->request($modelId, 'predictLongRunning', $payload);
    }

    /**
     * Poll the status of a long-running operation (e.g. Veo video generation).
     *
     * @param string $operationName The full operation name returned by generateVideo(),
     *                              e.g. "projects/my-project/locations/us-central1/operations/123"
     */
    public function getOperation(string $operationName): array
    {
        if ($this->apiKey) {
            $url = sprintf(
                "%s/%s?key=%s",
                $this->baseUrl,
                ltrim($operationName, '/'),
                $this->apiKey
            );
            $headers = ['Content-Type: application/json'];
        } else {
            $url = sprintf(
                "%s/%s",
                $this->baseUrl,
                ltrim($operationName, '/')
            );
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->accessToken,
            ];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);

        if ($error) {
            throw new \RuntimeException("cURL Error: " . $error);
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMessage = $decoded['error']['message'] ?? 'Unknown API Error';
            throw new \RuntimeException("API Error ({$httpCode}): " . $errorMessage);
        }

        return $decoded;
    }

    // -------------------------------------------------------------------------
    // Generic predict — TTS, third-party models, raw payloads
    // -------------------------------------------------------------------------

    /**
     * Send a raw prediction/generation request.
     * Useful for TTS models, ElevenLabs, and any non-standard endpoint.
     *
     * @param array  $payload  The full request body
     * @param string $modelId  Model identifier, e.g. 'gemini-3.1-flash-tts-preview'
     *                         or 'elevenlabs/elevenlabs-tts-v2-5'
     * @param string $action   API action (default: 'predict')
     */
    public function predict(array $payload, string $modelId, string $action = 'predict'): array
    {
        return $this->request($modelId, $action, $payload);
    }

    // -------------------------------------------------------------------------
    // Core HTTP request handler
    // -------------------------------------------------------------------------

    /**
     * Build the endpoint URL and dispatch the HTTP request.
     *
     * Publisher resolution:
     *  - 'anthropic/claude-sonnet-4-6' → publisher=anthropic, model=claude-sonnet-4-6
     *  - 'google/veo-3.1-generate-001' → publisher=google,    model=veo-3.1-generate-001
     *  - 'gemini-3.1-flash-lite-preview' → publisher=google,  model=gemini-3.1-flash-lite-preview
     *
     * Special action handling:
     *  - 'rawPredict'        → maps to the 'rawPredict' endpoint (used by Anthropic Claude)
     *  - 'predictLongRunning' → maps to the 'predictLongRunning' endpoint (used by Veo)
     */
    private function request(string $modelId, string $action, array $payload): array
    {
        // Resolve publisher and model from the modelId string
        $publisher = 'google';
        $model     = $modelId;

        if (strpos($modelId, '/') !== false) {
            [$publisher, $model] = explode('/', $modelId, 2);
        }

        $headers = ['Content-Type: application/json'];

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

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);

        if ($error) {
            throw new \RuntimeException("cURL Error: " . $error);
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMessage = $decoded['error']['message'] ?? 'Unknown API Error';
            throw new \RuntimeException("API Error ({$httpCode}): " . $errorMessage);
        }

        return $decoded;
    }
}
