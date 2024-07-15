<?php

namespace Comfino\Common\Backend\RestEndpoint;

use Cache\TagInterop\TaggableCacheItemPoolInterface;
use Comfino\Common\Backend\Cache\ItemTypeEnum;
use Comfino\Common\Backend\RestEndpoint;
use Comfino\Common\Exception\InvalidEndpoint;
use Comfino\Common\Exception\InvalidRequest;
use Psr\Cache\InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

class CacheInvalidate extends RestEndpoint
{
    /**
     * @readonly
     * @var \Cache\TagInterop\TaggableCacheItemPoolInterface
     */
    private $cache;
    public function __construct(
        string $name,
        string $endpointUrl,
        TaggableCacheItemPoolInterface $cache
    ) {
        $this->cache = $cache;
        parent::__construct($name, $endpointUrl);

        $this->methods = ['POST', 'PUT', 'PATCH'];
    }

    /**
     * @throws InvalidArgumentException
     * @param \Psr\Http\Message\ServerRequestInterface $serverRequest
     * @param string|null $endpointName
     */
    public function processRequest($serverRequest, $endpointName = null): ?array
    {
        if (!$this->endpointPathMatch($serverRequest, $endpointName)) {
            throw new InvalidEndpoint('Endpoint path does not match request path.');
        }

        if (!is_array($requestPayload = $this->getParsedRequestBody($serverRequest))) {
            throw new InvalidRequest('Invalid request payload.');
        }

        $this->cache->invalidateTags(array_intersect($requestPayload, ItemTypeEnum::values()));

        return null;
    }
}
