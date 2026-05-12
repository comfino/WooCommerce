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
}
