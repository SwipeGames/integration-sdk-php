<?php

declare(strict_types=1);

namespace SwipeGames\SDK\Exception;

/**
 * Thrown when the Swipe Games API returns an error response.
 */
class SwipeGamesApiException extends \RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        string $message,
        public readonly ?string $errorCode = null,
        public readonly ?string $details = null,
    ) {
        $msg = "SwipeGamesApiError: {$message} (status={$statusCode})";
        if ($errorCode !== null) {
            $msg = "SwipeGamesApiError: {$message} (status={$statusCode}, code={$errorCode})";
        }
        parent::__construct($msg);
    }
}
