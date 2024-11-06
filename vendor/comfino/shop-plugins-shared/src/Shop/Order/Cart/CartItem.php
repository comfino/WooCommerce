<?php

namespace Comfino\Shop\Order\Cart;

class CartItem implements CartItemInterface
{
    /**
     * @var ProductInterface
     * @readonly
     */
    private $product;
    /**
     * @var int
     * @readonly
     */
    private $quantity;
    /**
     * @param ProductInterface $product
     * @param int $quantity
     */
    public function __construct(ProductInterface $product, int $quantity)
    {
        $this->product = $product;
        $this->quantity = $quantity;
    }

    /**
     * @inheritDoc
     */
    public function getProduct(): ProductInterface
    {
        return $this->product;
    }

    /**
     * @inheritDoc
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }
}
