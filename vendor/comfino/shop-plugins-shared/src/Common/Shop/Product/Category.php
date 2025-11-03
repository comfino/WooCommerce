<?php

declare(strict_types=1);

namespace Comfino\Common\Shop\Product;

final class Category
{
    /**
     * @readonly
     * @var int
     */
    public $id;
    /**
     * @readonly
     * @var string
     */
    public $name;
    /**
     * @readonly
     * @var int
     */
    public $position;
    /**
     * @var Category[]
     * @readonly
     */
    public $children;
    /**
     * @param Category[] $children
     */
    public function __construct(int $id, string $name, int $position, array $children)
    {
        $this->id = $id;
        $this->name = $name;
        $this->position = $position;
        $this->children = $children;
    }
}
