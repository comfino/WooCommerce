<?php

declare(strict_types=1);

namespace Comfino\Common\Api\Retry;

use ComfinoExternal\Psr\Http\Message\ResponseInterface;
use ComfinoExternal\Sunrise\Http\Client\Curl\Client as CurlClient;
use ComfinoExternal\Sunrise\Http\Factory\ResponseFactory;

class SunriseHttpClientAdapter implements HttpClientAdapterInterface
{
    /**
     * @var ResponseFactory
     */
    private $responseFactory;
    /**
     * @var array
     */
    private $curlOptions = [];
    /**
     * @var CurlClient
     */
    private $client;

    /**
     * @param ResponseFactory $responseFactory
     * @param array $curlOptions
     */
    public function __construct(
        ResponseFactory $responseFactory,
        array $curlOptions = []
    ) {
        $this->responseFactory = $responseFactory;
        $this->curlOptions = $curlOptions;
        
        $this->client = $this->createClient(new TimeoutConfig(1, 3));
    }

    /**
     * @param mixed $request
     * @param \Comfino\Common\Api\Retry\TimeoutConfig $timeoutConfig
     */
    public function sendRequest($request, $timeoutConfig): ResponseInterface
    {
        return $this->client->sendRequest($request);
    }

    /**
     * @param \Comfino\Common\Api\Retry\TimeoutConfig $timeoutConfig
     */
    public function updateTimeoutConfig($timeoutConfig): void
    {
        $this->client = $this->createClient($timeoutConfig);
    }

    /**
     * @param TimeoutConfig $timeoutConfig
     * @return CurlClient
     */
    private function createClient(TimeoutConfig $timeoutConfig): CurlClient
    {
        $clientOptions = [
            CURLOPT_CONNECTTIMEOUT => $timeoutConfig->connectionTimeout,
            CURLOPT_TIMEOUT => $timeoutConfig->transferTimeout
        ];

        foreach ($this->curlOptions as $optionKey => $optionValue) {
            $clientOptions[$optionKey] = $optionValue;
        }

        return new CurlClient($this->responseFactory, $clientOptions);
    }

    /**
     * @return CurlClient
     */
    public function getUnderlyingClient(): CurlClient
    {
        return $this->client;
    }

    /**
     * @param array $curlOptions
     * @param TimeoutConfig|null $timeoutConfig
     * @return void
     */
    public function updateCurlOptions($curlOptions, $timeoutConfig = null): void
    {
        $this->curlOptions = $curlOptions;

        if ($timeoutConfig !== null) {
            $this->client = $this->createClient($timeoutConfig);
        }
    }
}
