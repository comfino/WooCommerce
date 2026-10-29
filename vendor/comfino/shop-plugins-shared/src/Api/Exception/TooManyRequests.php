<?php

declare(strict_types=1);

namespace Comfino\Api\Exception;

use ComfinoExternal\Psr\Http\Message\ResponseInterface;

class TooManyRequests extends RequestValidationError
{
    /**
     * @var int|null
     */
    private $retryAfterSeconds;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        string $url = '',
        string $requestBody = '',
        string $responseBody = '',
        $deserializedResponseBody = null,
        ?ResponseInterface $response = null,
        ?int $retryAfterSeconds = null
    ) {
        parent::__construct($message, $code, $previous, $url, $requestBody, $responseBody, $deserializedResponseBody, $response);

        $this->retryAfterSeconds = $retryAfterSeconds;
    }

    public function getRetryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }

    public function getStatusCode(): int
    {
        return 429;
    }
}
