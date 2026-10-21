<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Queue;

interface DeadLetterReporterInterface
{
    /**
     * @param \Comfino\Common\Backend\Queue\QueuedRequest $request
     * @param \Throwable $error
     */
    public function report($request, $error): void;
}
