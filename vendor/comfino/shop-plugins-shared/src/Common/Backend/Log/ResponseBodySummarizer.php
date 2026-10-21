<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Log;

final class ResponseBodySummarizer
{
    public const MAX_LENGTH = 2000;

    private const HTML_PREFIX = '[HTML response] ';
    private const TRUNCATED_SUFFIX = '... [truncated]';

    public static function summarize(?string $body, int $maxLength = self::MAX_LENGTH): ?string
    {
        if ($body === null || $body === '') {
            return $body;
        }

        if (preg_match('/<\s*(?:!doctype|html|head|body|style|script)\b/i', $body) === 1) {
            $text = preg_replace('#<(style|script|svg|head)\b[^>]*>.*?</\1\s*>#is', ' ', $body) ?? $body;
            $text = preg_replace('/<[^>]*>/', ' ', $text) ?? $text;
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $body = self::HTML_PREFIX . trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        }

        return self::cap($body, $maxLength);
    }

    private static function cap(string $text, int $maxLength): string
    {
        return mb_strlen($text, 'UTF-8') > $maxLength
            ? mb_substr($text, 0, $maxLength, 'UTF-8') . self::TRUNCATED_SUFFIX
            : $text;
    }
}
