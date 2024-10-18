<?php

namespace Comfino\Common\Shop\Product\CategoryTree;

interface BuildStrategyInterface
{
    public function build(): Descriptor;
}
