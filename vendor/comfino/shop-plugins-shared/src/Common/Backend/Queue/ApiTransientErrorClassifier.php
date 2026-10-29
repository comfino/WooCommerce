<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Queue;

use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\NonJsonResponse;
use Comfino\Api\Exception\ServiceUnavailable;
use Comfino\Api\Exception\TooManyRequests;
use Comfino\Api\HttpErrorExceptionInterface;
use Comfino\Common\Api\Retry\CurlErrorDetector;
use Comfino\Common\Exception\ConnectionTimeout;
use ComfinoExternal\Psr\Http\Client\ClientExceptionInterface;
use ComfinoExternal\Psr\Http\Client\NetworkExceptionInterface;

final class ApiTransientErrorClassifier implements TransientErrorClassifierInterface
{
    private const HTTP_BAD_REQUEST = 400;
    private const HTTP_UNAUTHORIZED = 401;
    private const HTTP_FORBIDDEN = 403;
    private const HTTP_NOT_FOUND = 404;
    private const HTTP_CONFLICT = 409;
    private const HTTP_TOO_MANY_REQUESTS = 429;
    private const HTTP_INTERNAL_SERVER_ERROR = 500;

    /**
     * @var \Comfino\Common\Api\Retry\CurlErrorDetector
     */
    private $curlErrorDetector;

    private $absorbNotFoundOperations;

    /**
     * @param string[]|null $absorbNotFoundOperations
     */
    public function __construct(?array $absorbNotFoundOperations = null)
    {
        $this->absorbNotFoundOperations = $absorbNotFoundOperations ?? ['cancel_order'];
        
        $this->curlErrorDetector = new CurlErrorDetector(true, true, true);
    }

    /**
     * @param string $operationType
     * @param \Throwable $error
     */
    public function classify($operationType, $error): string
    {
        return $this->matchExplicitRule($operationType, $error) ?? QueueErrorDisposition::RETRY;
    }

    /**
     * @param \Throwable $error
     */
    public static function isTransient($error): bool
    {
        $disposition = (new self())->matchExplicitRule('', $error);

        return $disposition === QueueErrorDisposition::RETRY || $disposition === QueueErrorDisposition::TREAT_AS_SUCCESS;
    }

    /**
     * @return string|null
     */
    private function matchExplicitRule(string $operationType, \Throwable $error): ?string
    {
        if ($error instanceof ConnectionTimeout || $error instanceof NetworkExceptionInterface) {
            return QueueErrorDisposition::RETRY;
        }

        if ($error instanceof ClientExceptionInterface && $this->curlErrorDetector->isRetryableError($error)) {
            return QueueErrorDisposition::RETRY;
        }

        if ($error instanceof AccessDenied && $error->isIdempotentFailure()) {
            return QueueErrorDisposition::TREAT_AS_SUCCESS;
        }

        if ($error instanceof HttpErrorExceptionInterface) {
            $statusCode = $error->getStatusCode();

            if (
                ($statusCode === self::HTTP_NOT_FOUND || $statusCode === self::HTTP_CONFLICT) &&
                in_array($operationType, $this->absorbNotFoundOperations, true)
            ) {
                return QueueErrorDisposition::TREAT_AS_SUCCESS;
            }

            if (
                $error instanceof ServiceUnavailable ||
                $statusCode >= self::HTTP_INTERNAL_SERVER_ERROR ||
                $error instanceof TooManyRequests
            ) {
                return QueueErrorDisposition::RETRY;
            }

            if ($statusCode === self::HTTP_UNAUTHORIZED || $statusCode === self::HTTP_FORBIDDEN) {
                return QueueErrorDisposition::PAUSE_TENANT;
            }

            if ($statusCode >= self::HTTP_BAD_REQUEST) {
                return QueueErrorDisposition::DROP_PERMANENT;
            }
        }

        if ($error instanceof \InvalidArgumentException) {
            return QueueErrorDisposition::DROP_PERMANENT;
        }

        return null;
    }
}
