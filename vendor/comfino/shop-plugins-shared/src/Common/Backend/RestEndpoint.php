<?php

namespace Comfino\Common\Backend;

use Comfino\Api\SerializerInterface;
use ComfinoExternal\Psr\Http\Message\ServerRequestInterface;

abstract class RestEndpoint implements RestEndpointInterface
{
    /**
     * @readonly
     * @var string
     */
    private $name;
    /**
     * @readonly
     * @var string
     */
    private $endpointUrl;
    /**
     * @var mixed[]
     */
    protected $methods;
    /**
     * @var \Comfino\Api\SerializerInterface|null
     */
    protected $serializer;

    public function __construct(string $name, string $endpointUrl)
    {
        $this->name = $name;
        $this->endpointUrl = $endpointUrl;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getEndpointUrl(): string
    {
        return $this->endpointUrl;
    }

    /**
     * @param \Comfino\Api\SerializerInterface $serializer
     */
    public function setSerializer($serializer): void
    {
        $this->serializer = $serializer;
    }

    /**
     * @param \ComfinoExternal\Psr\Http\Message\ServerRequestInterface $serverRequest
     * @param string|null $endpointName
     */
    protected function endpointPathMatch($serverRequest, $endpointName = null): bool
    {
        $requestMethod = strtoupper($serverRequest->getMethod());

        if ($endpointName !== null && $endpointName === $this->name && in_array($requestMethod, $this->methods, true)) {
            return true;
        }

        return (string) $serverRequest->getUri() === $this->endpointUrl && in_array($requestMethod, $this->methods, true);
    }

    /**
     * @param \ComfinoExternal\Psr\Http\Message\ServerRequestInterface $serverRequest
     * @return mixed[]|string|null
     */
    protected function getParsedRequestBody($serverRequest)
    {
        $contentType = $serverRequest->hasHeader('Content-Type') ? $serverRequest->getHeader('Content-Type')[0] : '';
        $requestPayload = $serverRequest->getBody()->getContents();

        $serverRequest->getBody()->rewind();

        if ($contentType === 'application/json') {
            if ($this->serializer !== null) {
                return $this->serializer->unserialize($requestPayload);
            }

            return json_decode($requestPayload, true);
        }

        if (strtoupper($serverRequest->getMethod()) === 'POST') {
            return $serverRequest->getParsedBody();
        }

        return $requestPayload;
    }
}
