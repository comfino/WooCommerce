<?php

declare(strict_types=1);

namespace Comfino\Shop\Order;

class Seller implements SellerInterface
{
    /**
     * @var string|null
     * @readonly
     */
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
        return $this->taxId !== null ? trim(strip_tags($this->taxId)) : null;
    }
}
