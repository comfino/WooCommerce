<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Clock;

final class SystemClock implements ClockInterface
{
    public function now(): int
    {
        return time();
    }
}
