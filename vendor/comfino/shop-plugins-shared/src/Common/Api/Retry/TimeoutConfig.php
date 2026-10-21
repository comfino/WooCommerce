<?php

declare(strict_types=1);

namespace Comfino\Common\Api\Retry;

final class TimeoutConfig
{
    /**
     * @var int
     */
    public $connectionTimeout;
    /**
     * @var int
     */
    public $transferTimeout;
    /**
     * @param int $connectionTimeout
     * @param int $transferTimeout
     */
    public function __construct(
        int $connectionTimeout,
        int $transferTimeout
    ) {
        $this->connectionTimeout = $connectionTimeout;
        $this->transferTimeout = $transferTimeout;
        if ($this->connectionTimeout < 0) {
            throw new \InvalidArgumentException('Connection timeout cannot be negative.');
        }

        if ($this->transferTimeout < 0) {
            throw new \InvalidArgumentException('Transfer timeout cannot be negative.');
        }

        if ($this->transferTimeout < $this->connectionTimeout) {
            throw new \InvalidArgumentException('Transfer timeout must be greater than or equal to connection timeout.');
        }
    }

    /**
     * @param RetryPolicyInterface $policy
     * @param int $attemptNumber
     * @return self
     */
    public static function fromRetryPolicy($policy, $attemptNumber): self
    {
        return new self(
            $policy->getConnectionTimeout($attemptNumber),
            $policy->getTransferTimeout($attemptNumber)
        );
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return sprintf(
            'TimeoutConfig(connection=%ds, transfer=%ds)',
            $this->connectionTimeout,
            $this->transferTimeout
        );
    }

    /**
     * @param TimeoutConfig $other
     * @return bool
     */
    public function equals($other): bool
    {
        return $this->connectionTimeout === $other->connectionTimeout
            && $this->transferTimeout === $other->transferTimeout;
    }
}
