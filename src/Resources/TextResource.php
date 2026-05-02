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
     * @param array       $contents          Gemini-format contents array.
     * @param string      $modelId           Model identifier.
     * @param string|null $systemInstruction Optional system prompt text.
     * @param array       $generationConfig  Optional generation parameters, e.g.:
     *                                       ['temperature'=>0.7, 'maxOutputTokens'=>1024,
     *                                        'topP'=>0.9, 'responseMimeType'=>'application/json']
     */
    public function generate(
        array   $contents,
        string  $modelId           = 'gemini-3.1-flash-lite-preview',
        ?string $systemInstruction = null,
        array   $generationConfig  = []
    ): array {
        $payload = ['contents' => $contents];

        if ($systemInstruction !== null) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemInstruction]],
            ];
        }

        if (!empty($generationConfig)) {
            $payload['generationConfig'] = $generationConfig;
        }

        return $this->http->request($modelId, 'generateContent', $payload);
    }

    /**
     * Streamed content generation.
     * Defaults to 'gemini-3.1-flash-lite-preview'.
     *
     * Each chunk is a partial JSON object from the API. The callback receives
     * each decoded chunk as an array as it arrives.
     *
     * @param array         $contents          Gemini-format contents array.
     * @param string        $modelId           Model identifier.
     * @param callable|null $onChunk           fn(array $chunk): void — called for each chunk.
     *                                         If null, all chunks are collected and returned.
     * @param string|null   $systemInstruction Optional system prompt text.
     * @param array         $generationConfig  Optional generation parameters.
     *
     * @return array  All collected chunks (only populated when $onChunk is null).
     */
    public function stream(
        array     $contents,
        string    $modelId           = 'gemini-3.1-flash-lite-preview',
        ?callable $onChunk           = null,
        ?string   $systemInstruction = null,
        array     $generationConfig  = []
    ): array {
        $payload = ['contents' => $contents];

        if ($systemInstruction !== null) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemInstruction]],
            ];
        }

        if (!empty($generationConfig)) {
            $payload['generationConfig'] = $generationConfig;
        }

        $chunks   = [];
        $callback = $onChunk ?? function (array $chunk) use (&$chunks): void {
            $chunks[] = $chunk;
        };

        $this->http->stream(
            $modelId,
            'streamGenerateContent',
            $payload,
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
