<?php

namespace Comfino\Extended\Api;

use Comfino\Api\Response\Base as BaseApiResponse;
use Comfino\Api\SerializerInterface;
use Comfino\Extended\Api\Dto\Plugin\ShopPluginError;
use Comfino\Extended\Api\Request\NotifyAbandonedCart;
use Comfino\Extended\Api\Request\NotifyShopPluginRemoval;
use Comfino\Extended\Api\Request\ReportShopPluginError;
use Comfino\Extended\Api\Serializer\Json as JsonSerializer;
use ComfinoExternal\Psr\Http\Client\ClientInterface;
use ComfinoExternal\Psr\Http\Message\RequestFactoryInterface;
use ComfinoExternal\Psr\Http\Message\StreamFactoryInterface;

/**
 * Extended Comfino API client PHP 7.1+ compatible.
 */
class Client extends \Comfino\Api\Client
{
    public function __construct(
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ClientInterface $client,
        ?string $apiKey,
        $apiVersion = 1,
        ?SerializerInterface $serializer = null
    ) {
        parent::__construct($requestFactory, $streamFactory, $client, $apiKey, $apiVersion, $serializer ?? new JsonSerializer());
    }

    /**
     * Sends a plugin error report to the Comfino API.
     *
     * @param ShopPluginError $shopPluginError
     * @return bool
     */
    public function sendLoggedError($shopPluginError): bool
    {
        try {
            $request = new ReportShopPluginError($shopPluginError, $this->getUserAgent());

            new BaseApiResponse(
                $request,
                $this->sendRequest($request->setSerializer($this->serializer)),
                $this->serializer
            );
        } catch (\Throwable $exception) {
            return false;
        }

        return true;
    }

    /**
     * Sends notification about plugin uninstallation.
     *
     * @return bool
     */
    public function notifyPluginRemoval(): bool
    {
        try {
            $this->sendRequest((new NotifyShopPluginRemoval())->setSerializer($this->serializer));
        } catch (\Throwable $exception) {
            return false;
        }

        return true;
    }

    /**
     * Sends notification about abandoned cart.
     *
     * @param string $type
     * @return bool
     */
    public function notifyAbandonedCart($type): bool
    {
        try {
            $this->sendRequest((new NotifyAbandonedCart($type))->setSerializer($this->serializer));
        } catch (\Throwable $exception) {
            return false;
        }

        return true;
    }
}
