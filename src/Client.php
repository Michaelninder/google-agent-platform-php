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
     * 'location' defaults to 'global'.
     *
     * Recommended locations per model family:
     *  - Gemini text/TTS : 'global'
     *  - Anthropic Claude : 'us-east5'
     *  - Veo video        : 'us-central1'
     *  - Imagen images    : 'us-central1'
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
    // Gemini — Standard text generation
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
    // Imagen — Image generation
    // -------------------------------------------------------------------------

    /**
     * Generate images using Imagen 3 (or compatible models).
     *
     * The API returns base64-encoded PNG bytes. This method decodes them and
     * optionally saves each image to disk.
     *
     * Supported models (pass as $modelId):
     *  - 'imagen-3.0-generate-001'  (default, highest quality)
     *  - 'imagen-3.0-fast-generate-001'  (faster, lower cost)
     *
     * @param string      $prompt          Text prompt describing the image.
     * @param string      $modelId         Model identifier.
     * @param int         $sampleCount     Number of images to generate (1–4).
     * @param string      $aspectRatio     e.g. '1:1', '16:9', '9:16', '4:3', '3:4'
     * @param string|null $outputDir       Directory to save images to. If null, images are
     *                                     returned as base64 strings in the result array.
     * @param array       $additionalParams Extra parameters (negativePrompt, personGeneration, etc.)
     *
     * @return array  Each element: ['mimeType'=>'image/png', 'base64'=>'...', 'savedPath'=>'...']
     *                'savedPath' is only present when $outputDir is provided.
     */
    public function generateImage(
        string  $prompt,
        string  $modelId          = 'imagen-3.0-generate-001',
        int     $sampleCount      = 1,
        string  $aspectRatio      = '1:1',
        ?string $outputDir        = null,
        array   $additionalParams = []
    ): array {
        $parameters = \array_merge([
            'sampleCount' => $sampleCount,
            'aspectRatio' => $aspectRatio,
        ], $additionalParams);

        $payload = [
            'instances'  => [['prompt' => $prompt]],
            'parameters' => $parameters,
        ];

        $response = $this->request($modelId, 'predict', $payload);

        // Normalize the predictions array
        $predictions = $response['predictions'] ?? [];
        $results     = [];

        foreach ($predictions as $prediction) {
            $mimeType = $prediction['mimeType'] ?? 'image/png';
            $b64      = $prediction['bytesBase64Encoded'] ?? '';

            $entry = [
                'mimeType' => $mimeType,
                'base64'   => $b64,
            ];

            if ($outputDir !== null && $b64 !== '') {
                $ext       = $this->mimeToExtension($mimeType);
                $filename  = $outputDir . \DIRECTORY_SEPARATOR . 'imagen_' . \uniqid() . '.' . $ext;
                $bytes     = \base64_decode($b64);

                if (!\is_dir($outputDir)) {
                    \mkdir($outputDir, 0755, true);
                }

                \file_put_contents($filename, $bytes);
                $entry['savedPath'] = $filename;
            }

            $results[] = $entry;
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // TTS — Text-to-Speech (Gemini TTS, ElevenLabs, etc.)
    // -------------------------------------------------------------------------

    /**
     * Synthesize speech from text using a TTS model.
     *
     * The API may return either:
     *  a) A JSON envelope with base64-encoded audio (Gemini TTS / predict endpoint)
     *  b) Raw binary audio bytes (some rawPredict endpoints)
     *
     * This method handles both cases and optionally saves the audio to a file.
     *
     * Supported models (pass as $modelId):
     *  - 'gemini-3.1-flash-tts-preview'  (default — low latency, style control)
     *  - 'gemini-2.5-pro-tts'
     *  - 'gemini-2.5-flash-tts'
     *  - 'elevenlabs/elevenlabs-tts-v2-5'
     *
     * @param string      $text         The text to synthesize.
     * @param string      $modelId      Model identifier.
     * @param array       $voiceConfig  Voice configuration, e.g.:
     *                                  ['prebuiltVoiceConfig' => ['voiceName' => 'en-US-Standard-A']]
     * @param string|null $stylePrompt  Natural language style instruction, e.g.
     *                                  'Speak in a calm, friendly tone with a slight British accent.'
     * @param string|null $outputFile   Full path to save the audio file (e.g. '/tmp/speech.mp3').
     *                                  If null, raw audio bytes are returned in the result.
     * @param array       $extra        Any additional payload parameters.
     *
     * @return array  ['mimeType'=>'audio/mp3', 'bytes'=>'<raw binary>', 'savedPath'=>'...']
     *                'savedPath' is only present when $outputFile is provided.
     */
    public function synthesizeSpeech(
        string  $text,
        string  $modelId     = 'gemini-3.1-flash-tts-preview',
        array   $voiceConfig = [],
        ?string $stylePrompt = null,
        ?string $outputFile  = null,
        array   $extra       = []
    ): array {
        $payload = \array_merge([
            'text' => $text,
        ], $extra);

        if (!empty($voiceConfig)) {
            $payload['voiceConfig'] = $voiceConfig;
        }

        if ($stylePrompt !== null) {
            $payload['stylePrompt'] = $stylePrompt;
        }

        // Fetch raw bytes — TTS responses are binary or base64-wrapped JSON
        $raw      = $this->requestRaw($modelId, 'predict', $payload);
        $mimeType = 'audio/mp3';

        // Try to decode as JSON first (some endpoints wrap audio in a JSON envelope)
        $decoded = \json_decode($raw, true);

        if (\json_last_error() === JSON_ERROR_NONE && isset($decoded['predictions'])) {
            // JSON envelope: extract base64 audio from predictions
            $prediction = $decoded['predictions'][0] ?? [];
            $b64        = $prediction['bytesBase64Encoded'] ?? ($prediction['audioContent'] ?? '');
            $mimeType   = $prediction['mimeType'] ?? 'audio/mp3';
            $audioBytes = \base64_decode($b64);
        } else {
            // Raw binary response
            $audioBytes = $raw;
        }

        $result = [
            'mimeType' => $mimeType,
            'bytes'    => $audioBytes,
        ];

        if ($outputFile !== null) {
            $dir = \dirname($outputFile);
            if (!\is_dir($dir)) {
                \mkdir($dir, 0755, true);
            }
            \file_put_contents($outputFile, $audioBytes);
            $result['savedPath'] = $outputFile;
        }

        return $result;
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
     * @param array  $messages   Array of message objects: [['role'=>'user','content'=>'...']]
     * @param string $modelId    Model identifier.
     * @param int    $maxTokens  Maximum tokens to generate (default 1024).
     * @param bool   $stream     Whether to stream the response (default false).
     * @param array  $extra      Any additional top-level payload parameters.
     */
    public function claudeMessages(
        array  $messages,
        string $modelId   = 'anthropic/claude-sonnet-4-6',
        int    $maxTokens = 1024,
        bool   $stream    = false,
        array  $extra     = []
    ): array {
        $payload = \array_merge([
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
     * Returns a long-running operation. Use getOperation() to poll for completion.
     *
     * Supported models (pass as $modelId):
     *  - 'google/veo-3.1-generate-001'
     *
     * @param string      $prompt            Text prompt describing the video.
     * @param string      $modelId           Model identifier.
     * @param int         $sampleCount       Number of videos to generate (1–2).
     * @param string|null $outputStorageUri  GCS bucket URI, e.g. 'gs://bucket/output/'.
     *                                       If null, base64-encoded video bytes are returned.
     * @param array       $additionalParams  Extra parameters, e.g. ['generateAudio'=>true].
     */
    public function generateVideo(
        string  $prompt,
        string  $modelId          = 'google/veo-3.1-generate-001',
        int     $sampleCount      = 1,
        ?string $outputStorageUri = null,
        array   $additionalParams = []
    ): array {
        $parameters = \array_merge([
            'sampleCount' => $sampleCount,
        ], $additionalParams);

        if ($outputStorageUri !== null) {
            $parameters['storageUri'] = $outputStorageUri;
        }

        $payload = [
            'instances'  => [['prompt' => $prompt]],
            'parameters' => $parameters,
        ];

        return $this->request($modelId, 'predictLongRunning', $payload);
    }

    /**
     * Poll the status of a long-running operation (e.g. Veo video generation).
     *
     * @param string $operationName Full operation name returned by generateVideo(),
     *                              e.g. "projects/my-project/locations/us-central1/operations/123"
     */
    public function getOperation(string $operationName): array
    {
        $headers = ['Content-Type: application/json'];

        if ($this->apiKey) {
            $url = \sprintf(
                "%s/%s?key=%s",
                $this->baseUrl,
                \ltrim($operationName, '/'),
                $this->apiKey
            );
        } else {
            $url       = \sprintf("%s/%s", $this->baseUrl, \ltrim($operationName, '/'));
            $headers[] = "Authorization: Bearer {$this->accessToken}";
        }

        $ch = \curl_init();
        \curl_setopt($ch, CURLOPT_URL, $url);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = \curl_exec($ch);
        $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = \curl_error($ch);

        if ($error) {
            throw new \RuntimeException("cURL Error: {$error}");
        }

        $decoded = \json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMessage = $decoded['error']['message'] ?? 'Unknown API Error';
            throw new \RuntimeException("API Error ({$httpCode}): {$errorMessage}");
        }

        return $decoded;
    }

    // -------------------------------------------------------------------------
    // Generic predict — raw payloads
    // -------------------------------------------------------------------------

    /**
     * Send a raw prediction request. Useful for non-standard endpoints.
     *
     * @param array  $payload  The full request body.
     * @param string $modelId  Model identifier.
     * @param string $action   API action (default: 'predict').
     */
    public function predict(array $payload, string $modelId, string $action = 'predict'): array
    {
        return $this->request($modelId, $action, $payload);
    }

    // -------------------------------------------------------------------------
    // Core HTTP request handlers
    // -------------------------------------------------------------------------

    /**
     * Dispatch a request and return a decoded JSON array.
     * Use this for all text/structured responses.
     */
    private function request(string $modelId, string $action, array $payload): array
    {
        $raw     = $this->requestRaw($modelId, $action, $payload);
        $decoded = \json_decode($raw, true);

        if (\json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException(
                'Failed to decode API response as JSON. '
                . 'If this endpoint returns binary data, use requestRaw() directly.'
            );
        }

        if (isset($decoded['error'])) {
            $errorMessage = $decoded['error']['message'] ?? 'Unknown API Error';
            throw new \RuntimeException("API Error: {$errorMessage}");
        }

        return $decoded;
    }

    /**
     * Dispatch a request and return the raw response body as a string.
     * Used internally for binary responses (images, audio) and by request().
     */
    private function requestRaw(string $modelId, string $action, array $payload): string
    {
        // Resolve publisher and model from the modelId string
        // e.g. 'anthropic/claude-sonnet-4-6' → publisher=anthropic, model=claude-sonnet-4-6
        // e.g. 'gemini-3.1-flash-lite-preview' → publisher=google, model=gemini-3.1-flash-lite-preview
        $publisher = 'google';
        $model     = $modelId;

        if (\strpos($modelId, '/') !== false) {
            [$publisher, $model] = \explode('/', $modelId, 2);
        }

        $headers = ['Content-Type: application/json'];

        if ($this->apiKey) {
            $url = \sprintf(
                "%s/publishers/%s/models/%s:%s?key=%s",
                $this->baseUrl,
                $publisher,
                $model,
                $action,
                $this->apiKey
            );
        } else {
            $url = \sprintf(
                "%s/projects/%s/locations/%s/publishers/%s/models/%s:%s",
                $this->baseUrl,
                $this->projectId,
                $this->location,
                $publisher,
                $model,
                $action
            );
            $headers[] = "Authorization: Bearer {$this->accessToken}";
        }

        $ch = \curl_init();
        \curl_setopt($ch, CURLOPT_URL, $url);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, CURLOPT_POST, true);
        \curl_setopt($ch, CURLOPT_POSTFIELDS, \json_encode($payload));
        \curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = \curl_exec($ch);
        $httpCode = \curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = \curl_error($ch);

        if ($error) {
            throw new \RuntimeException("cURL Error: {$error}");
        }

        // For non-JSON binary responses we can only detect HTTP errors via status code.
        // Attempt a quick JSON decode to surface structured error messages.
        if ($httpCode >= 400) {
            $decoded      = \json_decode($response, true);
            $errorMessage = $decoded['error']['message'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException("API Error ({$httpCode}): {$errorMessage}");
        }

        return $response;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Map a MIME type to a file extension.
     */
    private function mimeToExtension(string $mimeType): string
    {
        $map = [
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            'audio/mp3'  => 'mp3',
            'audio/mpeg' => 'mp3',
            'audio/wav'  => 'wav',
            'audio/ogg'  => 'ogg',
            'audio/flac' => 'flac',
        ];

        return $map[$mimeType] ?? 'bin';
    }
}
