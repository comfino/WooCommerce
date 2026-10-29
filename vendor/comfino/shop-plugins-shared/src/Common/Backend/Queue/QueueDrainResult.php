<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Queue;

final class QueueDrainResult
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
    public $remaining = 0;
    /**
     * @var bool
     */
    public $stoppedOnTransientFailure = false;
    /**
     * @var bool
     */
    public $skipped = false;
    /**
     * @var int
     */
    public $notDue = 0;
    
    public $pausedTenants;

    /**
     * @param int $processed
     * @param int $deadLettered
     * @param int $requeued
     * @param int $remaining
     * @param bool $stoppedOnTransientFailure
     * @param bool $skipped
     * @param list<string|null> $pausedTenants
     * @param int $notDue
     */
    public function __construct(
        int $processed = 0,
        int $deadLettered = 0,
        int $requeued = 0,
        int $remaining = 0,
        bool $stoppedOnTransientFailure = false,
        bool $skipped = false,
        array $pausedTenants = [],
        int $notDue = 0
    ) {
        $this->processed = $processed;
        $this->deadLettered = $deadLettered;
        $this->requeued = $requeued;
        $this->remaining = $remaining;
        $this->stoppedOnTransientFailure = $stoppedOnTransientFailure;
        $this->skipped = $skipped;
        $this->notDue = $notDue;
        $this->pausedTenants = $pausedTenants;
    }

    /**
     * @param int $remaining
     */
    public static function skipped(int $remaining): self
    {
        return new self(0, 0, 0, $remaining, false, true);
    }

    public function hasPausedTenants(): bool
    {
        return $this->pausedTenants !== [];
    }
}
