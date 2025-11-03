<?php

declare(strict_types=1);

namespace Comfino\Common\Shop\Product\CategoryTree;

interface BuildStrategyInterface
{
    public function build(): Descriptor;
}
