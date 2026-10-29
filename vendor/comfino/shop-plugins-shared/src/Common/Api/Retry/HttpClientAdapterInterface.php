<?php

declare(strict_types=1);

namespace Comfino\Common\Api\Retry;

/**
 * Language-agnostic HTTP client adapter interface.
 *
 * This interface abstracts HTTP client operations to make retry logic
 * independent of specific HTTP client implementations.
 *
 * Different languages/frameworks use different HTTP clients:
 * - PHP: cURL, Guzzle, Symfony HTTP Client, PSR-18 clients
 * - JavaScript: axios, fetch, node-fetch, got
 * - C#: HttpClient, RestSharp
 * - Java: HttpClient, OkHttp, Apache HttpClient
 * - Python: requests, httpx, aiohttp
 * - Go: net/http, resty
 *
 * This interface provides a common contract that can be adapted to any client.
 *
 * @version 1.0.0
 */
interface HttpClientAdapterInterface
{
    /**
     * @param mixed $request
     * @param TimeoutConfig $timeoutConfig
     * @return mixed
     * @throws \Throwable
     */
    public function sendRequest($request, $timeoutConfig);

    /**
     * @param TimeoutConfig $timeoutConfig
     * @return void
     */
    public function updateTimeoutConfig($timeoutConfig): void;
}
