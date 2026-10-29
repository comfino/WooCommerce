<?php

declare(strict_types=1);

namespace Comfino\Common\Shop\Order;

final class PromoCode
{
    public const MAX_LENGTH = 64;

    public const REQUEST_FIELD = 'comfino_promo_code';

    /**
     * @param mixed $value
     */
    public static function normalize($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $code = html_entity_decode(strip_tags($value));

        $code = preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u', '', $code) ?? trim($code);

        if ($code === '' || mb_strlen($code) > self::MAX_LENGTH) {
            return null;
        }

        return $code;
    }
}
