<?php

namespace Comfino\Common\Backend\Factory;

use Comfino\Common\Api\Client;

final class ApiClientFactory
{
    /**
     * Creates extended API client instance.
     *
     * @param string|null $apiKey Unique authentication key required for access to the Comfino API.
     * @param string|null $userAgent Custom client User-Agent header.
     * @param string|null $apiHost Custom API host.
     * @param string|null $apiLanguage Current API language - language code (eg: pl, en).
     * @param int $connectionTimeout API connection timeout in seconds.
     * @param int $transferTimeout Data transfer from API timeout in seconds. Must be greater than connection timeout.
     * @param int $connectionMaxNumAttempts Maximum number of connection attempts in case of timeout.
     * @param array $curlOptions cURL client extra options.
     */
    public function createClient(
        ?string $apiKey,
        ?string $userAgent,
        ?string $apiHost = null,
        ?string $apiLanguage = null,
        int $connectionTimeout = 1,
        int $transferTimeout = 3,
        int $connectionMaxNumAttempts = 3,
        array $curlOptions = []
    ): Client {
        $client = new Client($apiKey, $connectionTimeout, $transferTimeout, $connectionMaxNumAttempts, $curlOptions);

        if ($userAgent !== null) {
            $client->setCustomUserAgent($userAgent);
        }

        if ($apiHost !== null) {
            $client->setCustomApiHost($apiHost);
        }

        if ($apiLanguage !== null) {
            $client->setApiLanguage($apiLanguage);
        }

        return $client;
    }
}
