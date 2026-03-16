<?php

declare(strict_types=1);

namespace SwipeGames\SDK;

use SwipeGames\SDK\Client\ClientConfig;
use SwipeGames\SDK\Client\HttpClient;
use SwipeGames\SDK\Crypto\Jcs;
use SwipeGames\SDK\Crypto\Signer;
use SwipeGames\SDK\Crypto\Verifier;
use SwipeGames\SDK\Exception\SwipeGamesApiException;
use SwipeGames\SDK\Exception\SwipeGamesValidationException;
use SwipeGames\SDK\Handler\ParsedResult;
use SwipeGames\SDK\Handler\ResponseBuilder;
use SwipeGames\PublicApi\ObjectSerializer;
use SwipeGames\PublicApi\Core\CreateNewGameResponse;
use SwipeGames\PublicApi\Core\CreateFreeRoundsResponse;
use SwipeGames\PublicApi\Core\GameInfo;
use SwipeGames\PublicApi\Integration\BetRequest;
use SwipeGames\PublicApi\Integration\WinRequest;
use SwipeGames\PublicApi\Integration\RefundRequest;

class SwipeGamesClient
{
    private const ENV_URLS = [
        'staging' => 'https://staging.platform.0.swipegames.io/api/v1',
        'production' => 'https://prod.platform.1.swipegames.io/api/v1',
    ];

    private readonly string $cid;
    private readonly string $extCid;
    private readonly string $apiKey;
    private readonly string $integrationApiKey;
    private readonly string $baseUrl;
    private readonly bool $debug;
    private readonly ?object $logger;
    private readonly HttpClient $httpClient;

    public function __construct(ClientConfig $config, ?HttpClient $httpClient = null)
    {
        $this->cid = $config->cid;
        $this->extCid = $config->extCid;
        $this->apiKey = $config->apiKey;
        $this->integrationApiKey = $config->integrationApiKey;
        $this->debug = $config->debug;
        $this->logger = $config->logger;
        $this->httpClient = $httpClient ?? new HttpClient();

        if ($config->baseUrl !== null) {
            $this->baseUrl = $config->baseUrl;
        } else {
            $env = $config->env;
            if (!isset(self::ENV_URLS[$env])) {
                throw new \InvalidArgumentException("Unknown env: {$env}");
            }
            $this->baseUrl = self::ENV_URLS[$env];
        }
    }

    // ── Outbound: SDK → Platform (signed with apiKey) ──

    /**
     * Create a new game session and get the launcher URL.
     *
     * @param array{
     *     gameID: string,
     *     demo: bool,
     *     platform: string,
     *     currency: string,
     *     locale: string,
     *     sessionID?: string,
     *     returnURL?: string,
     *     depositURL?: string,
     *     initDemoBalance?: string,
     *     user?: array{id: string, firstName?: string, lastName?: string, nickName?: string, country?: string},
     * } $params
     */
    public function createNewGame(array $params): CreateNewGameResponse
    {
        $body = $this->buildCreateNewGameBody($params);
        $result = $this->doRequest('POST', '/create-new-game', $body);
        return ObjectSerializer::deserialize(
            json_decode($result['body']),
            CreateNewGameResponse::class
        );
    }

    /**
     * Get information about all supported games.
     *
     * @return GameInfo[]
     */
    public function getGames(): array
    {
        $queryParams = [
            'cID' => $this->cid,
            'extCID' => $this->extCid,
        ];
        $result = $this->doGet('/games', $queryParams);
        return ObjectSerializer::deserialize(
            json_decode($result['body']),
            GameInfo::class . '[]'
        );
    }

    /**
     * Create a new free rounds campaign.
     *
     * @param array{
     *     extID: string,
     *     currency: string,
     *     quantity: int,
     *     betLine: int,
     *     validFrom: string,
     *     gameIDs?: string[],
     *     userIDs?: string[],
     *     validUntil?: string,
     * } $params
     */
    public function createFreeRounds(array $params): CreateFreeRoundsResponse
    {
        $body = $this->buildCreateFreeRoundsBody($params);
        $result = $this->doRequest('POST', '/free-rounds', $body);
        return ObjectSerializer::deserialize(
            json_decode($result['body']),
            CreateFreeRoundsResponse::class
        );
    }

    /**
     * Cancel/delete an existing free rounds campaign.
     *
     * @param array{id?: string, extID?: string} $params At least one of id or extID must be provided
     */
    public function cancelFreeRounds(array $params): void
    {
        $id = $params['id'] ?? '';
        $extID = $params['extID'] ?? '';

        if ($id === '' && $extID === '') {
            throw new SwipeGamesValidationException('One of id or extID must be provided');
        }

        $body = [
            'cID' => $this->cid,
            'extCID' => $this->extCid,
        ];
        if ($id !== '') {
            $body['id'] = $id;
        }
        if ($extID !== '') {
            $body['extID'] = $extID;
        }

        $this->doRequest('DELETE', '/free-rounds', $body);
    }

    // ── Inbound: Platform → SDK (verified with integrationApiKey) ──

    public function verifyBetRequest(string $body, ?string $signature): bool
    {
        return $this->verifyInboundSignature($body, $signature);
    }

    public function verifyWinRequest(string $body, ?string $signature): bool
    {
        return $this->verifyInboundSignature($body, $signature);
    }

    public function verifyRefundRequest(string $body, ?string $signature): bool
    {
        return $this->verifyInboundSignature($body, $signature);
    }

    /**
     * @param array<string, string> $queryParams
     */
    public function verifyBalanceRequest(array $queryParams, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }
        return Verifier::verifyQueryParams($queryParams, $signature, $this->integrationApiKey);
    }

    /**
     * @return ParsedResult<BetRequest>
     */
    public function parseAndVerifyBetRequest(string $rawBody, ?string $signature): ParsedResult
    {
        return $this->parseAndVerifyInboundRequest($rawBody, $signature, BetRequest::class, function (BetRequest $req): ?string {
            $invalid = $req->listInvalidProperties();
            if (!empty($invalid)) {
                return 'Invalid request body';
            }
            return null;
        });
    }

    /**
     * @return ParsedResult<WinRequest>
     */
    public function parseAndVerifyWinRequest(string $rawBody, ?string $signature): ParsedResult
    {
        return $this->parseAndVerifyInboundRequest($rawBody, $signature, WinRequest::class, function (WinRequest $req): ?string {
            $invalid = $req->listInvalidProperties();
            if (!empty($invalid)) {
                return 'Invalid request body';
            }
            return null;
        });
    }

    /**
     * @return ParsedResult<RefundRequest>
     */
    public function parseAndVerifyRefundRequest(string $rawBody, ?string $signature): ParsedResult
    {
        return $this->parseAndVerifyInboundRequest($rawBody, $signature, RefundRequest::class, function (RefundRequest $req): ?string {
            $invalid = $req->listInvalidProperties();
            if (!empty($invalid)) {
                return 'Invalid request body';
            }
            return null;
        });
    }

    /**
     * @param array<string, string> $queryParams
     */
    public function parseAndVerifyBalanceRequest(array $queryParams, ?string $signature): ParsedResult
    {
        if (!$this->verifyBalanceRequest($queryParams, $signature)) {
            return ParsedResult::failure(ResponseBuilder::errorResponse('Invalid signature'));
        }

        $sessionID = $queryParams['sessionID'] ?? '';
        if ($sessionID === '') {
            return ParsedResult::failure(ResponseBuilder::errorResponse('Missing sessionID'));
        }

        return ParsedResult::success(['sessionID' => $sessionID]);
    }

    // ── Internal helpers ──

    private function verifyInboundSignature(string $body, ?string $signature): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }
        return Verifier::verify($body, $signature, $this->integrationApiKey);
    }

    /**
     * @template T
     * @param class-string<T> $class
     * @param callable(T): ?string $validate
     * @return ParsedResult<T>
     */
    private function parseAndVerifyInboundRequest(string $rawBody, ?string $signature, string $class, callable $validate): ParsedResult
    {
        try {
            if (!$this->verifyInboundSignature($rawBody, $signature)) {
                return ParsedResult::failure(ResponseBuilder::errorResponse('Invalid signature'));
            }

            $decoded = json_decode($rawBody);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                return ParsedResult::failure(ResponseBuilder::errorResponse('Invalid request body'));
            }

            $instance = ObjectSerializer::deserialize($decoded, $class);

            $error = $validate($instance);
            if ($error !== null) {
                return ParsedResult::failure(ResponseBuilder::errorResponse($error));
            }

            return ParsedResult::success($instance);
        } catch (\Throwable) {
            return ParsedResult::failure(ResponseBuilder::errorResponse('Invalid request body'));
        }
    }

    /**
     * @param array<string, string> $queryParams
     * @return array{statusCode: int, body: string}
     */
    private function doGet(string $path, array $queryParams): array
    {
        $url = $this->baseUrl . $path . '?' . http_build_query($queryParams);
        $signature = Signer::signQueryParams($queryParams, $this->apiKey);

        $this->log("GET {$url}");

        $result = $this->httpClient->request('GET', $url, [
            'headers' => [
                'X-REQUEST-SIGN' => $signature,
            ],
        ]);

        $this->log("GET {$url} -> {$result['statusCode']}");

        if ($result['statusCode'] < 200 || $result['statusCode'] >= 300) {
            $this->throwApiError($result, "GET {$url}");
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $body
     * @return array{statusCode: int, body: string}
     */
    private function doRequest(string $method, string $path, array $body): array
    {
        $url = $this->baseUrl . $path;
        $canonical = Jcs::canonicalize($body);
        $signature = Signer::sign($body, $this->apiKey);

        $this->log("{$method} {$url}");
        $this->log("Body: {$canonical}");

        $result = $this->httpClient->request($method, $url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-REQUEST-SIGN' => $signature,
            ],
            'body' => $canonical,
        ]);

        $this->log("{$method} {$url} -> {$result['statusCode']}");

        if ($result['statusCode'] < 200 || $result['statusCode'] >= 300) {
            $this->throwApiError($result, "{$method} {$url}");
        }

        return $result;
    }

    /**
     * @param array{statusCode: int, body: string} $result
     */
    private function throwApiError(array $result, string $label): never
    {
        $errBody = json_decode($result['body'], true) ?? [];
        $message = $errBody['message'] ?? 'Unknown error';
        $code = $errBody['code'] ?? null;
        $details = $errBody['details'] ?? null;

        $this->logError("{$label} error: " . json_encode($errBody));

        throw new SwipeGamesApiException(
            statusCode: $result['statusCode'],
            message: $message,
            errorCode: $code,
            details: $details,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCreateNewGameBody(array $params): array
    {
        $body = [
            'cID' => $this->cid,
            'extCID' => $this->extCid,
            'gameID' => $params['gameID'],
            'demo' => $params['demo'],
            'platform' => $params['platform'],
            'currency' => $params['currency'],
            'locale' => $params['locale'],
        ];

        foreach (['sessionID', 'returnURL', 'depositURL', 'initDemoBalance'] as $optional) {
            if (isset($params[$optional]) && $params[$optional] !== '') {
                $body[$optional] = $params[$optional];
            }
        }

        if (isset($params['user'])) {
            $body['user'] = $params['user'];
        }

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCreateFreeRoundsBody(array $params): array
    {
        $body = [
            'cID' => $this->cid,
            'extCID' => $this->extCid,
            'extID' => $params['extID'],
            'currency' => $params['currency'],
            'quantity' => $params['quantity'],
            'betLine' => $params['betLine'],
            'validFrom' => $params['validFrom'],
        ];

        if (!empty($params['gameIDs'])) {
            $body['gameIDs'] = $params['gameIDs'];
        }
        if (!empty($params['userIDs'])) {
            $body['userIDs'] = $params['userIDs'];
        }
        if (isset($params['validUntil']) && $params['validUntil'] !== '') {
            $body['validUntil'] = $params['validUntil'];
        }

        return $body;
    }

    private function log(string $message): void
    {
        if (!$this->debug) {
            return;
        }
        if ($this->logger !== null) {
            $this->logger->debug("[SwipeGamesSDK] {$message}");
        }
    }

    private function logError(string $message): void
    {
        if (!$this->debug) {
            return;
        }
        if ($this->logger !== null) {
            $this->logger->error("[SwipeGamesSDK] {$message}");
        }
    }
}
