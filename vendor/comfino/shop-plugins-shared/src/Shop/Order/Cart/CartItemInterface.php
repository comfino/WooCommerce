<?php

declare(strict_types=1);

namespace Comfino\Shop\Order\Cart;

interface CartItemInterface
{
    /** @return ProductInterface */
    public function getProduct(): ProductInterface;

    /** @return int */
    public function getQuantity(): int;
}
