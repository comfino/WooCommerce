<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Queue;

interface JitterInterface
{
    /**
     * @param int $maxInclusive
     */
    public function random($maxInclusive): int;
}
