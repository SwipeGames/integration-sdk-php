# Auto-Update SDK for swipegames/public-api

You are updating `swipegames/integration-sdk` (PHP) to match a new version of `swipegames/public-api`.

## Environment

These env vars provide context (all optional — detect from local state if missing):

- `PUBLIC_API_DIFF_FILE` — path to a file containing the git diff between versions
- `PUBLIC_API_REPO_PATH` — path to the public-api repo checkout (default: `../public-api`)
- `CURRENT_VERSION` — currently installed version of `swipegames/public-api`
- `TARGET_VERSION` — target version to update to

## Step 1: Gather context

1. **Detect versions** (if not provided via env vars):
   - Current: `jq -r '.require["swipegames/public-api"]' composer.json | sed 's/^[^0-9]*//'`
   - Target: `curl -sf https://repo.packagist.org/p2/swipegames/public-api.json | jq -r '.packages["swipegames/public-api"][0].version' | sed 's/^v//'`

2. **Read the public-api diff** to understand what changed:
   - If `PUBLIC_API_DIFF_FILE` is set and exists, read that file
   - Otherwise, in the public-api repo (`PUBLIC_API_REPO_PATH` or `../public-api`), run:
     ```
     git diff v{current}..v{target} -- packages/php/ api/ docs/ ':!**/*.gen.go' ':!**/go.mod' ':!**/go.sum' ':!**/composer.lock' ':!**/schemas/*.schema.mdx'
     ```
   - If the public-api repo is not available, inspect the installed package at `vendor/swipegames/public-api/` to understand the current API surface

3. **Read the public-api source** for full context:
   - In the public-api repo: `packages/php/src/`, `api/`, `docs/` directories
   - Pay special attention to `docs/changes-log.md` for a human-readable changelog
   - Enumerate the actual PHP namespaces present in the installed package (`vendor/swipegames/public-api/packages/php/src/*/`) — do NOT assume a fixed set. Namespaces have changed between versions (e.g. `Common\*` was removed in v1.4.0 and its types moved to `Core\*`). Note any added, removed, or renamed namespaces/types.

4. **Categorize the changes:**
   - New Core API endpoints (→ new methods on `SwipeGamesClient`)
   - New Integration (reverse-call) endpoints (→ new verify/parse methods + response builders)
   - Changed request/response schemas (→ update method signatures if needed)
   - New shared types or enums (→ types come from `swipegames/public-api`, no local changes needed)
   - New error codes or actions (→ update exception handling if needed)
   - Namespace moves/renames or added/removed types (→ update `use` statements in `src/` and `tests/`, and the namespace table in `README.md`)
   - Breaking changes (→ document in PR body)

## Step 2: Read the current SDK

Read these files to understand existing patterns:

1. `CLAUDE.md` — project architecture overview
2. `src/SwipeGamesClient.php` — main client class (outbound + inbound methods)
3. `src/Handler/ResponseBuilder.php` — response builder functions (setter-style)
4. `src/Handler/ParsedResult.php` — ok/error wrapper
5. `src/Client/ClientConfig.php` — readonly value object for configuration
6. `src/Client/HttpClient.php` — Guzzle wrapper
7. `src/Crypto/Jcs.php` — RFC 8785 JCS canonicalization
8. `src/Crypto/Signer.php` — HMAC-SHA256 signing
9. `src/Crypto/Verifier.php` — timing-safe signature verification
10. `src/Exception/SwipeGamesApiException.php`
11. `src/Exception/SwipeGamesValidationException.php`
12. All files in `tests/` — test patterns

## Step 3: Update the SDK

Apply changes following existing patterns exactly. For each change type:

### New Core API endpoint

1. **`src/SwipeGamesClient.php`**: Add a new public method following the pattern of existing methods (e.g., `createNewGame`, `getGames`):
   - Use the appropriate HTTP method via `doRequest` (POST) or `doGet` (GET)
   - Build the path from the endpoint
   - Sign the request with `$this->apiKey` (outbound key)
   - Deserialize the response with `ObjectSerializer::deserialize($responseBody, 'TargetType')`
   - Return the typed result

### New Integration (reverse-call) endpoint

1. **`src/SwipeGamesClient.php`**: Add two methods following existing patterns:
   - `verify{Name}Request(array $headers, string $rawBody): bool` — signature verification only using `$this->integrationApiKey`
   - `parseAndVerify{Name}Request(array $headers, string $rawBody): ParsedResult` — verify + parse body + validate with `listInvalidProperties()`

2. **`src/Handler/ResponseBuilder.php`**: Add a `create{Name}Response($data): {Name}Response` builder function using setter-style construction

### Changed schemas (new fields, removed fields)

1. Types come from `swipegames/public-api` package — no local type changes needed
2. Update any client methods that construct or consume changed types
3. Update method signatures if parameter types changed
4. If fields were removed, check for usages in tests and update accordingly

### New error codes or actions

1. Error types come from `swipegames/public-api` — typically no local changes needed
2. Update response builders or exception handling if they need to handle new codes

### Namespace moves/renames or added/removed types

1. Grep `src/` and `tests/` for `use SwipeGames\PublicApi\...` and update any imports whose namespace moved (e.g., a type that relocated from `Common\` to `Core\`). Remove imports for types that were removed upstream.
2. Update the namespace/types table in `README.md` so it reflects the namespaces and types actually present in `vendor/swipegames/public-api/packages/php/src/` for the target version. Add new public types, remove deleted ones, and delete rows for namespaces that no longer exist.

## Step 4: Update tests

Follow existing test patterns exactly:

1. Read existing test files in `tests/` to understand the style (PHPUnit 10, etc.)
2. Add tests for every new method or changed behavior
3. Test both success and error paths
4. For new client methods: test request signing, URL construction, response deserialization
5. For new verify/parse methods: test valid signatures, invalid signatures, malformed bodies
6. For new response builders: test output shape and type correctness

## Step 5: Update user-facing docs

1. `README.md` — verify every code example, type reference, and the namespace/types table still matches the installed `vendor/swipegames/public-api/packages/php/src/` layout. Fix any stale namespace (e.g. `Common\ErrorResponse` → `Core\ErrorResponse`), renamed type, or removed field.
2. If the SDK's public API changed (new methods, changed signatures, new builders), add or update the corresponding section in the README.
3. Grep the repo for any other references to old namespaces/types and update them (`grep -rn "SwipeGames\\\\PublicApi\\\\" README.md docs/ 2>/dev/null`).

## Step 6: Verify

1. Run `vendor/bin/phpunit` — all tests must pass
2. If tests fail, read the error output carefully, fix the issues, and re-run
3. Repeat until all tests pass cleanly
4. Final sanity check: `grep -rn "SwipeGames\\\\PublicApi\\\\" src/ tests/ README.md` should not reference any namespace that no longer exists in `vendor/swipegames/public-api/packages/php/src/`.

## Constraints

- **`declare(strict_types=1)`** in all PHP files
- **PSR-4 autoloading** under `SwipeGames\SDK\` namespace
- **PHP ^8.1 compatibility** — do not use features unavailable in PHP 8.1
- **Follow existing code style exactly**: same naming conventions, same patterns, same file organization
- **Do NOT modify `src/Crypto/`** unless the signing/verification mechanism itself changed
- **Maintain dual-key architecture**: `apiKey` for outbound requests, `integrationApiKey` for inbound verification
- **Types from `SwipeGames\PublicApi\*`**: use whichever namespaces exist in the installed package for the target version (typically `Core\*` and `Integration\*`; older versions also had `Common\*`). Do not import a namespace that does not exist in `vendor/swipegames/public-api/packages/php/src/`.
- **`ObjectSerializer::deserialize`** for all deserialization
- **`listInvalidProperties()`** for validation of parsed request bodies
- **Setter-style response builders** in `ResponseBuilder.php`
- **Backward compatible**: Don't remove or rename existing public API unless the upstream change is breaking
- **No unnecessary changes**: Only modify what's needed to support the new public-api version
