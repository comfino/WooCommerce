<?php

namespace Comfino\Shop\Order\Cart;

class Product implements ProductInterface
{
    /**
     * @var string
     * @readonly
     */
    private $name;
    /**
     * @var int
     * @readonly
     */
    private $price;
    /**
     * @var string|null
     * @readonly
     */
    private $id;
    /**
     * @var string|null
     * @readonly
     */
    private $category;
    /**
     * @var string|null
     * @readonly
     */
    private $ean;
    /**
     * @var string|null
     * @readonly
     */
    private $photoUrl;
    /**
     * @var int[]|null
     * @readonly
     */
    private $categoryIds;
    /**
     * @var int|null
     * @readonly
     */
    private $netPrice;
    /**
     * @var int|null
     * @readonly
     */
    private $taxRate;
    /**
     * @var int|null
     * @readonly
     */
    private $taxValue;
    /**
     * @param string $name
     * @param int $price
     * @param string|null $id
     * @param string|null $category
     * @param string|null $ean
     * @param string|null $photoUrl
     * @param int[]|null $categoryIds
     * @param int|null $netPrice
     * @param int|null $taxRate
     * @param int|null $taxValue
     */
    public function __construct(string $name, int $price, ?string $id = null, ?string $category = null, ?string $ean = null, ?string $photoUrl = null, ?array $categoryIds = null, ?int $netPrice = null, ?int $taxRate = null, ?int $taxValue = null)
    {
        $this->name = $name;
        $this->price = $price;
        $this->id = $id;
        $this->category = $category;
        $this->ean = $ean;
        $this->photoUrl = $photoUrl;
        $this->categoryIds = $categoryIds;
        $this->netPrice = $netPrice;
        $this->taxRate = $taxRate;
        $this->taxValue = $taxValue;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getPrice(): int
    {
        return $this->price;
    }

    /**
     * @inheritDoc
     */
    public function getNetPrice(): ?int
    {
        return $this->netPrice;
    }

    /**
     * @inheritDoc
     */
    public function getTaxRate(): ?int
    {
        return $this->taxRate;
    }

    /**
     * @inheritDoc
     */
    public function getTaxValue(): ?int
    {
        return $this->taxValue;
    }

    /**
     * @inheritDoc
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function getCategory(): ?string
    {
        return $this->category;
    }

    /**
     * @inheritDoc
     */
    public function getEan(): ?string
    {
        return $this->ean;
    }

    /**
     * @inheritDoc
     */
    public function getPhotoUrl(): ?string
    {
        return $this->photoUrl;
    }

    /**
     * @inheritDoc
     */
    public function getCategoryIds(): ?array
    {
        return $this->categoryIds;
    }
}
