<?php

namespace GoogleAgentPlatform\Resources;

use GoogleAgentPlatform\Http\HttpClient;

/**
 * Gemini text generation — generateContent and streamGenerateContent.
 */
class TextResource
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * Standard (non-streaming) content generation.
     * Defaults to 'gemini-3.1-flash-lite-preview'.
     *
     * @param array  $contents  Gemini-format contents array.
     * @param string $modelId   Model identifier.
     */
    public function generate(
        array  $contents,
        string $modelId = 'gemini-3.1-flash-lite-preview'
    ): array {
        return $this->http->request($modelId, 'generateContent', ['contents' => $contents]);
    }

    /**
     * Streamed content generation.
     * Defaults to 'gemini-3.1-flash-lite-preview'.
     *
     * @param array  $contents  Gemini-format contents array.
     * @param string $modelId   Model identifier.
     */
    public function stream(
        array  $contents,
        string $modelId = 'gemini-3.1-flash-lite-preview'
    ): array {
        return $this->http->request($modelId, 'streamGenerateContent', ['contents' => $contents]);
    }
}
