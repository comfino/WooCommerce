<?php

namespace Comfino\Common\Shop\Product\CategoryTree;

final class Descriptor
{
    /**
     * @readonly
     * @var \Comfino\Common\Shop\Product\CategoryTree\NodeIterator
     */
    public $nodes;
    /**
     * @var Node[]|null
     * @readonly
     */
    public $index;
    /**
     * @param Node[]|null $index
     */
    public function __construct(NodeIterator $nodes, ?array $index)
    {
        $this->nodes = $nodes;
        $this->index = $index;
    }
}
