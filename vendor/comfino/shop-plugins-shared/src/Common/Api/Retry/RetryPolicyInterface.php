<?php

declare(strict_types=1);

namespace Comfino\Common\Api\Retry;

interface RetryPolicyInterface
{
    /**
     * @param mixed $error
     * @param int $attemptNumber
     * @return bool
     */
    public function shouldRetry($error, $attemptNumber): bool;

    /**
     * @param int $attemptNumber
     * @return int
     */
    public function getConnectionTimeout($attemptNumber): int;

    /**
     * @param int $attemptNumber
     * @return int
     */
    public function getTransferTimeout($attemptNumber): int;

    /**
     * @return int
     */
    public function getMaxAttempts(): int;

    /**
     * @return int
     */
    public function getBaseConnectionTimeout(): int;

    /**
     * @return int
     */
    public function getBaseTransferTimeout(): int;
}
