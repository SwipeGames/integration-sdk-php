<?php

declare(strict_types=1);

namespace SwipeGames\SDK\Exception;

/**
 * Thrown when request parameters fail validation.
 */
class SwipeGamesValidationException extends \InvalidArgumentException
{
    /**
     * @param array<string, string> $errors Validation errors keyed by field
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
    ) {
        parent::__construct("SwipeGamesValidationError: {$message}");
    }
}
