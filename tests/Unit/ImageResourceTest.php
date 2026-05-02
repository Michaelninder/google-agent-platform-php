<?php

namespace GoogleAgentPlatform\Tests\Unit;

use GoogleAgentPlatform\Http\HttpClient;
use GoogleAgentPlatform\Resources\ImageResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ImageResourceTest extends TestCase
{
    private function makeHttpMock(): MockObject&HttpClient
    {
        return $this->createMock(HttpClient::class);
    }

    private function fakePredictResponse(array $predictions): array
    {
        return ['predictions' => $predictions];
    }

    public function test_generate_returns_base64_entries(): void
    {
        $http = $this->makeHttpMock();
        $http->method('request')->willReturn($this->fakePredictResponse([
            ['mimeType' => 'image/png', 'bytesBase64Encoded' => \base64_encode('fake-png-bytes')],
        ]));

        $results = (new ImageResource($http))->generate('A red fox');

        $this->assertCount(1, $results);
        $this->assertSame('image/png', $results[0]['mimeType']);
        $this->assertSame(\base64_encode('fake-png-bytes'), $results[0]['base64']);
        $this->assertArrayNotHasKey('savedPath', $results[0]);
    }

    public function test_generate_saves_to_disk_when_output_dir_given(): void
    {
        $http = $this->makeHttpMock();
        $http->method('request')->willReturn($this->fakePredictResponse([
            ['mimeType' => 'image/png', 'bytesBase64Encoded' => \base64_encode('fake-png-bytes')],
        ]));

        $outputDir = \sys_get_temp_dir() . '/gap_test_images_' . \uniqid();

        try {
            $results = (new ImageResource($http))->generate('A fox', outputDir: $outputDir);

            $this->assertArrayHasKey('savedPath', $results[0]);
            $this->assertFileExists($results[0]['savedPath']);
            $this->assertSame('fake-png-bytes', \file_get_contents($results[0]['savedPath']));
        } finally {
            if (isset($results[0]['savedPath']) && \file_exists($results[0]['savedPath'])) {
                \unlink($results[0]['savedPath']);
            }
            if (\is_dir($outputDir)) {
                \rmdir($outputDir);
            }
        }
    }

    public function test_generate_sends_correct_payload(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('request')
            ->with(
                'imagen-3.0-generate-001',
                'predict',
                $this->callback(function (array $payload): bool {
                    return $payload['instances'][0]['prompt'] === 'A lighthouse'
                        && $payload['parameters']['sampleCount'] === 2
                        && $payload['parameters']['aspectRatio'] === '16:9';
                })
            )
            ->willReturn(['predictions' => []]);

        (new ImageResource($http))->generate('A lighthouse', sampleCount: 2, aspectRatio: '16:9');
    }

    public function test_generate_handles_empty_predictions(): void
    {
        $http = $this->makeHttpMock();
        $http->method('request')->willReturn(['predictions' => []]);

        $results = (new ImageResource($http))->generate('test');
        $this->assertSame([], $results);
    }
}
