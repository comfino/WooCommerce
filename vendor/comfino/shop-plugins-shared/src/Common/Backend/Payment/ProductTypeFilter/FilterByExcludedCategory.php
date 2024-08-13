<?php

namespace Comfino\Common\Backend\Payment\ProductTypeFilter;

use Comfino\Common\Backend\Payment\ProductTypeFilterInterface;
use Comfino\Common\Shop\Cart;
use Comfino\Common\Shop\Product\CategoryFilter;

class FilterByExcludedCategory implements ProductTypeFilterInterface
{
    /**
     * @readonly
     * @var ComfinoExternal\\Comfino\Common\Shop\Product\CategoryFilter
     */
    private $categoryFilter;
    /**
     * @var int[][]
     * @readonly
     */
    private $excludedCategoryIdsByProductType;
    /**
     * @param int[][] $excludedCategoryIdsByProductType ['PRODUCT_TYPE' => [excluded_category_ids]]
     */
    public function __construct(CategoryFilter $categoryFilter, array $excludedCategoryIdsByProductType)
    {
        $this->categoryFilter = $categoryFilter;
        $this->excludedCategoryIdsByProductType = $excludedCategoryIdsByProductType;
    }

    /**
     * @param mixed[] $availableProductTypes
     * @param ComfinoExternal\\Comfino\Common\Shop\Cart $cart
     */
    public function getAllowedProductTypes($availableProductTypes, $cart): array
    {
        $allowedProductTypes = [];

        foreach ($availableProductTypes as $productType) {
            if (array_key_exists((string) $productType, $this->excludedCategoryIdsByProductType)) {
                if ($this->categoryFilter->isCartValid($cart, $this->excludedCategoryIdsByProductType[(string) $productType])) {
                    $allowedProductTypes[] = $productType;
                }
            } else {
                $allowedProductTypes[] = $productType;
            }
        }

        return $allowedProductTypes;
    }
}
