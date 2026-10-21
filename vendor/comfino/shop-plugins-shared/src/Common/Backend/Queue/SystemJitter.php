<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Queue;

final class SystemJitter implements JitterInterface
{
    /**
     * @param int $maxInclusive
     */
    public function random($maxInclusive): int
    {
        return $maxInclusive > 0 ? random_int(0, $maxInclusive) : 0;
    }
}
