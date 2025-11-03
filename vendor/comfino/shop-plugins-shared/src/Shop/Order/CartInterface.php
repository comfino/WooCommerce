<?php

declare(strict_types=1);

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

    /** @return int|null */
    public function getDeliveryNetCost(): ?int;

    /** @return int|null */
    public function getDeliveryCostTaxRate(): ?int;

    /** @return int|null */
    public function getDeliveryCostTaxValue(): ?int;

    /** @return string|null */
    public function getCategory(): ?string;
}
