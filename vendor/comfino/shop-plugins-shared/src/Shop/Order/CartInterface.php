<?php

namespace Comfino\Shop\Order;

use Comfino\Shop\Order\Cart\CartItemInterface;

interface CartInterface
{
    /** @return CartItemInterface[] */
    public function getItems(): array;

    /** @return int */
    public function getTotalAmount(): int;

    /** @return int|null */
    public function getDeliveryCost(): ?int;

    /** @return string|null */
    public function getCategory(): ?string;
}
