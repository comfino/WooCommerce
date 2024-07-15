<?php

namespace Comfino\Shop\Order;

interface SellerInterface
{
    /** @return string|null */
    public function getTaxId(): ?string;
}
