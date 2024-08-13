<?php

namespace Comfino\Common\Backend\Factory;

use Comfino\Extended\Api\Client;
use ComfinoExternal\Sunrise\Http\Factory\RequestFactory;
use ComfinoExternal\Sunrise\Http\Factory\ResponseFactory;
use ComfinoExternal\Sunrise\Http\Factory\StreamFactory;

final class ApiClientFactory
{
    public function createClient(
        ?string $apiKey,
        ?string $userAgent,
        ?string $apiHost = null,
        ?string $apiLanguage = null,
        array $curlOptions = []
    ): Client {
        $client = new Client(
            new RequestFactory(),
            new StreamFactory(),
            new \ComfinoExternal\Sunrise\Http\Client\Curl\Client(new ResponseFactory(), $curlOptions),
            $apiKey
        );

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
