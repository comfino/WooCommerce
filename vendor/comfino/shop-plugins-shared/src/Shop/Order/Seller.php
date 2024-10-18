<?php

namespace Comfino\Shop\Order;

class Seller implements SellerInterface
{
    /** @var string|null */
    private $taxId;

    /**
     * @param string|null $taxId
     */
    public function __construct(?string $taxId)
    {
        $this->taxId = $taxId;
    }

    /**
     * @inheritDoc
     */
    public function getTaxId(): ?string
    {
        return $this->taxId;
    }
}
