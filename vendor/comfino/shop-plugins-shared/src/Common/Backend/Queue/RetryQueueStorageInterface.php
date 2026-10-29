<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Queue;

interface RetryQueueStorageInterface
{
    /**
     * @param \Comfino\Common\Backend\Queue\QueuedRequest $request
     */
    public function enqueue($request): void;

    /**
     * @param int $limit
     * @param string|null $tenantKey
     * @param int|null $dueAt
     * @return QueuedRequest[]
     */
    public function peekBatch($limit, $tenantKey = null, $dueAt = null): array;

    /**
     * @param \Comfino\Common\Backend\Queue\QueuedRequest $request
     */
    public function update($request): void;

    /**
     * @param \Comfino\Common\Backend\Queue\QueuedRequest $request
     */
    public function remove($request): void;

    /**
     * @param string|null $tenantKey
     */
    public function count($tenantKey = null): int;

    /**
     * @param int|null $dueAt
     * @return list<string|null>
     */
    public function pendingTenantKeys($dueAt = null): array;
}
