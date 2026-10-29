<?php

declare(strict_types=1);

namespace Comfino\Common\Api\Retry;

interface ErrorDetectorInterface
{
    /**
     * @param mixed $error
     * @return bool
     */
    public function isRetryableError($error): bool;

    /**
     * @param mixed $error
     * @return int|string|null
     */
    public function getErrorCode($error);

    /**
     * @param mixed $error
     * @return string
     */
    public function getErrorMessage($error): string;
}
