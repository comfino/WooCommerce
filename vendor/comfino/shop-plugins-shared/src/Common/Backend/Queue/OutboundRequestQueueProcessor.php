<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Queue;

use ComfinoExternal\Cache\TagInterop\TaggableCacheItemInterface;
use Comfino\Common\Backend\Clock\ClockInterface;
use Comfino\Common\Backend\Clock\SystemClock;
use ComfinoExternal\Psr\Cache\CacheItemPoolInterface;

final class OutboundRequestQueueProcessor
{
    /**
     * @var OutboundRequestQueue
     */
    private $queue;
    /**
     * @var int
     */
    private $defaultBatchSize = 20;
    /**
     * @var int
     */
    private $cooldownSeconds = 300;
    /**
     * @var CacheItemPoolInterface|null
     */
    private $cachePool;
    /**
     * @var string|null
     */
    private $tenantKey;
    private const COOLDOWN_CACHE_KEY = 'outbound_queue_cooldown_until';

    public const CACHE_TAG = 'comfino_queue';

    /**
     * @var \Comfino\Common\Backend\Clock\ClockInterface
     */
    private $clock;
    /**
     * @var \Comfino\Common\Backend\Queue\JitterInterface
     */
    private $jitter;

    /**
     * @param OutboundRequestQueue $queue
     * @param ClockInterface|null $clock
     * @param JitterInterface|null $jitter
     * @param int $defaultBatchSize
     * @param int $cooldownSeconds
     * @param CacheItemPoolInterface|null $cachePool
     * @param string|null $tenantKey
     */
    public function __construct(
        OutboundRequestQueue $queue,
        ?ClockInterface $clock = null,
        ?JitterInterface $jitter = null,
        int $defaultBatchSize = 20,
        int $cooldownSeconds = 300,
        ?CacheItemPoolInterface $cachePool = null,
        ?string $tenantKey = null
    ) {
        $this->queue = $queue;
        $this->defaultBatchSize = $defaultBatchSize;
        $this->cooldownSeconds = $cooldownSeconds;
        $this->cachePool = $cachePool;
        $this->tenantKey = $tenantKey;
        $this->clock = $clock ?? new SystemClock();
        $this->jitter = $jitter ?? new SystemJitter();
    }

    /**
     * @param int|null $batchSize
     */
    public function process(?int $batchSize = null): QueueDrainResult
    {
        if ($this->isCoolingDown()) {
            return QueueDrainResult::skipped($this->queue->pendingCount($this->tenantKey));
        }

        $result = $this->queue->process($batchSize ?? $this->defaultBatchSize, $this->tenantKey);

        if ($result->stoppedOnTransientFailure) {
            $this->beginCooldown();
        }

        return $result;
    }

    private function isCoolingDown(): bool
    {
        if ($this->cachePool === null) {
            return false;
        }

        try {
            $item = $this->cachePool->getItem(self::COOLDOWN_CACHE_KEY);

            return $item->isHit() && $this->clock->now() < (int) $item->get();
        } catch (\Throwable $exception) {
            return false; 
        }
    }

    private function beginCooldown(): void
    {
        if ($this->cachePool === null) {
            return;
        }

        try {
            $jitteredCooldown = $this->cooldownSeconds + $this->jitter->random((int) ceil($this->cooldownSeconds / 2));

            $item = $this->cachePool->getItem(self::COOLDOWN_CACHE_KEY);
            $item->set($this->clock->now() + $jitteredCooldown);
            $item->expiresAfter($jitteredCooldown);

            if ($item instanceof TaggableCacheItemInterface) {
                $item->setTags([self::CACHE_TAG]);
            }

            $this->cachePool->save($item);
        } catch (\Throwable $exception) {
        }
    }
}
