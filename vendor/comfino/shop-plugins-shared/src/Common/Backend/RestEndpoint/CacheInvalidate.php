<?php

namespace Comfino\Common\Backend\RestEndpoint;

use ComfinoExternal\Cache\TagInterop\TaggableCacheItemPoolInterface;
use Comfino\Common\Backend\Cache\ItemTypeEnum;
use Comfino\Common\Backend\RestEndpoint;
use Comfino\Common\Exception\InvalidEndpoint;
use Comfino\Common\Exception\InvalidRequest;
use ComfinoExternal\Psr\Cache\InvalidArgumentException;
use ComfinoExternal\Psr\Http\Message\ServerRequestInterface;

class CacheInvalidate extends RestEndpoint
{
    /**
     * @readonly
     * @var \ComfinoExternal\Cache\TagInterop\TaggableCacheItemPoolInterface
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
     * @param \ComfinoExternal\Psr\Http\Message\ServerRequestInterface $serverRequest
     * @param string|null $endpointName
     */
    public function processRequest($serverRequest, $endpointName = null): ?array
    {
        if (!$this->endpointPathMatch($serverRequest, $endpointName)) {
            throw new InvalidEndpoint('Endpoint path does not match request path.');
        }

        try {
            if (!is_array($requestPayload = $this->getParsedRequestBody($serverRequest))) {
                throw new InvalidRequest('Invalid request payload.');
            }
        } catch (\JsonException $e) {
            throw new InvalidRequest(sprintf('Invalid request payload: %s', $e->getMessage()), $e->getCode(), $e);
        }

        $this->cache->invalidateTags(array_intersect($requestPayload, ItemTypeEnum::values()));

        return null;
    }
}
