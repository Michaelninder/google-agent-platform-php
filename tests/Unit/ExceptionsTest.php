<?php

namespace GoogleAgentPlatform\Tests\Unit;

use GoogleAgentPlatform\Exceptions\AgentPlatformException;
use GoogleAgentPlatform\Exceptions\ApiException;
use GoogleAgentPlatform\Exceptions\AuthException;
use GoogleAgentPlatform\Exceptions\FileNotFoundException;
use PHPUnit\Framework\TestCase;

class ExceptionsTest extends TestCase
{
    public function test_api_exception_is_agent_platform_exception(): void
    {
        $e = new ApiException('test', 400);
        $this->assertInstanceOf(AgentPlatformException::class, $e);
        $this->assertSame(400, $e->getHttpCode());
    }

    public function test_auth_exception_is_api_exception(): void
    {
        $e = new AuthException('Unauthorized', 401);
        $this->assertInstanceOf(ApiException::class, $e);
        $this->assertInstanceOf(AgentPlatformException::class, $e);
        $this->assertSame(401, $e->getHttpCode());
    }

    public function test_file_not_found_exception_is_agent_platform_exception(): void
    {
        $e = new FileNotFoundException('File not found: /tmp/x.jpg');
        $this->assertInstanceOf(AgentPlatformException::class, $e);
        $this->assertStringContainsString('/tmp/x.jpg', $e->getMessage());
    }

    public function test_all_exceptions_extend_runtime_exception(): void
    {
        $this->assertInstanceOf(\RuntimeException::class, new AgentPlatformException());
        $this->assertInstanceOf(\RuntimeException::class, new ApiException('x'));
        $this->assertInstanceOf(\RuntimeException::class, new AuthException('x'));
        $this->assertInstanceOf(\RuntimeException::class, new FileNotFoundException('x'));
    }
}
