<?php

namespace GoogleAgentPlatform\Exceptions;

/**
 * Thrown for authentication and authorisation failures (HTTP 401 / 403),
 * or when credentials are missing / invalid at construction time.
 */
class AuthException extends ApiException {}
