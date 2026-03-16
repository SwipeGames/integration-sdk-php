<?php

declare(strict_types=1);

namespace SwipeGames\SDK\Client;

use Psr\Log\LoggerInterface;

/**
 * Configuration for the SwipeGames client.
 */
final readonly class ClientConfig
{
    /**
     * @param string $cid SwipeGames-assigned client ID
     * @param string $extCid External client ID
     * @param string $apiKey API key for signing outbound Core API requests
     * @param string $integrationApiKey API key for verifying inbound reverse calls
     * @param string $env Environment: 'staging' (default) or 'production'
     * @param string|null $baseUrl Custom base URL (overrides env)
     * @param bool $debug Enable debug logging
     * @param LoggerInterface|null $logger PSR-3 logger instance
     * @param int $timeout HTTP request timeout in seconds
     */
    public function __construct(
        public string $cid,
        public string $extCid,
        public string $apiKey,
        public string $integrationApiKey,
        public string $env = 'staging',
        public ?string $baseUrl = null,
        public bool $debug = false,
        public ?LoggerInterface $logger = null,
        public int $timeout = 10,
    ) {
    }
}
