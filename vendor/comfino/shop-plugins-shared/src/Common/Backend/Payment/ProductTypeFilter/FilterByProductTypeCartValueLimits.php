<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Payment\ProductTypeFilter;

use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Common\Backend\Payment\ProductTypeFilterInterface;
use Comfino\Common\Shop\Cart;

class FilterByProductTypeCartValueLimits implements ProductTypeFilterInterface
{
    /**
     * @var LoanTypeEnum[]|null
     */
    private $allowedProductTypes;
    /**
     * @var int[]
     */
    private $minCartValueLimitsByProductType = [];
    /**
     * @var int[]
     */
    private $maxCartValueLimitsByProductType = [];
    /**
     * @param LoanTypeEnum[]|null $allowedProductTypes
     * @param int[] $minCartValueLimitsByProductType
     * @param int[] $maxCartValueLimitsByProductType
     */
    public function __construct(?array $allowedProductTypes = null, array $minCartValueLimitsByProductType = [], array $maxCartValueLimitsByProductType = [])
    {
        $this->allowedProductTypes = $allowedProductTypes;
        $this->minCartValueLimitsByProductType = $minCartValueLimitsByProductType;
        $this->maxCartValueLimitsByProductType = $maxCartValueLimitsByProductType;
    }

    /**
     * @param mixed[] $availableProductTypes
     * @param \Comfino\Common\Shop\Cart $cart
     */
    public function getAllowedProductTypes($availableProductTypes, $cart): array
    {
        $productTypes = $this->allowedProductTypes === null
            ? $availableProductTypes
            : array_intersect($this->allowedProductTypes, $availableProductTypes);

        $cartTotalValue = $cart->getTotalValue();
        $allowedProductTypes = [];

        foreach ($productTypes as $productType) {
            $productTypeKey = (string) $productType;

            if (array_key_exists($productTypeKey, $this->minCartValueLimitsByProductType) &&
                $cartTotalValue < $this->minCartValueLimitsByProductType[$productTypeKey]
            ) {
                continue;
            }

            if (array_key_exists($productTypeKey, $this->maxCartValueLimitsByProductType) &&
                $cartTotalValue > $this->maxCartValueLimitsByProductType[$productTypeKey]
            ) {
                continue;
            }

            $allowedProductTypes[] = $productType;
        }

        return $allowedProductTypes;
    }

    public function getAsArray(): array
    {
        return [
            'allowedProductTypes' => $this->allowedProductTypes,
            'minCartValueLimitsByProductType' => $this->minCartValueLimitsByProductType,
            'maxCartValueLimitsByProductType' => $this->maxCartValueLimitsByProductType,
        ];
    }
}
