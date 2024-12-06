<?php

namespace Comfino\Common\Backend\Payment\ProductTypeFilter;

use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Common\Backend\Payment\ProductTypeFilterInterface;
use Comfino\Common\Shop\Cart;

class FilterByProductType implements ProductTypeFilterInterface
{
    /**
     * @var LoanTypeEnum[]
     * @readonly
     */
    private $allowedProductTypes;
    /**
     * @param LoanTypeEnum[] $allowedProductTypes
     */
    public function __construct(array $allowedProductTypes)
    {
        $this->allowedProductTypes = $allowedProductTypes;
    }

    /**
     * @param mixed[] $availableProductTypes
     * @param \Comfino\Common\Shop\Cart $cart
     */
    public function getAllowedProductTypes($availableProductTypes, $cart): array
    {
        return array_intersect($this->allowedProductTypes, $availableProductTypes);
    }

    public function getAsArray(): array
    {
        return ['allowedProductTypes' => $this->allowedProductTypes];
    }
}
