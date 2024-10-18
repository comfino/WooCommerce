<?php

namespace Comfino\Common\Shop;

interface OrderStatusAdapterInterface
{
    /**
     * @param string $orderId
     * @param string $status
     */
    public function setStatus($orderId, $status): void;
}
