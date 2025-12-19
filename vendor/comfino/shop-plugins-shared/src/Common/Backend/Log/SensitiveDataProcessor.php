<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Log;

use ComfinoExternal\Monolog\Processor\ProcessorInterface;

final class SensitiveDataProcessor implements ProcessorInterface
{
    private const SENSITIVE_PATTERNS = [

        '/api[_-]?key/i',
        '/authorization/i',
        '/bearer/i',
        '/token/i',
        '/secret/i',
        '/signature/i',

        '/password/i',
        '/passwd/i',
        '/pwd/i',

        '/cr[_-]?signature/i',
        '/x-cr-signature/i',
        '/card[_-]?number/i',
        '/cvv/i',
        '/cvc/i',

        '/ssn/i',
        '/pesel/i',
        '/nip/i',

        '/session[_-]?id/i',
        '/csrf[_-]?token/i',
    ];

    private const SENSITIVE_HEADERS = [
        'authorization',
        'cr-signature',
        'x-cr-signature',
        'api-key',
        'x-api-key',
    ];

    /**
     * @param array $records
     * @return array
     */
    public function __invoke(array $records): array
    {
        $records['context'] = $this->sanitize($records['context'] ?? []);
        $records['extra'] = $this->sanitize($records['extra'] ?? []);

        return $records;
    }

    /**
     * @param array $data
     * @return array
     */
    private function sanitize(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value);
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
