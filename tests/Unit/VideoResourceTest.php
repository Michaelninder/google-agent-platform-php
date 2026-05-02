<?php

namespace GoogleAgentPlatform\Tests\Unit;

use GoogleAgentPlatform\Http\HttpClient;
use GoogleAgentPlatform\Resources\VideoResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class VideoResourceTest extends TestCase
{
    private function makeHttpMock(): MockObject&HttpClient
    {
        return $this->createMock(HttpClient::class);
    }

    public function test_generate_sends_correct_payload(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('request')
            ->with(
                'google/veo-3.1-generate-001',
                'predictLongRunning',
                $this->callback(function (array $payload): bool {
                    return $payload['instances'][0]['prompt'] === 'A timelapse'
                        && $payload['parameters']['sampleCount'] === 1;
                })
            )
            ->willReturn(['name' => 'projects/p/locations/l/operations/123']);

        $result = (new VideoResource($http))->generate('A timelapse');
        $this->assertSame('projects/p/locations/l/operations/123', $result['name']);
    }

    public function test_generate_includes_storage_uri_when_provided(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('request')
            ->with($this->anything(), $this->anything(), $this->callback(
                fn($p) => $p['parameters']['storageUri'] === 'gs://my-bucket/output/'
            ))
            ->willReturn([]);

        (new VideoResource($http))->generate('test', outputStorageUri: 'gs://my-bucket/output/');
    }

    public function test_generate_omits_storage_uri_when_null(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('request')
            ->with($this->anything(), $this->anything(), $this->callback(
                fn($p) => !isset($p['parameters']['storageUri'])
            ))
            ->willReturn([]);

        (new VideoResource($http))->generate('test');
    }

    public function test_get_operation_calls_http_get(): void
    {
        $http = $this->makeHttpMock();
        $http->method('getBaseUrl')->willReturn('https://aiplatform.googleapis.com/v1');
        $http->expects($this->once())
            ->method('get')
            ->with('https://aiplatform.googleapis.com/v1/projects/p/locations/l/operations/123')
            ->willReturn(['done' => true, 'response' => []]);

        $result = (new VideoResource($http))->getOperation('projects/p/locations/l/operations/123');
        $this->assertTrue($result['done']);
    }

    public function test_get_operation_strips_leading_slash(): void
    {
        $http = $this->makeHttpMock();
        $http->method('getBaseUrl')->willReturn('https://aiplatform.googleapis.com/v1');
        $http->expects($this->once())
            ->method('get')
            ->with('https://aiplatform.googleapis.com/v1/projects/p/operations/456')
            ->willReturn([]);

        (new VideoResource($http))->getOperation('/projects/p/operations/456');
    }
}
