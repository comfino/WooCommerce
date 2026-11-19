<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Log;

use Comfino\Api\SensitiveDataRedactor;
use ComfinoExternal\Monolog\Processor\ProcessorInterface;

final class SensitiveDataProcessor implements ProcessorInterface
{
    private const SENSITIVE_PATTERNS = [
        
        '/api[_-]?key/i',
        '/authorization/i',
        '/bearer/i',
        '/token/i',
        '/secret/i',

        '/password/i',
        '/passwd/i',

        '/card[_-]?number/i',
        '/cvv/i',

        '/pesel/i',  
        '/e[_-]?mail/i',
        '/phone/i',
        '/first[_-]?name/i',
        '/last[_-]?name/i',
        '/street/i',
        '/postal[_-]?code/i',
        '/tax[_-]?id/i',
        '/building[_-]?number/i',
        '/apartment[_-]?number/i',
        '/(^|[_\-.])ip($|[_\-.]|address)/i',

        '/session[_-]?id/i',
        '/csrf[_-]?token/i',
    ];

    private const SENSITIVE_WORDS = ['ssn', 'nip', 'pwd', 'cvc', 'city', 'zip'];

    private const SENSITIVE_HEADERS = [
        'authorization',
        'proxy-authorization',
        'api-key',
        'x-api-key',
        'auth',
        'cookie',
        'set-cookie',
    ];

    private const UNMASKED_KEY_PATTERNS = [
        '/cr-signature/i',
    ];

    /**
     * @param array $records
     * @return array
     */
    public function __invoke(array $records): array
    {
        $records['context'] = $this->sanitize($records['context'] ?? []);
        $records['extra'] = $this->sanitize($records['extra'] ?? []);

        if (isset($records['message']) && is_string($records['message'])) {
            $records['message'] = SensitiveDataRedactor::redactText($records['message']);
        }

        return $records;
    }

    /**
     * @param string $text
     * @return string
     */
    public function sanitizeText($text): string
    {
        $text = (string) preg_replace(
            '/\b(authorization|proxy-authorization|cookie|set-cookie)(\s*[=:]\s*)[^\r\n]+/i',
            '$1$2[REDACTED]',
            $text
        );
        $text = (string) preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer [REDACTED]', $text);

        $text = (string) preg_replace_callback(
            '/"([A-Za-z0-9_-]+)"\s*:\s*("(?:[^"\\\\]|\\\\.)*"|-?[0-9.]+)/',
            function (array $m) : string {
                return $this->isSensitiveTextKey($m[1]) ? '"' . $m[1] . '":"[REDACTED]"' : $m[0];
            },
            $text
        );

        $text = (string) preg_replace_callback(
            '/\b([A-Za-z0-9_-]+)(\s*[=:]\s*)([^\s&,;"]+)/',
            function (array $m) : string {
                return $this->isSensitiveTextKey($m[1]) ? $m[1] . $m[2] . '[REDACTED]' : $m[0];
            },
            $text
        );

        $text = (string) preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '[REDACTED]', $text);

        return (string) preg_replace('/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]*/', '[REDACTED]', $text);
    }

    private function isSensitiveTextKey(string $key): bool
    {
        foreach (self::UNMASKED_KEY_PATTERNS as $keyPattern) {
            if (preg_match($keyPattern, $key)) {
                return false;
            }
        }

        return $this->isSensitiveKey($key);
    }

    /**
     * @param array $data
     * @param string|int|null $parentKey
     * @return array
     */
    private function sanitize(array $data, $parentKey = null): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            if (is_string($key) || is_string($parentKey)) {
                foreach (self::UNMASKED_KEY_PATTERNS as $keyPattern) {
                    if ((is_string($key) && preg_match($keyPattern, $key)) || (is_string($parentKey) && preg_match($keyPattern, $parentKey))) {
                        $sanitized[$key] = $value;

                        continue 2;
                    }
                }
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value, $key);
            } elseif (is_string($value)) {
                $sanitized[$key] = $this->sanitizeString($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * @param string $key
     * @return bool
     */
    private function isSensitiveKey(string $key): bool
    {
        $keyLower = strtolower($key);

        if (in_array($keyLower, self::SENSITIVE_HEADERS, true)) {
            return true;
        }

        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            if (preg_match($pattern, $key)) {
                return true;
            }
        }

        $words = preg_split('/[^A-Za-z0-9]+|(?<=[a-z0-9])(?=[A-Z])/', $key, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words === false ? [] : $words as $word) {
            if (in_array(strtolower($word), self::SENSITIVE_WORDS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $value
     * @return string
     */
    private function sanitizeString(string $value): string
    {
        if (strlen($value) < 8) {
            return $value;
        }

        if ($this->looksLikeSensitiveValue($value)) {
            return $this->maskString($value);
        }

        return $value;
    }

    /**
     * @param string $value
     * @return bool
     */
    private function looksLikeSensitiveValue(string $value): bool
    {
        if (preg_match('/^[a-zA-Z0-9_-]{32,}$/', $value)) {
            return true;
        }

        if (preg_match('/^eyJ[a-zA-Z0-9_-]+\.eyJ[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+$/', $value)) {
            return true;
        }

        if (preg_match('/^[A-Za-z0-9+\/]{20,}={0,2}$/', $value) && strlen($value) > 30) {
            return true;
        }

        return false;
    }

    /**
     * @param string $value
     * @return string
     */
    private function maskString(string $value): string
    {
        $length = strlen($value);

        if ($length <= 8) {
            return '[REDACTED]';
        }

        return substr($value, 0, 4) . '...' . substr($value, -4);
    }
}
