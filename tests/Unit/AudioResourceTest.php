<?php

namespace GoogleAgentPlatform\Tests\Unit;

use GoogleAgentPlatform\Http\HttpClient;
use GoogleAgentPlatform\Resources\AudioResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AudioResourceTest extends TestCase
{
    private function makeHttpMock(): MockObject&HttpClient
    {
        return $this->createMock(HttpClient::class);
    }

    public function test_synthesize_handles_json_envelope_response(): void
    {
        $audioBytes = 'fake-mp3-bytes';
        $envelope   = \json_encode([
            'predictions' => [
                ['mimeType' => 'audio/mp3', 'bytesBase64Encoded' => \base64_encode($audioBytes)],
            ],
        ]);

        $http = $this->makeHttpMock();
        $http->method('requestRaw')->willReturn($envelope);

        $result = (new AudioResource($http))->synthesize('Hello world');

        $this->assertSame('audio/mp3', $result['mimeType']);
        $this->assertSame($audioBytes, $result['bytes']);
        $this->assertArrayNotHasKey('savedPath', $result);
    }

    public function test_synthesize_handles_raw_binary_response(): void
    {
        $rawBytes = "\xFF\xFB\x90\x00fake-mp3-data";

        $http = $this->makeHttpMock();
        $http->method('requestRaw')->willReturn($rawBytes);

        $result = (new AudioResource($http))->synthesize('Hello');

        $this->assertSame($rawBytes, $result['bytes']);
    }

    public function test_synthesize_saves_to_file(): void
    {
        $audioBytes = 'fake-audio-data';
        $envelope   = \json_encode([
            'predictions' => [
                ['mimeType' => 'audio/wav', 'bytesBase64Encoded' => \base64_encode($audioBytes)],
            ],
        ]);

        $http = $this->makeHttpMock();
        $http->method('requestRaw')->willReturn($envelope);

        $outputFile = \sys_get_temp_dir() . '/gap_test_' . \uniqid() . '.wav';

        try {
            $result = (new AudioResource($http))->synthesize('Hello', outputFile: $outputFile);

            $this->assertArrayHasKey('savedPath', $result);
            $this->assertFileExists($outputFile);
            $this->assertSame($audioBytes, \file_get_contents($outputFile));
            $this->assertSame('audio/wav', $result['mimeType']);
        } finally {
            if (\file_exists($outputFile)) {
                \unlink($outputFile);
            }
        }
    }

    public function test_synthesize_sends_voice_config_and_style_prompt(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('requestRaw')
            ->with(
                'gemini-3.1-flash-tts-preview',
                'predict',
                $this->callback(function (array $payload): bool {
                    return $payload['text'] === 'Hello'
                        && isset($payload['voiceConfig'])
                        && $payload['stylePrompt'] === 'Speak slowly.';
                })
            )
            ->willReturn(\json_encode(['predictions' => [['mimeType' => 'audio/mp3', 'bytesBase64Encoded' => '']]]));

        (new AudioResource($http))->synthesize(
            'Hello',
            voiceConfig: ['prebuiltVoiceConfig' => ['voiceName' => 'en-US-Standard-A']],
            stylePrompt: 'Speak slowly.'
        );
    }
}
