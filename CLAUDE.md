# Swipe Games PHP Integration SDK

## Architecture

PHP SDK for integrating with the Swipe Games platform. Mirrors the Go (`integration-sdk-go`) and Node (`integration-sdk-node`) SDKs.

### Key Components

- **Crypto** (`src/Crypto/`) — RFC 8785 JCS canonicalization, HMAC-SHA256 signing, timing-safe verification. Must produce identical signatures to Go and Node SDKs.
- **Client** (`src/Client/`) — `ClientConfig` (readonly value object), `HttpClient` (Guzzle wrapper).
- **SwipeGamesClient** (`src/SwipeGamesClient.php`) — Main SDK class. Outbound methods (signed with `apiKey`), inbound verification (verified with `integrationApiKey`).
- **Handler** (`src/Handler/`) — `ResponseBuilder` (returns typed response objects), `ParsedResult` (ok/error wrapper).
- **Exception** (`src/Exception/`) — `SwipeGamesApiException`, `SwipeGamesValidationException`.

### Generated Types

All API types come from `swipegames/public-api` (generated from OpenAPI specs in the `public-api` repo via openapi-generator). The SDK does not define its own type classes — it imports them:

- `SwipeGames\PublicApi\Core\*` — `CreateNewGameResponse`, `CreateFreeRoundsResponse`, `GameInfo`, etc.
- `SwipeGames\PublicApi\Integration\*` — `BetRequest`, `WinRequest`, `RefundRequest`, `BalanceResponse`, `BetResponse`, etc.
- `SwipeGames\PublicApi\Common\*` — `ErrorResponse`, `User`

For local development, the types package is referenced via a Composer path repository pointing to `../public-api/packages/php`. When published to Packagist, replace with a version constraint.

### Dual-key Architecture

- `apiKey` — signs outbound requests to the Swipe Games Core API
- `integrationApiKey` — verifies inbound reverse calls from the platform

### Commands

```bash
composer install          # Install dependencies
composer test             # Run tests (alias for vendor/bin/phpunit)
vendor/bin/phpunit        # Run tests directly
```

### Testing

Crypto test vectors are shared across Go, Node, and PHP SDKs. Any change to canonicalization or signing must pass the same vectors in all three languages.
