<?php

declare(strict_types=1);

namespace Comfino\View;

use Comfino\Shop\Order\CartInterface;
use Comfino\Shop\Order\CartTrait;

/**
 * Exposes the protected CartTrait::getCartAsArray() helper to callers outside the API
 * Request hierarchy, so the paywall bootstrap data can carry the same cart shape the
 * v3 /paywall-product-details endpoint expects.
 */
final class PaywallCartSerializer
{
    use CartTrait;

    public static function toArray(CartInterface $cart): array
    {
        return (new self())->getCartAsArray($cart);
    }
}
