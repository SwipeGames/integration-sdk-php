# Swipe Games PHP Integration SDK

PHP SDK for integrating with the Swipe Games platform.

## Requirements

- PHP 8.1+
- Guzzle 7.x

## Installation

```bash
composer require swipegames/integration-sdk
```

## Quick Start

```php
use SwipeGames\SDK\SwipeGamesClient;
use SwipeGames\SDK\Client\ClientConfig;

$client = new SwipeGamesClient(new ClientConfig(
    cid: 'your-client-id',
    extCid: 'your-external-client-id',
    apiKey: 'your-api-key',
    integrationApiKey: 'your-integration-api-key',
    env: 'staging', // or 'production'
));
```

## Configuration

```php
use SwipeGames\SDK\Client\ClientConfig;

$config = new ClientConfig(
    cid: 'your-client-id',           // SwipeGames-assigned client ID
    extCid: 'your-external-id',       // Your external client ID
    apiKey: 'your-api-key',           // Signs outbound Core API requests
    integrationApiKey: 'your-key',    // Verifies inbound reverse calls
    env: 'staging',                   // 'staging' (default) or 'production'
    baseUrl: null,                    // Custom URL (overrides env)
    debug: false,                     // Enable debug logging
    logger: $psrLogger,               // PSR-3 LoggerInterface
    timeout: 10,                      // HTTP timeout in seconds
);
```

### Environments

| Environment  | URL                                               |
| ------------ | ------------------------------------------------- |
| `staging`    | `https://staging.platform.0.swipegames.io/api/v1` |
| `production` | `https://prod.platform.1.swipegames.io/api/v1`    |

## Core API

### Create New Game

```php
use SwipeGames\PublicApi\Core\CreateNewGameResponse;

$result = $client->createNewGame([
    'gameID' => 'sg_catch_97',
    'demo' => false,
    'platform' => 'desktop',   // 'desktop' or 'mobile'
    'currency' => 'USD',
    'locale' => 'en_us',
    'sessionID' => 'your-session-id',       // optional
    'returnURL' => 'https://your-site.com', // optional
    'depositURL' => 'https://deposit.url', // optional
    'initDemoBalance' => '5000',           // optional (demo only)
    'user' => [                             // optional
        'id' => 'player-123',
        'firstName' => 'John',
    ],
]);

// $result is a CreateNewGameResponse object
$result->getGameUrl();  // redirect player here
$result->getGsId();     // game session ID
```

### Get Games

```php
use SwipeGames\PublicApi\Core\GameInfo;

/** @var GameInfo[] $games */
$games = $client->getGames();

foreach ($games as $game) {
    echo $game->getId() . ': ' . $game->getTitle() . "\n";
    // $game->getCurrencies(), $game->getLocales(), $game->getPlatforms()
    // $game->getImages()->getSquare(), $game->getHasFreeSpins(), $game->getRtp()
}
```

### Create Free Rounds

```php
use SwipeGames\PublicApi\Core\CreateFreeRoundsResponse;

$result = $client->createFreeRounds([
    'extID' => 'my-campaign-001',
    'currency' => 'USD',
    'quantity' => 10,
    'betLine' => 1,
    'validFrom' => '2026-01-01T00:00:00.000Z',
    'gameIDs' => ['sg_catch_97'],    // optional
    'userIDs' => ['player-123'],     // optional
    'validUntil' => '2026-02-01T00:00:00.000Z', // optional
]);

// $result is a CreateFreeRoundsResponse object
$result->getId();     // internal campaign ID
$result->getExtId();  // your external ID
```

### Cancel Free Rounds

```php
// By internal ID
$client->cancelFreeRounds(['id' => 'campaign-uuid']);

// By external ID
$client->cancelFreeRounds(['extID' => 'my-campaign-001']);
```

## Integration Adapter

Implement these endpoints on your side to handle reverse calls from the platform.

> **Important:** The platform enforces a **5-second timeout** on all integration adapter calls. If your endpoint does not respond in time, the platform will send a refund for bet requests and retry win/refund requests until a 200 response is received.

### Parse and Verify Requests

The SDK verifies signatures, validates request bodies, and returns typed objects in one step:

```php
use SwipeGames\PublicApi\Integration\BetRequest;

// In your /bet endpoint handler
$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_REQUEST_SIGN'] ?? null;

$result = $client->parseAndVerifyBetRequest($rawBody, $signature);

if (!$result->ok) {
    http_response_code(401);
    echo json_encode($result->error);
    return;
}

/** @var BetRequest $betRequest */
$betRequest = $result->body;
$betRequest->getType();       // 'regular' or 'free'
$betRequest->getSessionId();  // game session ID
$betRequest->getAmount();     // bet amount
$betRequest->getTxId();       // transaction ID (idempotency key)
$betRequest->getRoundId();    // round ID
$betRequest->getFrId();       // free rounds ID (only for type='free')

// Process the bet...
```

Available parse+verify methods:

| Method                                             | Request Type | Returns                |
| -------------------------------------------------- | ------------ | ---------------------- |
| `parseAndVerifyBetRequest($body, $sig)`            | POST /bet    | `ParsedResult<BetRequest>`    |
| `parseAndVerifyWinRequest($body, $sig)`            | POST /win    | `ParsedResult<WinRequest>`    |
| `parseAndVerifyRefundRequest($body, $sig)`         | POST /refund | `ParsedResult<RefundRequest>` |
| `parseAndVerifyBalanceRequest($queryParams, $sig)` | GET /balance | `ParsedResult<array>` (pass `$_GET`) |

### Verify-Only Methods

If you only need signature verification:

```php
$isValid = $client->verifyBetRequest($rawBody, $signature);
$isValid = $client->verifyWinRequest($rawBody, $signature);
$isValid = $client->verifyRefundRequest($rawBody, $signature);
$isValid = $client->verifyBalanceRequest($_GET, $signature);
```

### Response Builders

Response builders return typed objects that are `JsonSerializable`:

```php
use SwipeGames\SDK\Handler\ResponseBuilder;

// Balance response — returns BalanceResponse object
echo json_encode(ResponseBuilder::balanceResponse('100.50'));
// {"balance":"100.50"}

// Bet response — returns BetResponse object
echo json_encode(ResponseBuilder::betResponse('90.50', 'your-tx-id'));
// {"balance":"90.50","txID":"your-tx-id"}

// Win response — returns WinResponse object
echo json_encode(ResponseBuilder::winResponse('150.50', 'your-tx-id'));

// Refund response — returns RefundResponse object
echo json_encode(ResponseBuilder::refundResponse('100.50', 'your-tx-id'));

// Error response — returns ErrorResponseWithCodeAndAction object
echo json_encode(ResponseBuilder::errorResponse(
    message: 'Insufficient funds',
    code: 'insufficient_funds',     // optional
    action: 'refresh',              // optional
    actionData: 'some-data',        // optional
    details: 'Balance is 0',        // optional
));
```

## Types

All API types are generated from OpenAPI specs and provided by the `swipegames/public-api` package:

| Namespace | Types |
| --------- | ----- |
| `SwipeGames\PublicApi\Common` | `ErrorResponse`, `User` |
| `SwipeGames\PublicApi\Core` | `CreateNewGameRequest`, `CreateNewGameResponse`, `CreateFreeRoundsRequest`, `CreateFreeRoundsResponse`, `DeleteFreeRoundsRequest`, `GameInfo`, `GameInfoImages`, `BetLineInfo`, `BetLineValue`, `PlatformType` |
| `SwipeGames\PublicApi\Integration` | `BetRequest`, `WinRequest`, `RefundRequest`, `BalanceResponse`, `BetResponse`, `WinResponse`, `RefundResponse`, `ErrorResponseWithCodeAndAction` |

## Error Handling

```php
use SwipeGames\SDK\Exception\SwipeGamesApiException;
use SwipeGames\SDK\Exception\SwipeGamesValidationException;

try {
    $result = $client->createNewGame([...]);
} catch (SwipeGamesApiException $e) {
    // API returned an error or a network error occurred
    echo $e->statusCode;  // HTTP status (0 for network errors)
    echo $e->errorCode;   // Error code (optional)
    echo $e->details;     // Details (optional)
} catch (SwipeGamesValidationException $e) {
    // Request parameters failed validation
    echo $e->getMessage();
}
```

### Error Codes

| Code                      | Description            |
| ------------------------- | ---------------------- |
| `game_not_found`          | Game ID not found      |
| `currency_not_supported`  | Currency not supported |
| `locale_not_supported`    | Locale not supported   |
| `account_blocked`         | Player account blocked |
| `bet_limit`               | Bet limit exceeded     |
| `loss_limit`              | Loss limit exceeded    |
| `time_limit`              | Time limit exceeded    |
| `insufficient_funds`      | Insufficient balance   |
| `session_expired`         | Game session expired   |
| `session_not_found`       | Game session not found |
| `client_connection_error` | Connection error       |

### Error Actions

| Action    | Description                   |
| --------- | ----------------------------- |
| `refresh` | Show refresh button to player |

## Debug Logging

Enable debug logging with a PSR-3 logger:

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('swipegames');
$logger->pushHandler(new StreamHandler('php://stderr'));

$client = new SwipeGamesClient(new ClientConfig(
    // ...
    debug: true,
    logger: $logger,
));
```

All log messages are prefixed with `[SwipeGamesSDK]`.

## Development

```bash
composer install
vendor/bin/phpunit
```
