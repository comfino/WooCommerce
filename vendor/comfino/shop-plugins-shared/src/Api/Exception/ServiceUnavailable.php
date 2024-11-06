<?php

namespace Comfino\Api\Exception;

class ServiceUnavailable extends \RuntimeException
{
    /** @var string */
    private $url;
    /** @var string */
    private $requestBody;
    /** @var string */
    private $responseBody;

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, string $url = '', string $requestBody = '', string $responseBody = '')
    {
        parent::__construct($message, $code, $previous);

        $this->url = $url;
        $this->requestBody = $requestBody;
        $this->responseBody = $responseBody;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @param string $url
     */
    public function setUrl($url): void
    {
        $this->url = $url;
    }

    public function getRequestBody(): string
    {
        return $this->requestBody;
    }

    /**
     * @param string $requestBody
     */
    public function setRequestBody($requestBody): void
    {
        $this->requestBody = $requestBody;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }

    /**
     * @param string $responseBody
     */
    public function setResponseBody($responseBody): void
    {
        $this->responseBody = $responseBody;
    }
}
