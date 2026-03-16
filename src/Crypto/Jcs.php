<?php

declare(strict_types=1);

namespace SwipeGames\SDK\Crypto;

/**
 * RFC 8785 JSON Canonicalization Scheme.
 * Produces a deterministic JSON string with sorted keys and no extra whitespace.
 */
final class Jcs
{
    /**
     * Canonicalize JSON data according to RFC 8785.
     *
     * @param string|array<mixed> $data JSON string or associative array
     */
    public static function canonicalize(string|array $data): string
    {
        if (is_string($data)) {
            $obj = json_decode($data, false);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Failed to parse JSON: ' . json_last_error_msg());
            }
        } else {
            // Round-trip through JSON to get stdClass for objects.
            // Cast to object so empty arrays become {} not [].
            $json = json_encode((object) $data, JSON_PRESERVE_ZERO_FRACTION);
            $obj = json_decode($json, false);
        }

        return self::serializeCanonical($obj);
    }

    private static function serializeCanonical(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return self::canonicalizeNumber($value);
        }

        if (is_string($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_array($value)) {
            $parts = array_map([self::class, 'serializeCanonical'], $value);
            return '[' . implode(',', $parts) . ']';
        }

        if ($value instanceof \stdClass) {
            $props = get_object_vars($value);
            ksort($props, SORT_STRING);

            $parts = [];
            foreach ($props as $k => $v) {
                $parts[] = json_encode((string) $k, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    . ':' . self::serializeCanonical($v);
            }
            return '{' . implode(',', $parts) . '}';
        }

        throw new \InvalidArgumentException('Unsupported type: ' . gettype($value));
    }

    /**
     * Format a float according to RFC 8785 / ES2024 Number.toString().
     */
    private static function canonicalizeNumber(float $f): string
    {
        if (is_nan($f) || is_infinite($f)) {
            return 'null';
        }

        if ($f == 0.0) {
            return '0';
        }

        $abs = abs($f);

        // ES2024: use exponential for very large or very small numbers
        if ($abs >= 1e21 || $abs < 1e-6) {
            return self::formatES2024Exponential($f);
        }

        // Use shortest decimal representation
        // PHP's serialize_precision=-1 (default in 8.x) gives shortest form
        $s = json_encode($f);
        return $s;
    }

    private static function formatES2024Exponential(float $f): string
    {
        // Format as scientific notation
        $s = sprintf('%e', $f);

        // Parse mantissa and exponent
        $parts = explode('e', $s);
        if (count($parts) !== 2) {
            return $s;
        }

        $mantissa = $parts[0];
        $exp = (int) $parts[1];

        // Remove trailing zeros from mantissa
        if (str_contains($mantissa, '.')) {
            $mantissa = rtrim($mantissa, '0');
            $mantissa = rtrim($mantissa, '.');
        }

        if ($exp >= 0) {
            return $mantissa . 'e+' . $exp;
        }

        return $mantissa . 'e-' . abs($exp);
    }
}
