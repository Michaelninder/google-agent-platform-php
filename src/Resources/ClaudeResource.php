<?php

namespace GoogleAgentPlatform\Resources;

use GoogleAgentPlatform\Http\HttpClient;

/**
 * Anthropic Claude models hosted on Google Agent Platform.
 *
 * Supported models:
 *  - 'anthropic/claude-sonnet-4-6'
 *  - 'anthropic/claude-opus-4-6'
 *
 * Key differences from the direct Anthropic API:
 *  - 'model' is NOT a valid body parameter — the model is part of the endpoint URL.
 *  - 'anthropic_version' MUST be set to 'vertex-2023-10-16'.
 *
 * Requires Cloud Mode (project_id + access_token). Recommended location: 'us-east5'.
 */
class ClaudeResource
{
    public function __construct(private readonly HttpClient $http) {}

    /**
     * Send a messages request to a Claude model.
     *
     * @param array    $messages   Message objects: [['role' => 'user', 'content' => '...']]
     * @param string   $modelId    Model identifier, e.g. 'anthropic/claude-sonnet-4-6'.
     * @param int      $maxTokens  Maximum tokens to generate (default 1024).
     * @param bool     $stream     Whether to stream the response (default false).
     * @param array    $extra      Any additional top-level payload parameters.
     * @param callable|null $onChunk  fn(array $chunk): void — required when $stream is true.
     *                                If null and $stream is true, chunks are collected and returned.
     *
     * @return array  Full response (non-streaming) or collected chunks (streaming).
     */
    public function messages(
        array    $messages,
        string   $modelId   = 'anthropic/claude-sonnet-4-6',
        int      $maxTokens = 1024,
        bool     $stream    = false,
        array    $extra     = [],
        ?callable $onChunk  = null
    ): array {
        $payload = \array_merge([
            'anthropic_version' => 'vertex-2023-10-16',
            'messages'          => $messages,
            'max_tokens'        => $maxTokens,
            'stream'            => $stream,
        ], $extra);

        if ($stream) {
            $chunks   = [];
            $callback = $onChunk ?? function (array $chunk) use (&$chunks): void {
                $chunks[] = $chunk;
            };

            $this->http->stream(
                $modelId,
                'rawPredict',
                $payload,
                function (string $line) use ($callback): void {
                    // Claude SSE lines are prefixed with "event: ..." or "data: ..."
                    // We only care about "data:" lines
                    $decoded = \json_decode($line, true);
                    if (\is_array($decoded)) {
                        $callback($decoded);
                    }
                }
            );

            return $chunks;
        }

        return $this->http->request($modelId, 'rawPredict', $payload);
    }
}
