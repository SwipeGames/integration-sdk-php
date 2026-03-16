<?php

declare(strict_types=1);

namespace SwipeGames\SDK\Crypto;

/**
 * HMAC-SHA256 signing using JCS canonicalization.
 * Matches the Go and Node SDK implementations.
 */
final class Signer
{
    /**
     * Create an HMAC-SHA256 signature of the given data using JCS canonicalization.
     *
     * @param string|array<mixed> $data JSON string or associative array
     * @param string $apiKey HMAC secret key
     * @return string hex-encoded HMAC-SHA256 signature
     */
    public static function sign(string|array $data, string $apiKey): string
    {
        $canonical = Jcs::canonicalize($data);
        return hash_hmac('sha256', $canonical, $apiKey);
    }

    /**
     * Create an HMAC-SHA256 signature from query parameters.
     *
     * @param array<string, string> $params flat key-value map
     * @param string $apiKey HMAC secret key
     * @return string hex-encoded HMAC-SHA256 signature
     */
    public static function signQueryParams(array $params, string $apiKey): string
    {
        return self::sign($params, $apiKey);
    }
}
