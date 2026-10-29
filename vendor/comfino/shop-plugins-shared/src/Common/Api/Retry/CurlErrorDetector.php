<?php

declare(strict_types=1);

namespace Comfino\Common\Api\Retry;

use ComfinoExternal\Psr\Http\Client\ClientExceptionInterface;

class CurlErrorDetector implements ErrorDetectorInterface
{
    /**
     * @var bool
     */
    private $retryOnConnectionFailure = false;
    /**
     * @var bool
     */
    private $retryOnDnsFailure = false;
    /**
     * @var bool
     */
    private $retryOnReceiveError = false;
    
    private const CURLE_OPERATION_TIMEDOUT = 28;

    private const CURLE_COULDNT_CONNECT = 7;

    private const CURLE_COULDNT_RESOLVE_HOST = 6;

    private const CURLE_RECV_ERROR = 56;

    /**
     * @param bool $retryOnConnectionFailure
     * @param bool $retryOnDnsFailure
     * @param bool $retryOnReceiveError
     */
    public function __construct(bool $retryOnConnectionFailure = false, bool $retryOnDnsFailure = false, bool $retryOnReceiveError = false)
    {
        $this->retryOnConnectionFailure = $retryOnConnectionFailure;
        $this->retryOnDnsFailure = $retryOnDnsFailure;
        $this->retryOnReceiveError = $retryOnReceiveError;
    }

    /**
     * @param mixed $error
     */
    public function isRetryableError($error): bool
    {
        if (!$error instanceof ClientExceptionInterface) {
            return false;
        }

        $errorCode = $error->getCode();

        if ($errorCode === self::CURLE_OPERATION_TIMEDOUT) {
            return true;
        }

        if ($this->retryOnConnectionFailure && $errorCode === self::CURLE_COULDNT_CONNECT) {
            return true;
        }

        if ($this->retryOnDnsFailure && $errorCode === self::CURLE_COULDNT_RESOLVE_HOST) {
            return true;
        }

        if ($this->retryOnReceiveError && $errorCode === self::CURLE_RECV_ERROR) {
            return true;
        }

        return false;
    }

    /**
     * @param mixed $error
     * @return int|string|null
     */
    public function getErrorCode($error)
    {
        if (!$error instanceof \Throwable) {
            return null;
        }

        return $error->getCode();
    }

    /**
     * @param mixed $error
     */
    public function getErrorMessage($error): string
    {
        if (!$error instanceof \Throwable) {
            return 'Unknown error';
        }

        return $error->getMessage();
    }
}
