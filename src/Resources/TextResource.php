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
     * Each chunk is a partial JSON object from the API. The callback receives
     * each decoded chunk as an array as it arrives.
     *
     * @param array    $contents  Gemini-format contents array.
     * @param string   $modelId   Model identifier.
     * @param callable $onChunk   fn(array $chunk): void — called for each streamed chunk.
     *                            If null, all chunks are collected and returned as an array.
     * @return array   All collected chunks (only populated when $onChunk is null).
     */
    public function stream(
        array    $contents,
        string   $modelId  = 'gemini-3.1-flash-lite-preview',
        ?callable $onChunk = null
    ): array {
        $chunks   = [];
        $callback = $onChunk ?? function (array $chunk) use (&$chunks): void {
            $chunks[] = $chunk;
        };

        $this->http->stream(
            $modelId,
            'streamGenerateContent',
            ['contents' => $contents],
            function (string $line) use ($callback): void {
                $decoded = \json_decode($line, true);
                if (\is_array($decoded)) {
                    $callback($decoded);
                }
            }
        );

        return $chunks;
    }
}
