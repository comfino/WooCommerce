<?php

declare(strict_types=1);

namespace Comfino\Common\Api\Retry;

class ExponentialBackoffRetryPolicy implements RetryPolicyInterface
{
    /**
     * @var int
     */
    private $baseConnectionTimeout;
    /**
     * @var int
     */
    private $baseTransferTimeout;
    /**
     * @var int
     */
    private $maxAttempts;
    /**
     * @var ErrorDetectorInterface
     */
    private $errorDetector;
    
    public const MAX_CONNECTION_TIMEOUT = 30;
    
    public const MAX_TRANSFER_TIMEOUT = 60;
    
    public const DEFAULT_MAX_ATTEMPTS = 3;
    
    public const MIN_TRANSFER_TIMEOUT_MULTIPLIER = 3;

    /**
     * @param int $baseConnectionTimeout
     * @param int $baseTransferTimeout
     * @param int $maxAttempts
     * @param ErrorDetectorInterface $errorDetector
     */
    public function __construct(
        int $baseConnectionTimeout,
        int $baseTransferTimeout,
        int $maxAttempts,
        ErrorDetectorInterface $errorDetector
    ) {
        $this->baseConnectionTimeout = $baseConnectionTimeout;
        $this->baseTransferTimeout = $baseTransferTimeout;
        $this->maxAttempts = $maxAttempts;
        $this->errorDetector = $errorDetector;
        if ($this->baseConnectionTimeout < 1) {
            throw new \InvalidArgumentException('Base connection timeout must be at least 1 second.');
        }

        if ($this->baseTransferTimeout < self::MIN_TRANSFER_TIMEOUT_MULTIPLIER * $this->baseConnectionTimeout) {
            throw new \InvalidArgumentException(
                sprintf('Transfer timeout must be at least %dx connection timeout.', self::MIN_TRANSFER_TIMEOUT_MULTIPLIER)
            );
        }

        if ($this->maxAttempts < 1) {
            throw new \InvalidArgumentException('Maximum attempts must be at least 1.');
        }
    }

    /**
     * @param mixed $error
     * @param int $attemptNumber
     */
    public function shouldRetry($error, $attemptNumber): bool
    {
        if ($attemptNumber >= $this->maxAttempts) {
            return false;
        }

        return $this->errorDetector->isRetryableError($error);
    }

    /**
     * @param int $attemptNumber
     */
    public function getConnectionTimeout($attemptNumber): int
    {
        return $this->calculateTimeout($this->baseConnectionTimeout, $attemptNumber, self::MAX_CONNECTION_TIMEOUT);
    }

    /**
     * @param int $attemptNumber
     */
    public function getTransferTimeout($attemptNumber): int
    {
        return $this->calculateTimeout($this->baseTransferTimeout, $attemptNumber, self::MAX_TRANSFER_TIMEOUT);
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getBaseConnectionTimeout(): int
    {
        return $this->baseConnectionTimeout;
    }

    public function getBaseTransferTimeout(): int
    {
        return $this->baseTransferTimeout;
    }

    /**
     * @param int $baseTimeout
     * @param int $attemptNumber
     * @param int $maxTimeout
     * @return int
     */
    private function calculateTimeout(int $baseTimeout, int $attemptNumber, int $maxTimeout): int
    {
        if ($attemptNumber < 1 || $attemptNumber > $this->maxAttempts) {
            return $baseTimeout;
        }

        if ($this->maxAttempts <= 1) {
            return $baseTimeout;
        }

        $timeout = $baseTimeout << ($attemptNumber - 1);

        return min($timeout, $maxTimeout);
    }
}
