<?php

declare(strict_types=1);

namespace Comfino\FinancialProduct\Filter;

use Comfino\Common\Backend\Payment\ProductTypeFilterInterface;
use Comfino\Common\Shop\Cart;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Global product ID blacklist filter.
 *
 * If the cart contains any product whose ID is on the excluded list, all Comfino
 * financial product types are hidden (no payment option is offered). When the list
 * is empty or no cart item matches, all available product types are returned unchanged.
 */
class FilterByExcludedProductId implements ProductTypeFilterInterface
{
    /**
     * @var int[]
     */
    private $excludedProductIds;

    /**
     * @param int[] $excludedProductIds
     */
    public function __construct(array $excludedProductIds)
    {
        $this->excludedProductIds = array_map('intval', $excludedProductIds);
    }

    /**
     * @param mixed[] $availableProductTypes
     * @param \Comfino\Common\Shop\Cart $cart
     */
    public function getAllowedProductTypes($availableProductTypes, $cart): array
    {
        if (empty($this->excludedProductIds)) {
            return $availableProductTypes;
        }

        foreach ($cart->getCartItems() as $cartItem) {
            if (in_array((int) $cartItem->getProduct()->getId(), $this->excludedProductIds, true)) {
                return [];
            }
        }

        return $availableProductTypes;
    }

    public function getAsArray(): array
    {
        return ['excludedProductIds' => $this->excludedProductIds];
    }
}
