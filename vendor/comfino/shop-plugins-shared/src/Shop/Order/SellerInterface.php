<?php

declare(strict_types=1);

namespace Comfino\Shop\Order;

interface SellerInterface
{
    /** @return string|null */
    public function getTaxId(): ?string;
}
