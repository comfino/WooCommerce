<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Queue;

interface TenantPauseReporterInterface
{
    public const REASON_CREDENTIALS_REJECTED = 'credentials_rejected';
    
    public const REASON_CONSECUTIVE_FAILURES = 'consecutive_failures';

    /**
     * @param string|null $tenantKey
     * @param QueuedRequest $request
     * @param \Throwable $error
     * @param string $reason
     */
    public function reportPaused($tenantKey, $request, $error, $reason): void;
}
