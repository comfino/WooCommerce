<?php

declare(strict_types=1);

namespace Comfino\Common\Api\Retry;

class RetryExhaustedException extends \RuntimeException
{
    /**
     * @var \Throwable|null
     */
    private $originalError;
    /**
     * @var int
     */
    private $attemptCount;
    /**
     * @var TimeoutConfig|null
     */
    private $lastTimeoutConfig;
    /**
     * @param \Throwable|null $originalError
     * @param int $attemptCount
     * @param TimeoutConfig|null $lastTimeoutConfig
     */
    public function __construct(
        ?\Throwable $originalError,
        int $attemptCount,
        ?TimeoutConfig $lastTimeoutConfig = null
    ) {
        $this->originalError = $originalError;
        $this->attemptCount = $attemptCount;
        $this->lastTimeoutConfig = $lastTimeoutConfig;
        $message = $this->buildMessage();

        parent::__construct(
            $message,
            (($nullsafeVariable1 = $originalError) ? $nullsafeVariable1->getCode() : null) ?? 0,
            $originalError
        );
    }

    /**
     * @return \Throwable|null
     */
    public function getOriginalError(): ?\Throwable
    {
        return $this->originalError;
    }

    /**
     * @return int
     */
    public function getAttemptCount(): int
    {
        return $this->attemptCount;
    }

    /**
     * @return TimeoutConfig|null
     */
    public function getLastTimeoutConfig(): ?TimeoutConfig
    {
        return $this->lastTimeoutConfig;
    }

    /**
     * @return string
     */
    private function buildMessage(): string
    {
        $parts = [
            sprintf('Request failed after %d attempt(s)', $this->attemptCount),
        ];

        if ($this->lastTimeoutConfig !== null) {
            $parts[] = sprintf(
                'Final timeouts: connection=%ds, transfer=%ds',
                $this->lastTimeoutConfig->connectionTimeout,
                $this->lastTimeoutConfig->transferTimeout
            );
        }

        if ($this->originalError !== null) {
            $parts[] = sprintf(
                'Original error: %s',
                $this->originalError->getMessage()
            );
        }

        return implode('. ', $parts) . '.';
    }

    /**
     * @param \Throwable|null $originalError
     * @param int $attemptCount
     * @param TimeoutConfig|null $lastTimeoutConfig
     * @param string|null $requestUri
     * @param string|null $requestBody
     * @return self
     */
    public static function withRequestContext(
        $originalError,
        $attemptCount,
        $lastTimeoutConfig,
        $requestUri = null,
        $requestBody = null
    ): self {
        $exception = new self($originalError, $attemptCount, $lastTimeoutConfig);

        if ($requestUri !== null) {
            $exception->requestUri = $requestUri;
        }

        if ($requestBody !== null) {
            $exception->requestBody = $requestBody;
        }

        return $exception;
    }

    private $requestUri;

    private $requestBody;

    /**
     * @return string|null
     */
    public function getRequestUri(): ?string
    {
        return $this->requestUri;
    }

    /**
     * @return string|null
     */
    public function getRequestBody(): ?string
    {
        return $this->requestBody;
    }
}
