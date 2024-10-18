<?php

namespace Comfino\Common\Shop;

use Comfino\Shop\Order\Cart\CartItemInterface;

class Cart
{
    /**
     * @readonly
     * @var int
     */
    private $totalValue;
    /**
     * @readonly
     * @var int
     */
    private $deliveryCost;
    /**
     * @var CartItemInterface[]
     * @readonly
     */
    private $cartItems;
    /** @var int[]|null  */
    private $cartCategoryIds;

    /**
     * @param CartItemInterface[] $cartItems
     */
    public function __construct(int $totalValue, int $deliveryCost, array $cartItems)
    {
        $this->totalValue = $totalValue;
        $this->deliveryCost = $deliveryCost;
        $this->cartItems = $cartItems;
    }

    public function getTotalValue(): int
    {
        return $this->totalValue;
    }

    public function getDeliveryCost(): int
    {
        return $this->deliveryCost;
    }

    /**
     * @return CartItemInterface[]
     */
    public function getCartItems(): array
    {
        return $this->cartItems;
    }

    /**
     * @return int[]
     */
    public function getCartCategoryIds(): array
    {
        if ($this->cartCategoryIds !== null) {
            return $this->cartCategoryIds;
        }

        $categoryIds = [];

        foreach ($this->cartItems as $cartItem) {
            if (($productCategoryIds = $cartItem->getProduct()->getCategoryIds()) !== null) {
                $categoryIds[] = $productCategoryIds;
            }
        }

        return ($this->cartCategoryIds = array_unique(array_merge([], ...$categoryIds), SORT_NUMERIC));
    }

    /**
     * @param bool $withNulls
     */
    public function getAsArray($withNulls = true): array
    {
        return [
            'totalAmount' => $this->totalValue,
            'deliveryCost' => $this->deliveryCost,
            'products' => array_map(
                static function (CartItemInterface $cartItem) use ($withNulls): array {
                    $product = [
                        'name' => $cartItem->getProduct()->getName(),
                        'quantity' => $cartItem->getQuantity(),
                        'price' => $cartItem->getProduct()->getPrice(),
                        'netPrice' => $cartItem->getProduct()->getNetPrice(),
                        'vatRate' => $cartItem->getProduct()->getTaxRate(),
                        'vatAmount' => $cartItem->getProduct()->getTaxValue(),
                        'externalId' => $cartItem->getProduct()->getId(),
                        'category' => $cartItem->getProduct()->getCategory(),
                        'ean' => $cartItem->getProduct()->getEan(),
                        'photoUrl' => $cartItem->getProduct()->getPhotoUrl(),
                    ];

                    return $withNulls ? $product : array_filter($product, static function ($productFieldValue) : bool {
                        return $productFieldValue !== null;
                    });
                },
                $this->cartItems
            ),
        ];
    }
}
