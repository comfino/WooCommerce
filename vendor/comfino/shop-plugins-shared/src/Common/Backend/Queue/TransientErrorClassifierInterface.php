<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Queue;

interface TransientErrorClassifierInterface
{
    /**
     * @param string $operationType
     * @param \Throwable $error
     */
    public function classify($operationType, $error): string;
}
