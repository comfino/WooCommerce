<?php

declare(strict_types=1);

namespace Comfino\Api\Exception;

class NonJsonResponse extends ResponseValidationError
{
    /**
     * @var int
     */
    private $statusCode;
    /**
     * @var string
     */
    private $contentType;

    public function __construct(
        string $message,
        int $statusCode,
        ?\Throwable $previous,
        string $url,
        string $requestBody,
        string $responseBody,
        string $contentType
    ) {
        parent::__construct($message, $statusCode, $previous, $url, $requestBody, $responseBody);

        $this->statusCode = $statusCode;
        $this->contentType = $contentType;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function looksLikeHtml(): bool
    {
        return preg_match('/^<(!DOCTYPE|html)/i', ltrim($this->getResponseBody())) === 1;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
