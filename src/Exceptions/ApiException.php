<?php

namespace GoogleAgentPlatform\Exceptions;

/**
 * Thrown when the API returns an error response (HTTP 4xx / 5xx).
 */
class ApiException extends AgentPlatformException
{
    public function __construct(
        string           $message,
        private readonly int $httpCode = 0,
        ?\Throwable      $previous = null
    ) {
        parent::__construct($message, $httpCode, $previous);
    }

    /**
     * The HTTP status code returned by the API.
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}
