<?php

namespace Comfino\Api\Request;

use Comfino\Api\Request;

class IsShopAccountActive extends Request
{
    /**
     * @param string|null $cacheInvalidateUrl
     * @param string|null $configurationUrl
     */
    public function __construct(?string $cacheInvalidateUrl, ?string $configurationUrl)
    {
        $this->setRequestMethod('GET');
        $this->setApiEndpointPath('user/is-active');

        if (!empty($cacheInvalidateUrl) || !empty($configurationUrl)) {
            $requestHeaders = [];

            if (!empty($cacheInvalidateUrl)) {
                $requestHeaders['Comfino-Cache-Invalidate-Url'] = $cacheInvalidateUrl;
            }
            if (!empty($cacheInvalidateUrl)) {
                $requestHeaders['Comfino-Configuration-Url'] = $configurationUrl;
            }

            $this->setRequestHeaders($requestHeaders);
        }
    }

    /**
     * @inheritDoc
     */
    protected function prepareRequestBody(): ?array
    {
        return null;
    }
}
