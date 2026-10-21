<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Clock;

interface ClockInterface
{
    public function now(): int;
}
