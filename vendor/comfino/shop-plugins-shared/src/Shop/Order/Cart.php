<?php

namespace Comfino\Shop\Order;

use Comfino\Shop\Order\Cart\CartItemInterface;

class Cart implements CartInterface
{
    /** @var CartItemInterface[] */
    private $items;
    /** @var int */
    private $totalAmount;
    /** @var int|null */
    private $deliveryCost;
    /** @var string|null */
    private $category;

    /**
     * @param CartItemInterface[] $items
     * @param int $totalAmount
     * @param int|null $deliveryCost
     * @param string|null $category
     */
    public function __construct(array $items, int $totalAmount, ?int $deliveryCost = null, ?string $category = null)
    {
        $this->items = $items;
        $this->totalAmount = $totalAmount;
        $this->deliveryCost = $deliveryCost;
        $this->category = $category;
    }

    /**
     * @inheritDoc
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @inheritDoc
     */
    public function getTotalAmount(): int
    {
        return $this->totalAmount;
    }

    /**
     * @inheritDoc
     */
    public function getDeliveryCost(): ?int
    {
        return $this->deliveryCost;
    }

    /**
     * @inheritDoc
     */
    public function getCategory(): ?string
    {
        return $this->category;
    }
}
