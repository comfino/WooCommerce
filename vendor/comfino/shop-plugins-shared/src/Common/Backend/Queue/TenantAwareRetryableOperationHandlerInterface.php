<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Queue;

interface TenantAwareRetryableOperationHandlerInterface extends RetryableOperationHandlerInterface
{
    /**
     * @param string|null $tenantKey
     * @throws \Throwable
     */
    public function executeForTenant($payload, $tenantKey): void;
}
