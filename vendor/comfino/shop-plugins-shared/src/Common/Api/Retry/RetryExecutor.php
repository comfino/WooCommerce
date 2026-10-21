<?php

declare(strict_types=1);

namespace Comfino\Common\Api\Retry;

class RetryExecutor
{
    /**
     * @var RetryPolicyInterface
     */
    private $retryPolicy;
    /**
     * @param RetryPolicyInterface $retryPolicy
     */
    public function __construct(RetryPolicyInterface $retryPolicy)
    {
        $this->retryPolicy = $retryPolicy;
    }

    /**
     * @param mixed $request
     * @param HttpClientAdapterInterface $httpClient
     * @param callable|null $onRetry
     * @return mixed
     * @throws RetryExhaustedException
     * @throws \Throwable
     */
    public function execute(
        $request,
        $httpClient,
        $onRetry = null
    ) {
        $lastError = null;
        $lastTimeoutConfig = null;

        for ($attempt = 1; $attempt <= $this->retryPolicy->getMaxAttempts(); $attempt++) {
            try {
                $timeoutConfig = TimeoutConfig::fromRetryPolicy($this->retryPolicy, $attempt);
                $lastTimeoutConfig = $timeoutConfig;

                $httpClient->updateTimeoutConfig($timeoutConfig);

                return $httpClient->sendRequest($request, $timeoutConfig);

            } catch (\Throwable $error) {
                $lastError = $error;

                if ($this->retryPolicy->shouldRetry($error, $attempt)) {
                    if ($onRetry !== null) {
                        $onRetry($attempt, $error);
                    }

                    continue;
                }

                throw $error;
            }
        }

        throw new RetryExhaustedException(
            $lastError,
            $this->retryPolicy->getMaxAttempts(),
            $lastTimeoutConfig
        );
    }

    /**
     * @return RetryPolicyInterface
     */
    public function getRetryPolicy(): RetryPolicyInterface
    {
        return $this->retryPolicy;
    }
}
