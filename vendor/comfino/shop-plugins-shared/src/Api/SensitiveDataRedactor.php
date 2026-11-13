<?php

declare(strict_types=1);

namespace Comfino\Api;

final class SensitiveDataRedactor
{
    private const SENSITIVE_KEY_FRAGMENTS = [
        'email',
        'phone',
        'taxid',
        'tax_id',
        'firstname',
        'first_name',
        'lastname',
        'last_name',
        'street',
        'postalcode',
        'postal_code',
        'zipcode',
        'zip_code',
        'housenumber',
        'house_number',
        'flatnumber',
        'flat_number',
        'apartmentnumber',
        'apartment_number',
        'buildingnumber',
        'building_number',
        'ipaddress',
        'ip_address',
        'apikey',
        'api_key',
        'authorization',
        'password',
        'secret',
        'token',
        'credential',
        'pesel',
        'regon',
    ];

    private const SENSITIVE_KEY_WORDS = ['nip', 'city'];

    private const CREDENTIAL_HEADER_PATTERN = '/\b(authorization|proxy-authorization|cookie|set-cookie)(\s*[:=]\s*)[^\r\n]+/i';
    private const BEARER_PATTERN = '/\bBearer\s+[A-Za-z0-9._~+\/=-]+/i';
    private const JWT_PATTERN = '/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]*/';
    private const SECRET_QUERY_PARAMETER_PATTERN = '/([?&](?:auth|token|access_token|api[_-]?key|logging[_-]?token|signature)=)[^&\s"\']+/i';

    private const IP_KEY_PATTERN = '/(^|[_\-.])ip($|[_\-.]|address)/';

    private const EMAIL_PATTERN = '/[a-z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+/i';
    private const IPV6_PATTERN = '/\b(?:[0-9a-f]{1,4}:){2,7}[0-9a-f]{1,4}\b/i';
    private const IPV4_PATTERN = '/\b(?:(?:25[0-5]|2[0-4]\d|1?\d?\d)\.){3}(?:25[0-5]|2[0-4]\d|1?\d?\d)\b/';
    
    private const PHONE_PATTERN = '/\+?\d(?:[\s-]?\d){8,}/';

    private const REDACTED = '***REDACTED***';

    public static function redactText(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $masked = preg_replace(self::CREDENTIAL_HEADER_PATTERN, '$1$2' . self::REDACTED, $text);
        $masked = preg_replace(self::BEARER_PATTERN, 'Bearer ' . self::REDACTED, $masked ?? $text);
        $masked = preg_replace(self::JWT_PATTERN, self::REDACTED, $masked ?? $text);
        $masked = preg_replace(self::SECRET_QUERY_PARAMETER_PATTERN, '$1' . self::REDACTED, $masked ?? $text);
        $masked = preg_replace(self::EMAIL_PATTERN, self::REDACTED, $masked ?? $text);
        $masked = preg_replace(self::IPV6_PATTERN, self::REDACTED, $masked);
        $masked = preg_replace(self::IPV4_PATTERN, self::REDACTED, $masked);
        $masked = preg_replace(self::PHONE_PATTERN, self::REDACTED, $masked);

        return $masked ?? $text;
    }

    public static function redactPayload(string $payload, int $maxBytes = 16384): string
    {
        if ($payload === '') {
            return '';
        }

        $decoded = json_decode($payload, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return self::truncate(self::redactText($payload), $maxBytes);
        }

        $redacted = self::redactStructure($decoded);
        $reencoded = json_encode($redacted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return self::truncate($reencoded === false ? self::REDACTED : $reencoded, $maxBytes);
    }

    public static function truncate(string $text, int $maxBytes): string
    {
        if (strlen($text) <= $maxBytes) {
            return $text;
        }

        $truncated = substr($text, 0, $maxBytes);
        $length = strlen($truncated);
        $i = $length;

        while ($i > 0 && (ord($truncated[$i - 1]) & 0xC0) === 0x80) {
            $i--;
        }

        if ($i > 0) {
            $leadByte = ord($truncated[$i - 1]);
            $sequenceLength = self::utf8SequenceLength($leadByte);

            if ($sequenceLength > 1 && ($length - ($i - 1)) < $sequenceLength) {
                $truncated = substr($truncated, 0, $i - 1);
            }
        }

        return $truncated;
    }

    private static function utf8SequenceLength(int $byte): int
    {
        if ($byte < 0x80) {
            return 1;
        }
        if (($byte & 0xE0) === 0xC0) {
            return 2;
        }
        if (($byte & 0xF0) === 0xE0) {
            return 3;
        }
        if (($byte & 0xF8) === 0xF0) {
            return 4;
        }

        return 1;
    }

    public static function redactJsonString(string $payload): string
    {
        if ($payload === '') {
            return '';
        }

        $decoded = json_decode($payload, true);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return sprintf('***NON-JSON-PAYLOAD (%d bytes)***', strlen($payload));
        }

        $redacted = self::redactStructure($decoded);
        $reencoded = json_encode($redacted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $reencoded === false ? self::REDACTED : $reencoded;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public static function redactStructure($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $result[$key] = $item === null ? null : self::REDACTED;

                continue;
            }

            $result[$key] = is_array($item) ? self::redactStructure($item) : $item;
        }

        return $result;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        if (preg_match(self::IP_KEY_PATTERN, $normalized) === 1) {
            return true;
        }

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (strpos($normalized, $fragment) !== false) {
                return true;
            }
        }

        $words = preg_split('/[^A-Za-z0-9]+|(?<=[a-z0-9])(?=[A-Z])/', $key, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words === false ? [] : $words as $word) {
            if (in_array(strtolower($word), self::SENSITIVE_KEY_WORDS, true)) {
                return true;
            }
        }

        return false;
    }
}
