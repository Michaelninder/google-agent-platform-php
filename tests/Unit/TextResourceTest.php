<?php

namespace GoogleAgentPlatform\Tests\Unit;

use GoogleAgentPlatform\Http\HttpClient;
use GoogleAgentPlatform\Resources\TextResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TextResourceTest extends TestCase
{
    private function makeHttpMock(): MockObject&HttpClient
    {
        return $this->createMock(HttpClient::class);
    }

    public function test_generate_calls_generate_content_action(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('request')
            ->with(
                'gemini-3.1-flash-lite-preview',
                'generateContent',
                ['contents' => [['role' => 'user', 'parts' => [['text' => 'Hello']]]]]
            )
            ->willReturn(['candidates' => []]);

        $resource = new TextResource($http);
        $result   = $resource->generate([['role' => 'user', 'parts' => [['text' => 'Hello']]]]);

        $this->assertSame(['candidates' => []], $result);
    }

    public function test_generate_uses_custom_model(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('request')
            ->with('gemini-3.1-pro-preview', 'generateContent', $this->anything())
            ->willReturn([]);

        (new TextResource($http))->generate([], 'gemini-3.1-pro-preview');
    }

    public function test_generate_includes_system_instruction(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('request')
            ->with($this->anything(), $this->anything(), $this->callback(function (array $payload): bool {
                return isset($payload['systemInstruction']['parts'][0]['text'])
                    && $payload['systemInstruction']['parts'][0]['text'] === 'You are a helpful assistant.';
            }))
            ->willReturn([]);

        (new TextResource($http))->generate([], systemInstruction: 'You are a helpful assistant.');
    }

    public function test_generate_includes_generation_config(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('request')
            ->with($this->anything(), $this->anything(), $this->callback(function (array $payload): bool {
                return isset($payload['generationConfig']['temperature'])
                    && $payload['generationConfig']['temperature'] === 0.5
                    && $payload['generationConfig']['maxOutputTokens'] === 512;
            }))
            ->willReturn([]);

        (new TextResource($http))->generate([], generationConfig: ['temperature' => 0.5, 'maxOutputTokens' => 512]);
    }

    public function test_generate_omits_system_instruction_when_null(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('request')
            ->with($this->anything(), $this->anything(), $this->callback(
                fn($p) => !isset($p['systemInstruction'])
            ))
            ->willReturn([]);

        (new TextResource($http))->generate([]);
    }

    public function test_stream_calls_http_stream(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('stream')
            ->with(
                'gemini-3.1-flash-lite-preview',
                'streamGenerateContent',
                $this->anything(),
                $this->isCallable()
            );

        $resource = new TextResource($http);
        $resource->stream([['role' => 'user', 'parts' => [['text' => 'Hi']]]]);
    }

    public function test_stream_collects_chunks_when_no_callback(): void
    {
        $http = $this->makeHttpMock();
        $http->method('stream')
            ->willReturnCallback(function ($modelId, $action, $payload, callable $onChunk): void {
                // Simulate two chunks arriving
                $onChunk(\json_encode(['candidates' => [['text' => 'Hello']]]));
                $onChunk(\json_encode(['candidates' => [['text' => ' world']]]));
            });

        $chunks = (new TextResource($http))->stream([]);

        $this->assertCount(2, $chunks);
        $this->assertSame('Hello', $chunks[0]['candidates'][0]['text']);
        $this->assertSame(' world', $chunks[1]['candidates'][0]['text']);
    }

    public function test_stream_calls_provided_callback(): void
    {
        $http = $this->makeHttpMock();
        $http->method('stream')
            ->willReturnCallback(function ($modelId, $action, $payload, callable $onChunk): void {
                $onChunk(\json_encode(['text' => 'chunk1']));
            });

        $received = [];
        (new TextResource($http))->stream([], 'gemini-3.1-flash-lite-preview', function (array $chunk) use (&$received): void {
            $received[] = $chunk;
        });

        $this->assertCount(1, $received);
        $this->assertSame('chunk1', $received[0]['text']);
    }
}
