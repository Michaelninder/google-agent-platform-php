<?php

namespace GoogleAgentPlatform\Tests\Unit;

use GoogleAgentPlatform\Client;
use GoogleAgentPlatform\Resources\AudioResource;
use GoogleAgentPlatform\Resources\ClaudeResource;
use GoogleAgentPlatform\Resources\FileResource;
use GoogleAgentPlatform\Resources\ImageResource;
use GoogleAgentPlatform\Resources\TextResource;
use GoogleAgentPlatform\Resources\VideoResource;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    public function test_constructs_with_api_key(): void
    {
        $client = new Client(['api_key' => 'test-key']);

        $this->assertInstanceOf(TextResource::class,  $client->text);
        $this->assertInstanceOf(ImageResource::class, $client->images);
        $this->assertInstanceOf(AudioResource::class, $client->audio);
        $this->assertInstanceOf(VideoResource::class, $client->video);
        $this->assertInstanceOf(ClaudeResource::class, $client->claude);
        $this->assertInstanceOf(FileResource::class,  $client->files);
    }

    public function test_constructs_with_cloud_mode(): void
    {
        $client = new Client([
            'project_id'   => 'my-project',
            'access_token' => 'my-token',
            'location'     => 'us-central1',
        ]);

        $this->assertInstanceOf(Client::class, $client);
    }

    public function test_throws_without_credentials(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Client([]);
    }

    public function test_throws_with_project_id_but_no_token(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Client(['project_id' => 'my-project']);
    }

    public function test_throws_with_token_but_no_project_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Client(['access_token' => 'my-token']);
    }
}
