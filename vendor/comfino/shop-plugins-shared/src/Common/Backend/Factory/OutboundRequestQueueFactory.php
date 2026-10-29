<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Factory;

use Comfino\Common\Backend\Clock\ClockInterface;
use Comfino\Common\Backend\Queue\ApiTransientErrorClassifier;
use Comfino\Common\Backend\Queue\DeadLetterReporterInterface;
use Comfino\Common\Backend\Queue\JitterInterface;
use Comfino\Common\Backend\Queue\OutboundRequestQueue;
use Comfino\Common\Backend\Queue\RetryableOperationHandlerInterface;
use Comfino\Common\Backend\Queue\RetryQueueStorageInterface;
use Comfino\Common\Backend\Queue\TenantPauseReporterInterface;
use Comfino\Common\Backend\Queue\TransientErrorClassifierInterface;
use ComfinoExternal\Psr\Log\LoggerInterface;

final class OutboundRequestQueueFactory
{
    /**
     * @param int $maxAttempts
     * @param int $baseRetryDelaySeconds
     * @param int $maxRetryDelaySeconds
     * @param int $maxConsecutiveTenantFailures
     * @param int $tenantPauseSeconds
     * @throws \InvalidArgumentException
     */
    public function create(
        RetryQueueStorageInterface $storage,
        array $handlers,
        ?LoggerInterface $logger = null,
        ?DeadLetterReporterInterface $deadLetterReporter = null,
        ?ClockInterface $clock = null,
        ?TransientErrorClassifierInterface $classifier = null,
        int $maxAttempts = 10,
        ?JitterInterface $jitter = null,
        int $baseRetryDelaySeconds = 60,
        int $maxRetryDelaySeconds = 3600,
        int $maxConsecutiveTenantFailures = 1,
        ?TenantPauseReporterInterface $pauseReporter = null,
        int $tenantPauseSeconds = 900
    ): OutboundRequestQueue {
        if ($handlers === []) {
            throw new \InvalidArgumentException('At least one handler must be registered.');
        }

        $queue = new OutboundRequestQueue(
            $storage,
            $classifier ?? new ApiTransientErrorClassifier(),
            $clock,
            $logger,
            $deadLetterReporter,
            $maxAttempts,
            $jitter,
            $baseRetryDelaySeconds,
            $maxRetryDelaySeconds,
            $maxConsecutiveTenantFailures,
            $pauseReporter,
            $tenantPauseSeconds
        );

        foreach ($handlers as $operationType => $handler) {
            $queue->registerHandler((string) $operationType, $handler);
        }

        return $queue;
    }
}
