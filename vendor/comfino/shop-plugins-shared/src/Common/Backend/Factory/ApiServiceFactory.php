<?php

namespace Comfino\Common\Backend\Factory;

use Comfino\Common\Backend\RestEndpointManager;
use Comfino\Extended\Api\Serializer\Json as JsonSerializer;
use Sunrise\Http\Factory\ResponseFactory;
use Sunrise\Http\Factory\ServerRequestFactory;
use Sunrise\Http\Factory\StreamFactory;
use Sunrise\Http\Factory\UriFactory;

final class ApiServiceFactory
{
    /**
     * @param string[] $apiKeys
     */
    public function createService(
        string $platformName,
        string $platformVersion,
        string $pluginVersion,
        array $apiKeys
    ): RestEndpointManager {
        return RestEndpointManager::getInstance(
            $platformName,
            $platformVersion,
            $pluginVersion,
            $apiKeys,
            new ServerRequestFactory(),
            new StreamFactory(),
            new UriFactory(),
            new ResponseFactory(),
            new JsonSerializer()
        );
    }
}
