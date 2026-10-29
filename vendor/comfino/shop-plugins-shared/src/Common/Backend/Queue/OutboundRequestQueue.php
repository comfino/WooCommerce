<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Queue;

use Comfino\Common\Backend\Clock\ClockInterface;
use Comfino\Common\Backend\Clock\SystemClock;
use ComfinoExternal\Psr\Log\LoggerInterface;

final class OutboundRequestQueue
{
    /**
     * @var RetryQueueStorageInterface
     */
    private $storage;
    /**
     * @var TransientErrorClassifierInterface
     */
    private $classifier;
    /**
     * @var LoggerInterface|null
     */
    private $logger;
    /**
     * @var DeadLetterReporterInterface|null
     */
    private $deadLetterReporter;
    /**
     * @var int
     */
    private $maxAttempts = 10;
    /**
     * @var int
     */
    private $baseRetryDelaySeconds = 60;
    /**
     * @var int
     */
    private $maxRetryDelaySeconds = 3600;
    /**
     * @var int
     */
    private $maxConsecutiveTenantFailures = 1;
    /**
     * @var TenantPauseReporterInterface|null
     */
    private $pauseReporter;
    /**
     * @var int
     */
    private $tenantPauseSeconds = 900;
    
    private $handlers = [];

    /**
     * @var \Comfino\Common\Backend\Clock\ClockInterface
     */
    private $clock;
    /**
     * @var \Comfino\Common\Backend\Queue\JitterInterface
     */
    private $jitter;

    /**
     * @param RetryQueueStorageInterface $storage
     * @param TransientErrorClassifierInterface $classifier
     * @param ClockInterface|null $clock
     * @param LoggerInterface|null $logger
     * @param DeadLetterReporterInterface|null $deadLetterReporter
     * @param int $maxAttempts
     * @param JitterInterface|null $jitter
     * @param int $baseRetryDelaySeconds
     * @param int $maxRetryDelaySeconds
     * @param int $maxConsecutiveTenantFailures
     * @param TenantPauseReporterInterface|null $pauseReporter
     * @param int $tenantPauseSeconds
     */
    public function __construct(
        RetryQueueStorageInterface $storage,
        TransientErrorClassifierInterface $classifier,
        ?ClockInterface $clock = null,
        ?LoggerInterface $logger = null,
        ?DeadLetterReporterInterface $deadLetterReporter = null,
        int $maxAttempts = 10,
        ?JitterInterface $jitter = null,
        int $baseRetryDelaySeconds = 60,
        int $maxRetryDelaySeconds = 3600,
        int $maxConsecutiveTenantFailures = 1,
        ?TenantPauseReporterInterface $pauseReporter = null,
        int $tenantPauseSeconds = 900
    ) {
        $this->storage = $storage;
        $this->classifier = $classifier;
        $this->logger = $logger;
        $this->deadLetterReporter = $deadLetterReporter;
        $this->maxAttempts = $maxAttempts;
        $this->baseRetryDelaySeconds = $baseRetryDelaySeconds;
        $this->maxRetryDelaySeconds = $maxRetryDelaySeconds;
        $this->maxConsecutiveTenantFailures = $maxConsecutiveTenantFailures;
        $this->pauseReporter = $pauseReporter;
        $this->tenantPauseSeconds = $tenantPauseSeconds;
        if ($maxAttempts < 1) {
            throw new \InvalidArgumentException('The maxAttempts must be at least 1.');
        }

        if ($maxConsecutiveTenantFailures < 1) {
            throw new \InvalidArgumentException('The maxConsecutiveTenantFailures must be at least 1.');
        }

        if ($baseRetryDelaySeconds < 0 || $maxRetryDelaySeconds < 0) {
            throw new \InvalidArgumentException('Retry delays cannot be negative.');
        }

        $this->clock = $clock ?? new SystemClock();
        $this->jitter = $jitter ?? new SystemJitter();
    }

    /**
     * @param string $operationType
     * @param RetryableOperationHandlerInterface $handler
     */
    public function registerHandler(string $operationType, RetryableOperationHandlerInterface $handler): void
    {
        $this->handlers[$operationType] = $handler;
    }

    /**
     * @param string $operationType
     * @param string|null $tenantKey
     * @return string
     */
    public function submit(string $operationType, array $payload, ?string $tenantKey = null): string
    {
        $handler = $this->handler($operationType);

        try {
            $this->dispatch($handler, $payload, $tenantKey);

            return SubmitResult::SENT_IMMEDIATELY;
        } catch (\Throwable $error) {
            switch ($this->classifier->classify($operationType, $error)) {
                case QueueErrorDisposition::TREAT_AS_SUCCESS:
                    return $this->onSubmitAbsorbed($operationType, $payload, $error);
                case QueueErrorDisposition::DROP_PERMANENT:
                    return $this->onSubmitDropped($operationType, $payload, $error, $tenantKey);
                case QueueErrorDisposition::RETRY:
                case QueueErrorDisposition::PAUSE_TENANT:
                    return $this->onSubmitDeferred($operationType, $payload, $error, $tenantKey);
            }
        }
    }

    /**
     * @param string $operationType
     * @param string|null $tenantKey
     */
    public function enqueue(string $operationType, array $payload, ?string $tenantKey = null): void
    {
        $this->handler($operationType); 

        $this->storage->enqueue(QueuedRequest::create($operationType, $payload, $this->clock->now(), $tenantKey));
    }

    /**
     * @param int $maxItems
     * @param string|null $tenantKey
     */
    public function process(int $maxItems, ?string $tenantKey = null): QueueDrainResult
    {
        if ($maxItems < 1) {
            return QueueDrainResult::skipped($this->storage->count($tenantKey));
        }

        $now = $this->clock->now();
        $partitions = $this->duePartitions($maxItems, $now, $tenantKey);

        if ($partitions === []) {
            return new QueueDrainResult(0, 0, 0, $this->storage->count($tenantKey));
        }

        $state = new DrainState();
        $budget = $maxItems;

        while ($budget > 0 && $partitions !== []) {
            foreach (array_keys($partitions) as $partitionKey) {
                if ($budget < 1) {
                    break;
                }

                $request = array_shift($partitions[$partitionKey]);

                if ($request === null) {
                    unset($partitions[$partitionKey]);

                    continue;
                }

                $budget--;

                if (!$request->isDue($now)) {
                    $state->notDue++;

                    continue;
                }

                if (!$this->deliver($request, $state, $now)) {
                    unset($partitions[$partitionKey]);
                }

                if (isset($partitions[$partitionKey]) && $partitions[$partitionKey] === []) {
                    unset($partitions[$partitionKey]);
                }
            }
        }

        return new QueueDrainResult(
            $state->processed,
            $state->deadLettered,
            $state->requeued,
            $this->storage->count($tenantKey),
            
            $state->pausedTenants !== [] && $state->processed === 0,
            false,
            $state->pausedTenants,
            $state->notDue
        );
    }

    /**
     * @param string|null $tenantKey
     */
    public function pendingCount(?string $tenantKey = null): int
    {
        return $this->storage->count($tenantKey);
    }

    private function onSubmitAbsorbed(string $operationType, array $payload, \Throwable $error): string
    {
        $this->log('debug', '[REQUEST_QUEUE] submit absorbed non-failure', $operationType, $payload, $error);

        return SubmitResult::SENT_IMMEDIATELY;
    }

    private function onSubmitDropped(string $operationType, array $payload, \Throwable $error, ?string $tenantKey): string
    {
        $this->log('error', '[REQUEST_QUEUE] submit dropped (permanent)', $operationType, $payload, $error, $tenantKey);

        ($nullsafeVariable1 = $this->deadLetterReporter) ? $nullsafeVariable1->report(QueuedRequest::create($operationType, $payload, $this->clock->now(), $tenantKey), $error) : null;

        return SubmitResult::DROPPED_PERMANENT;
    }

    private function onSubmitDeferred(string $operationType, array $payload, \Throwable $error, ?string $tenantKey): string
    {
        $now = $this->clock->now();

        $this->storage->enqueue(
            QueuedRequest::create($operationType, $payload, $now, $tenantKey)
                ->withAttemptFailure($this->describe($error), $this->nextAttemptAt($now, 1))
        );

        $this->log('warning', '[REQUEST_QUEUE] submit deferred to queue', $operationType, $payload, $error, $tenantKey);

        return SubmitResult::QUEUED;
    }

    /**
     * @param int $maxItems
     * @param int $now
     * @param string|null $tenantKey
     * @return array<string,
     */
    private function duePartitions(int $maxItems, int $now, ?string $tenantKey): array
    {
        $tenantKeys = $tenantKey !== null ? [$tenantKey] : $this->storage->pendingTenantKeys($now);

        $partitions = [];

        foreach ($tenantKeys as $key) {
            $batch = $this->storage->peekBatch($maxItems, $key, $now);

            if ($batch !== []) {
                $partitions[$this->partitionKey($key)] = $batch;
            }
        }

        return $partitions;
    }

    /**
     * @param QueuedRequest $request
     * @param DrainState $state
     * @param int $now
     * @return bool
     */
    private function deliver(QueuedRequest $request, DrainState $state, int $now): bool
    {
        $handler = $this->handlers[$request->operationType] ?? null;

        if ($handler === null) {
            $this->log(
                'error',
                '[REQUEST_QUEUE] No handler for queued operation.',
                $request->operationType,
                $request->payload,
                null,
                $request->tenantKey
            );

            $this->storage->remove($request);

            $state->deadLettered++;

            return true;
        }

        try {
            $this->dispatch($handler, $request->payload, $request->tenantKey);
            $this->storage->remove($request);

            $state->processed++;
            $state->clearFailures($request->tenantKey);

            return true;
        } catch (\Throwable $error) {
            return $this->handleFailure($request, $error, $state, $now);
        }
    }

    /**
     * @param QueuedRequest $request
     * @param \Throwable $error
     * @param DrainState $state
     * @param int $now
     * @return bool
     */
    private function handleFailure(QueuedRequest $request, \Throwable $error, DrainState $state, int $now): bool
    {
        switch ($this->classifier->classify($request->operationType, $error)) {
            case QueueErrorDisposition::TREAT_AS_SUCCESS:
                return $this->onDeliveryAbsorbed($request, $state);
            case QueueErrorDisposition::DROP_PERMANENT:
                return $this->onDeliveryDropped($request, $error, $state);
            case QueueErrorDisposition::PAUSE_TENANT:
                return $this->onDeliveryPaused($request, $error, $state, $now);
            case QueueErrorDisposition::RETRY:
                return $this->onDeliveryRetried($request, $error, $state, $now);
        }
    }

    /**
     * @param QueuedRequest $request
     * @param DrainState $state
     * @return bool
     */
    private function onDeliveryAbsorbed(QueuedRequest $request, DrainState $state): bool
    {
        $this->storage->remove($request);

        $state->processed++;
        $state->clearFailures($request->tenantKey);

        return true;
    }

    /**
     * @param QueuedRequest $request
     * @param \Throwable $error
     * @param DrainState $state
     * @return bool
     */
    private function onDeliveryDropped(QueuedRequest $request, \Throwable $error, DrainState $state): bool
    {
        $this->deadLetter($request, $error, 'permanent error');

        $state->deadLettered++;

        return true;
    }

    /**
     * @param QueuedRequest $request
     * @param \Throwable $error
     * @param DrainState $state
     * @param int $now
     * @return bool
     */
    private function onDeliveryPaused(QueuedRequest $request, \Throwable $error, DrainState $state, int $now): bool
    {
        $this->storage->update($request->deferredTo($this->describe($error), $now + $this->tenantPauseSeconds));

        $state->requeued++;

        $this->pauseTenant($request, $error, $state, TenantPauseReporterInterface::REASON_CREDENTIALS_REJECTED);

        return false;
    }

    /**
     * @param QueuedRequest $request
     * @param \Throwable $error
     * @param DrainState $state
     * @param int $now
     * @return bool
     */
    private function onDeliveryRetried(QueuedRequest $request, \Throwable $error, DrainState $state, int $now): bool
    {
        $failed = $request->withAttemptFailure($this->describe($error), $this->nextAttemptAt($now, $request->attempts + 1));

        if ($failed->attempts >= $this->maxAttempts) {
            $this->deadLetter($failed, $error, 'max_attempts_exceeded');

            $state->deadLettered++;

            return true;
        }

        $this->storage->update($failed);

        $state->requeued++;

        if ($state->recordFailure($request->tenantKey) < $this->maxConsecutiveTenantFailures) {
            return true;
        }

        $this->pauseTenant($request, $error, $state, TenantPauseReporterInterface::REASON_CONSECUTIVE_FAILURES);

        return false;
    }

    /**
     * @param QueuedRequest $request
     * @param \Throwable $error
     * @param DrainState $state
     * @param string $reason
     */
    private function pauseTenant(QueuedRequest $request, \Throwable $error, DrainState $state, string $reason): void
    {
        $state->pauseTenant($request->tenantKey);

        $this->log(
            'warning',
            sprintf('[REQUEST_QUEUE] tenant partition paused (%s)', $reason),
            $request->operationType,
            $request->payload,
            $error,
            $request->tenantKey
        );

        try {
            ($nullsafeVariable2 = $this->pauseReporter) ? $nullsafeVariable2->reportPaused($request->tenantKey, $request, $error, $reason) : null;
        } catch (\Throwable $exception) {
        }
    }

    /**
     * @param int $now
     * @param int $attempts
     * @return int
     */
    private function nextAttemptAt(int $now, int $attempts): int
    {
        if ($this->baseRetryDelaySeconds === 0) {
            return $now;
        }

        $exponent = max(0, min($attempts - 1, 30)); 
        $delay = min($this->baseRetryDelaySeconds << $exponent, $this->maxRetryDelaySeconds);

        return $now + $this->jitter->random($delay);
    }

    /**
     * @param string $operationType
     */
    private function handler(string $operationType): RetryableOperationHandlerInterface
    {
        if (!isset($this->handlers[$operationType])) {
            throw new \InvalidArgumentException(sprintf('No handler registered for operation "%s".', $operationType));
        }

        return $this->handlers[$operationType];
    }

    /**
     * @param RetryableOperationHandlerInterface $handler
     * @param string|null $tenantKey
     * @throws \Throwable
     */
    private function dispatch(RetryableOperationHandlerInterface $handler, array $payload, ?string $tenantKey): void
    {
        if ($handler instanceof TenantAwareRetryableOperationHandlerInterface) {
            $handler->executeForTenant($payload, $tenantKey);

            return;
        }

        $handler->execute($payload);
    }

    /**
     * @param QueuedRequest $request
     * @param \Throwable $error
     * @param string $reason
     */
    private function deadLetter(QueuedRequest $request, \Throwable $error, string $reason): void
    {
        $this->log(
            'error',
            sprintf('[REQUEST_QUEUE] dead-lettered (%s)', $reason),
            $request->operationType,
            $request->payload,
            $error,
            $request->tenantKey
        );
        $this->storage->remove($request);
        ($nullsafeVariable3 = $this->deadLetterReporter) ? $nullsafeVariable3->report($request, $error) : null;
    }

    private function describe(\Throwable $error): string
    {
        return get_class($error) . ': ' . $error->getMessage();
    }

    /**
     * @param string|null $tenantKey
     */
    private function partitionKey(?string $tenantKey): string
    {
        return $tenantKey ?? "\0unscoped";
    }

    private function log(
        string $level,
        string $message,
        string $operationType,
        array $payload,
        ?\Throwable $error = null,
        ?string $tenantKey = null
    ): void {
        ($nullsafeVariable4 = $this->logger) ? $nullsafeVariable4->log($level, $message, [
            'operationType' => $operationType,
            'payload' => $payload,
            'tenantKey' => $tenantKey,
            'error' => $error !== null ? $this->describe($error) : null,
        ]) : null;
    }
}
