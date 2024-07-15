<?php

namespace Comfino\Common\Backend;

use Comfino\Api\SerializerInterface;
use Comfino\Common\Exception\InvalidEndpoint;
use Comfino\Common\Exception\InvalidRequest;
use Psr\Http\Message\ServerRequestInterface;

interface RestEndpointInterface
{
    public function getName(): string;

    public function getMethods(): array;

    public function getEndpointUrl(): string;

    /**
     * @param \Comfino\Api\SerializerInterface $serializer
     */
    public function setSerializer($serializer): void;

    /**
     * @throws InvalidEndpoint
     * @throws InvalidRequest
     * @param \Psr\Http\Message\ServerRequestInterface $serverRequest
     * @param string|null $endpointName
     */
    public function processRequest($serverRequest, $endpointName = null): ?array;
}
