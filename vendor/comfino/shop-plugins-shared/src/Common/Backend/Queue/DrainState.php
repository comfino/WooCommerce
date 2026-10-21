<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Queue;

final class DrainState
{
    /**
     * @var int
     */
    public $processed = 0;

    /**
     * @var int
     */
    public $deadLettered = 0;

    /**
     * @var int
     */
    public $requeued = 0;

    /**
     * @var int
     */
    public $notDue = 0;

    public $pausedTenants = [];

    private $failureStreaks = [];

    /**
     * @param string|null $tenantKey
     * @return int
     */
    public function recordFailure(?string $tenantKey): int
    {
        $key = $this->key($tenantKey);

        return $this->failureStreaks[$key] = ($this->failureStreaks[$key] ?? 0) + 1;
    }

    /**
     * @param string|null $tenantKey
     */
    public function clearFailures(?string $tenantKey): void
    {
        unset($this->failureStreaks[$this->key($tenantKey)]);
    }

    /**
     * @param string|null $tenantKey
     */
    public function pauseTenant(?string $tenantKey): void
    {
        if (!in_array($tenantKey, $this->pausedTenants, true)) {
            $this->pausedTenants[] = $tenantKey;
        }
    }

    /**
     * @param string|null $tenantKey
     */
    private function key(?string $tenantKey): string
    {
        return $tenantKey ?? "\0unscoped";
    }
}
