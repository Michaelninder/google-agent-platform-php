<?php

namespace GoogleAgentPlatform\Tests\Unit;

use GoogleAgentPlatform\Http\HttpClient;
use GoogleAgentPlatform\Resources\ClaudeResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ClaudeResourceTest extends TestCase
{
    private function makeHttpMock(): MockObject&HttpClient
    {
        return $this->createMock(HttpClient::class);
    }

    public function test_messages_sends_correct_payload(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('request')
            ->with(
                'anthropic/claude-sonnet-4-6',
                'rawPredict',
                $this->callback(function (array $payload): bool {
                    return $payload['anthropic_version'] === 'vertex-2023-10-16'
                        && $payload['max_tokens'] === 1024
                        && $payload['stream'] === false
                        && $payload['messages'][0]['role'] === 'user';
                })
            )
            ->willReturn(['content' => []]);

        $resource = new ClaudeResource($http);
        $resource->messages([['role' => 'user', 'content' => 'Hello']]);
    }

    public function test_messages_uses_custom_model_and_tokens(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('request')
            ->with('anthropic/claude-opus-4-6', 'rawPredict', $this->callback(fn($p) => $p['max_tokens'] === 2048))
            ->willReturn([]);

        (new ClaudeResource($http))->messages([], 'anthropic/claude-opus-4-6', 2048);
    }

    public function test_messages_streaming_calls_http_stream(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('stream')
            ->with(
                'anthropic/claude-sonnet-4-6',
                'rawPredict',
                $this->callback(fn($p) => $p['stream'] === true),
                $this->isCallable()
            );

        (new ClaudeResource($http))->messages([], stream: true);
    }

    public function test_messages_streaming_collects_chunks(): void
    {
        $http = $this->makeHttpMock();
        $http->method('stream')
            ->willReturnCallback(function ($m, $a, $p, callable $cb): void {
                $cb(\json_encode(['type' => 'content_block_delta', 'delta' => ['text' => 'Hi']]));
            });

        $chunks = (new ClaudeResource($http))->messages([], stream: true);

        $this->assertCount(1, $chunks);
        $this->assertSame('content_block_delta', $chunks[0]['type']);
    }

    public function test_extra_params_are_merged(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('request')
            ->with($this->anything(), $this->anything(), $this->callback(
                fn($p) => isset($p['system']) && $p['system'] === 'You are helpful.'
            ))
            ->willReturn([]);

        (new ClaudeResource($http))->messages([], extra: ['system' => 'You are helpful.']);
    }
}
