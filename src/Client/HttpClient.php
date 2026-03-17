<?php

declare(strict_types=1);

namespace SwipeGames\SDK\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Guzzle HTTP client wrapper.
 */
class HttpClient
{
    private Client $client;

    public function __construct(?Client $client = null, int $timeout = 10)
    {
        $this->client = $client ?? new Client([
            'http_errors' => false,
            'timeout' => $timeout,
        ]);
    }

    /**
     * @param string $method HTTP method
     * @param string $url Full URL
     * @param array<string, mixed> $options Guzzle request options
     * @return array{statusCode: int, body: string}
     * @throws GuzzleException
     */
    public function request(string $method, string $url, array $options = []): array
    {
        $response = $this->client->request($method, $url, $options);

        return [
            'statusCode' => $response->getStatusCode(),
            'body' => $response->getBody()->getContents(),
        ];
    }
}
