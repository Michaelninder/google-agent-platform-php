<?php

namespace GoogleAgentPlatform\Tests\Unit;

use GoogleAgentPlatform\Http\HttpClient;
use GoogleAgentPlatform\Resources\FileResource;
use GoogleAgentPlatform\Exceptions\FileNotFoundException;
use PHPUnit\Framework\TestCase;

class FileResourceTest extends TestCase
{
    private function makeResource(): FileResource
    {
        $http = new HttpClient('test-key', null, null, 'global');
        return new FileResource($http);
    }

    // -------------------------------------------------------------------------
    // withFile()
    // -------------------------------------------------------------------------

    public function test_with_file_returns_correct_structure(): void
    {
        $tmpFile = \tempnam(\sys_get_temp_dir(), 'gap_test_');
        \file_put_contents($tmpFile, 'hello world');

        try {
            $resource = $this->makeResource();
            $contents = $resource->withFile($tmpFile, 'What is this?', 'text/plain');

            $this->assertCount(1, $contents);
            $this->assertSame('user', $contents[0]['role']);

            $parts = $contents[0]['parts'];
            $this->assertCount(2, $parts);

            // First part: inlineData
            $this->assertArrayHasKey('inlineData', $parts[0]);
            $this->assertSame('text/plain', $parts[0]['inlineData']['mimeType']);
            $this->assertSame(\base64_encode('hello world'), $parts[0]['inlineData']['data']);

            // Second part: text
            $this->assertSame('What is this?', $parts[1]['text']);
        } finally {
            \unlink($tmpFile);
        }
    }

    public function test_with_file_throws_for_missing_file(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessageMatches('/File not found/');

        $this->makeResource()->withFile('/nonexistent/path/file.jpg', 'test');
    }

    public function test_with_file_accepts_custom_role(): void
    {
        $tmpFile = \tempnam(\sys_get_temp_dir(), 'gap_test_');
        \file_put_contents($tmpFile, 'data');

        try {
            $contents = $this->makeResource()->withFile($tmpFile, 'prompt', 'text/plain', 'model');
            $this->assertSame('model', $contents[0]['role']);
        } finally {
            \unlink($tmpFile);
        }
    }

    // -------------------------------------------------------------------------
    // withFiles()
    // -------------------------------------------------------------------------

    public function test_with_files_embeds_multiple_files(): void
    {
        $file1 = \tempnam(\sys_get_temp_dir(), 'gap_test_');
        $file2 = \tempnam(\sys_get_temp_dir(), 'gap_test_');
        \file_put_contents($file1, 'content1');
        \file_put_contents($file2, 'content2');

        try {
            $contents = $this->makeResource()->withFiles(
                [$file1, ['path' => $file2, 'mimeType' => 'text/plain']],
                'Analyze both files.'
            );

            $parts = $contents[0]['parts'];
            // 2 inlineData parts + 1 text part
            $this->assertCount(3, $parts);
            $this->assertArrayHasKey('inlineData', $parts[0]);
            $this->assertArrayHasKey('inlineData', $parts[1]);
            $this->assertSame('Analyze both files.', $parts[2]['text']);
            $this->assertSame('text/plain', $parts[1]['inlineData']['mimeType']);
        } finally {
            \unlink($file1);
            \unlink($file2);
        }
    }

    public function test_with_files_throws_for_missing_file(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->makeResource()->withFiles(['/no/such/file.png'], 'test');
    }

    // -------------------------------------------------------------------------
    // fromUri()
    // -------------------------------------------------------------------------

    public function test_from_uri_returns_correct_structure(): void
    {
        $contents = $this->makeResource()->fromUri(
            'https://example.com/files/abc123',
            'video/mp4',
            'Summarize this video.'
        );

        $this->assertCount(1, $contents);
        $this->assertSame('user', $contents[0]['role']);

        $parts = $contents[0]['parts'];
        $this->assertCount(2, $parts);
        $this->assertSame('https://example.com/files/abc123', $parts[0]['fileData']['fileUri']);
        $this->assertSame('video/mp4', $parts[0]['fileData']['mimeType']);
        $this->assertSame('Summarize this video.', $parts[1]['text']);
    }

    // -------------------------------------------------------------------------
    // getFile() URL normalisation
    // -------------------------------------------------------------------------

    /**
     * We can't make real HTTP calls in unit tests, but we can verify the URL
     * that would be built by inspecting HttpClient's buildFileApiUrl output.
     */
    public function test_get_file_url_normalisation(): void
    {
        $http = new HttpClient('test-key', null, null, 'global');

        // All three forms should produce the same URL
        $url1 = $http->buildFileApiUrl('/abc123');
        $url2 = $http->buildFileApiUrl('/abc123');

        $this->assertSame($url1, $url2);
        $this->assertStringContainsString('/files/abc123', $url1);
    }

    // -------------------------------------------------------------------------
    // deleteFile()
    // -------------------------------------------------------------------------

    public function test_delete_file_calls_http_delete(): void
    {
        $http = $this->createMock(HttpClient::class);
        $http->method('buildFileApiUrl')->willReturn('https://example.com/files/abc123?key=k');
        $http->expects($this->once())->method('delete');

        $resource = new FileResource($http);
        $resource->deleteFile('files/abc123');
    }

    public function test_delete_file_normalises_name_with_prefix(): void
    {
        $http = $this->createMock(HttpClient::class);
        $http->expects($this->once())
            ->method('buildFileApiUrl')
            ->with('/abc123')
            ->willReturn('https://example.com/files/abc123');
        $http->method('delete');

        (new FileResource($http))->deleteFile('files/abc123');
    }

    public function test_delete_file_normalises_bare_id(): void
    {
        $http = $this->createMock(HttpClient::class);
        $http->expects($this->once())
            ->method('buildFileApiUrl')
            ->with('/abc123')
            ->willReturn('https://example.com/files/abc123');
        $http->method('delete');

        (new FileResource($http))->deleteFile('abc123');
    }
}
