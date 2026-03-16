<?php

declare(strict_types=1);

namespace SwipeGames\SDK\Handler;

/**
 * Result wrapper for parsed and verified inbound requests.
 */
final readonly class ParsedResult
{
    /**
     * @param bool $ok Whether the parse+verify succeeded
     * @param mixed $body Parsed request body (when ok=true)
     * @param array<string, mixed>|null $error Error response (when ok=false)
     */
    private function __construct(
        public bool $ok,
        public mixed $body = null,
        public ?array $error = null,
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
     *
     * @param array<string, mixed> $error Error response
     */
    public static function failure(array $error): self
    {
        return new self(ok: false, error: $error);
    }
}
