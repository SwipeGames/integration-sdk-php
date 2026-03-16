<?php

declare(strict_types=1);

namespace SwipeGames\SDK\Crypto;

/**
 * Timing-safe HMAC-SHA256 signature verification.
 */
final class Verifier
{
    /**
     * Verify an HMAC-SHA256 signature against the given data using timing-safe comparison.
     *
     * @param string|array<mixed> $data JSON string or associative array
     * @param string $signature hex-encoded signature to verify
     * @param string $apiKey HMAC secret key
     */
    public static function verify(string|array $data, string $signature, string $apiKey): bool
    {
        $expected = Signer::sign($data, $apiKey);
        return hash_equals($expected, $signature);
    }

    /**
     * Verify a query params signature using timing-safe comparison.
     *
     * @param array<string, string> $params flat key-value map
     * @param string $signature hex-encoded signature to verify
     * @param string $apiKey HMAC secret key
     */
    public static function verifyQueryParams(array $params, string $signature, string $apiKey): bool
    {
        $expected = Signer::signQueryParams($params, $apiKey);
        return hash_equals($expected, $signature);
    }
}
