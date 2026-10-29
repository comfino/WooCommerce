<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Queue;

interface RetryableOperationHandlerInterface
{
    /**
     * @throws \Throwable
     */
    public function execute($payload): void;
}
