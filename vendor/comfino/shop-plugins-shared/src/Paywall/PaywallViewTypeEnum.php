<?php

namespace Comfino\Paywall;

use Comfino\Enum;

class PaywallViewTypeEnum extends Enum
{
    public const PAYWALL_VIEW_FULL = 'full';
    public const PAYWALL_VIEW_LIST = 'list';

    /**
     * @param string $value
     * @param bool $strict
     * @return $this
     */
    public static function from($value, $strict = true): \Comfino\Enum
    {
        return new self($value, $strict);
    }
}
