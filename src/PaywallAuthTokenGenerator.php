<?php

namespace Comfino;

/**
 * Generates a time-limited HMAC-signed auth token for the Comfino Paywall V3 iframe.
 *
 * Payload layout (binary, then base64 encoded):
 *   Bytes 0–7: Unix timestamp, unsigned 64-bit big-endian (8 bytes)
 *   Bytes 8–39: HMAC-SHA3-256(timestamp_bytes ∥ widgetKey_utf8, apiKey), raw binary (32 bytes)
 *   Bytes 40–75: widgetKey UTF-8 string (UUIDv4, 36 bytes)
 *
 * Total: 76 bytes → ~104 chars base64-encoded.
 * Token lifetime: 15 minutes (enforced server-side).
 *
 * Requires: PHP 7.1+ with OpenSSL >= 1.1.0 (for SHA-3 hash support).
 */
final class PaywallAuthTokenGenerator
{
    public static function generateAuthToken(string $widgetKey, string $apiKey): string
    {
        $timestampBytes = pack('J', time());
        $hmac = hash_hmac('sha3-256', $timestampBytes . $widgetKey, $apiKey, true);

        return base64_encode($timestampBytes . $hmac . $widgetKey);
    }

    /**
     * Generates a versioned, domain-separated HMAC-signed auth token for the Comfino frontend error
     * reporting endpoint.
     *
     * Must stay byte-compatible with Comfino\Auth\FrontendLogAuthKeyGenerator in the Comfino Web SDK
     * backend (php-sdk / php-api-client), since a single server-side validator accepts tokens from all
     * plugins.
     *
     * Payload layout (binary, then base64 encoded):
     *   Byte  0:     Version, unsigned 8-bit integer (currently 1)
     *   Bytes 1–8:   Unix timestamp, unsigned 64-bit big-endian (8 bytes)
     *   Bytes 9–40:  HMAC-SHA3-256("comfino-fe-log:v1" ∥ version ∥ timestamp ∥ widgetKey, apiKey) (32 bytes)
     *   Bytes 41–76: widgetKey UTF-8 string (UUIDv4, 36 bytes)
     *
     * Total: 77 bytes. The "comfino-fe-log:v1" domain prefix makes this token non-interchangeable with
     * the paywall auth token above.
     */
    public static function generateLoggingToken(string $widgetKey, string $apiKey): string
    {
        $versionByte = pack('C', 1);
        $timestampBytes = pack('J', time());
        $hmac = hash_hmac('sha3-256', 'comfino-fe-log:v1' . $versionByte . $timestampBytes . $widgetKey, $apiKey, true);

        return base64_encode($versionByte . $timestampBytes . $hmac . $widgetKey);
    }
}
