<?php

namespace Comfino\Common\Backend\Payment\ProductTypeFilter;

use Comfino\Common\Backend\Payment\ProductTypeFilterInterface;
use Comfino\Common\Shop\Cart;

class FilterByCartValueLowerLimit implements ProductTypeFilterInterface
{
    /**
     * @var int[]
     * @readonly
     */
    private $cartValueLimitsByProductType;
    /**
     * @param int[] $cartValueLimitsByProductType ['PRODUCT_TYPE' => cart_value_limit]
     */
    public function __construct(array $cartValueLimitsByProductType)
    {
        $this->cartValueLimitsByProductType = $cartValueLimitsByProductType;
    }

    /**
     * @param mixed[] $availableProductTypes
     * @param \Comfino\Common\Shop\Cart $cart
     */
    public function getAllowedProductTypes($availableProductTypes, $cart): array
    {
        $allowedProductTypes = [];

        foreach ($availableProductTypes as $productType) {
            if (array_key_exists((string) $productType, $this->cartValueLimitsByProductType)) {
                if ($cart->getTotalValue() >= $this->cartValueLimitsByProductType[(string) $productType]) {
                    $allowedProductTypes[] = $productType;
                }
            } else {
                $allowedProductTypes[] = $productType;
            }
        }

        return $allowedProductTypes;
    }
}
