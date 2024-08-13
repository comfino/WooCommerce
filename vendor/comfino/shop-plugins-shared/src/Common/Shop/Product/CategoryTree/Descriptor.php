<?php

namespace Comfino\Common\Shop\Product\CategoryTree;

final class Descriptor
{
    /**
     * @readonly
     * @var ComfinoExternal\\Comfino\Common\Shop\Product\CategoryTree\NodeIterator
     */
    public $nodes;

    /** @var Node[]|null
     * @readonly */
    public $index;
}
