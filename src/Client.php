<?php

namespace GoogleAgentPlatform;

use GoogleAgentPlatform\Http\HttpClient;
use GoogleAgentPlatform\Resources\AudioResource;
use GoogleAgentPlatform\Resources\ClaudeResource;
use GoogleAgentPlatform\Resources\FileResource;
use GoogleAgentPlatform\Resources\ImageResource;
use GoogleAgentPlatform\Resources\TextResource;
use GoogleAgentPlatform\Resources\VideoResource;

/**
 * Google Agent Platform PHP SDK — main entry point.
 *
 * Supports Express Mode (API key) and Google Cloud Mode (OAuth Bearer token).
 *
 * Recommended locations per model family:
 *  - Gemini text / TTS : 'global'
 *  - Anthropic Claude  : 'us-east5'
 *  - Veo video         : 'us-central1'
 *  - Imagen images     : 'us-central1'
 *
 * Usage:
 *   $client = new Client(['api_key' => 'YOUR_KEY']);
 *
 *   // Resource-style (recommended)
 *   $client->text->generate([...]);
 *   $client->images->generate('A red fox...');
 *   $client->audio->synthesize('Hello world');
 *   $client->video->generate('A timelapse...');
 *   $client->claude->messages([...]);
 *   $client->files->uploadFile('/tmp/photo.jpg');
 *   $client->files->withFile('/tmp/photo.jpg', 'What is this?');
 *   $client->files->deleteFile('files/abc123');
 *
 *   // Legacy flat API (fully backward-compatible)
 *   $client->generateContent([...]);
 *   $client->generateImage('A red fox...');
 *   $client->synthesizeSpeech('Hello world');
 *   $client->generateVideo('A timelapse...');
 *   $client->claudeMessages([...]);
 */
class Client
{
    // -------------------------------------------------------------------------
    // Resource accessors (recommended API)
    // -------------------------------------------------------------------------

    /** Gemini text generation */
    public readonly TextResource   $text;

    /** Imagen image generation */
    public readonly ImageResource  $images;

    /** Text-to-Speech synthesis */
    public readonly AudioResource  $audio;

    /** Veo video generation */
    public readonly VideoResource  $video;

    /** Anthropic Claude models */
    public readonly ClaudeResource $claude;

    /** File handling — local embed (inlineData) and File API upload */
    public readonly FileResource   $files;

    // Keep a direct reference so predict() doesn't need reflection
    private readonly HttpClient $http;

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    /**
     * @param array{
     *   api_key?:      string,
     *   access_token?: string,
     *   project_id?:   string,
     *   location?:     string,
     * } $config
     */
    public function __construct(array $config)
    {
        $apiKey      = $config['api_key']      ?? null;
        $accessToken = $config['access_token'] ?? null;
        $projectId   = $config['project_id']   ?? null;
        $location    = $config['location']     ?? 'global';

        if (!$apiKey && (!$accessToken || !$projectId)) {
            throw new \InvalidArgumentException(
                "You must provide either an 'api_key' OR both 'access_token' and 'project_id'."
            );
        }

        $this->http   = new HttpClient($apiKey, $accessToken, $projectId, $location);

        $this->text   = new TextResource($this->http);
        $this->images = new ImageResource($this->http);
        $this->audio  = new AudioResource($this->http);
        $this->video  = new VideoResource($this->http);
        $this->claude = new ClaudeResource($this->http);
        $this->files  = new FileResource($this->http);
    }

    // -------------------------------------------------------------------------
    // Legacy flat API — fully backward-compatible
    // -------------------------------------------------------------------------

    /**
     * Standard (non-streaming) content generation.
     *
     * @param string|null $systemInstruction  Optional system prompt.
     * @param array       $generationConfig   Optional params: temperature, maxOutputTokens, etc.
     * @see TextResource::generate()
     */
    public function generateContent(
        array   $contents,
        string  $modelId           = 'gemini-3.1-flash-lite-preview',
        ?string $systemInstruction = null,
        array   $generationConfig  = []
    ): array {
        return $this->text->generate($contents, $modelId, $systemInstruction, $generationConfig);
    }

    /**
     * Streamed content generation.
     *
     * @param callable|null $onChunk           fn(array $chunk): void — called per chunk.
     *                                          If null, all chunks are collected and returned.
     * @param string|null   $systemInstruction  Optional system prompt.
     * @param array         $generationConfig   Optional generation parameters.
     * @see TextResource::stream()
     */
    public function streamGenerateContent(
        array     $contents,
        string    $modelId           = 'gemini-3.1-flash-lite-preview',
        ?callable $onChunk           = null,
        ?string   $systemInstruction = null,
        array     $generationConfig  = []
    ): array {
        return $this->text->stream($contents, $modelId, $onChunk, $systemInstruction, $generationConfig);
    }

    /**
     * Generate images using Imagen 3.
     * @see ImageResource::generate()
     */
    public function generateImage(
        string  $prompt,
        string  $modelId          = 'imagen-3.0-generate-001',
        int     $sampleCount      = 1,
        string  $aspectRatio      = '1:1',
        ?string $outputDir        = null,
        array   $additionalParams = []
    ): array {
        return $this->images->generate($prompt, $modelId, $sampleCount, $aspectRatio, $outputDir, $additionalParams);
    }

    /**
     * Synthesize speech from text.
     * @see AudioResource::synthesize()
     */
    public function synthesizeSpeech(
        string  $text,
        string  $modelId     = 'gemini-3.1-flash-tts-preview',
        array   $voiceConfig = [],
        ?string $stylePrompt = null,
        ?string $outputFile  = null,
        array   $extra       = []
    ): array {
        return $this->audio->synthesize($text, $modelId, $voiceConfig, $stylePrompt, $outputFile, $extra);
    }

    /**
     * Submit a Veo video generation job.
     * @see VideoResource::generate()
     */
    public function generateVideo(
        string  $prompt,
        string  $modelId          = 'google/veo-3.1-generate-001',
        int     $sampleCount      = 1,
        ?string $outputStorageUri = null,
        array   $additionalParams = []
    ): array {
        return $this->video->generate($prompt, $modelId, $sampleCount, $outputStorageUri, $additionalParams);
    }

    /**
     * Poll a long-running operation.
     * @see VideoResource::getOperation()
     */
    public function getOperation(string $operationName): array
    {
        return $this->video->getOperation($operationName);
    }

    /**
     * Send a Claude messages request.
     * @see ClaudeResource::messages()
     */
    public function claudeMessages(
        array  $messages,
        string $modelId   = 'anthropic/claude-sonnet-4-6',
        int    $maxTokens = 1024,
        bool   $stream    = false,
        array  $extra     = []
    ): array {
        return $this->claude->messages($messages, $modelId, $maxTokens, $stream, $extra);
    }

    /**
     * Send a raw prediction request.
     */
    public function predict(array $payload, string $modelId, string $action = 'predict'): array
    {
        return $this->http->request($modelId, $action, $payload);
    }
}
