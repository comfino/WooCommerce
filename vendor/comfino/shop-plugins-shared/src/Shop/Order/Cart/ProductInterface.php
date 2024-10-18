<?php

namespace Comfino\Shop\Order\Cart;

interface ProductInterface
{
    /** @return string */
    public function getName(): string;

    /** @return int */
    public function getPrice(): int;

    /** @return int|null */
    public function getNetPrice(): ?int;

    /** @return int|null */
    public function getTaxRate(): ?int;

    /** @return int|null */
    public function getTaxValue(): ?int;

    /** @return string|null */
    public function getId(): ?string;

    /** @return string|null */
    public function getCategory(): ?string;

    /** @return string|null */
    public function getEan(): ?string;

    /** @return string|null */
    public function getPhotoUrl(): ?string;

    /** @return int[]|null */
    public function getCategoryIds(): ?array;
}
