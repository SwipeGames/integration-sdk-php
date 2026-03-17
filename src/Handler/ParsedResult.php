<?php

declare(strict_types=1);

namespace SwipeGames\SDK\Handler;

use SwipeGames\PublicApi\Integration\ErrorResponseWithCodeAndAction;

/**
 * Result wrapper for parsed and verified inbound requests.
 */
final class ParsedResult
{
    /**
     * @param bool $ok Whether the parse+verify succeeded
     * @param mixed $body Parsed request body (when ok=true)
     * @param ErrorResponseWithCodeAndAction|null $error Error response (when ok=false)
     */
    private function __construct(
        public readonly bool $ok,
        public readonly mixed $body = null,
        public readonly ?ErrorResponseWithCodeAndAction $error = null,
    ) {
    }

    /**
     * Create a successful result.
     *
     * @param mixed $body Parsed request body
     */
    public static function success(mixed $body): self
    {
        return new self(ok: true, body: $body);
    }

    /**
     * Create a failure result.
     */
    public static function failure(ErrorResponseWithCodeAndAction $error): self
    {
        return new self(ok: false, error: $error);
    }
}
