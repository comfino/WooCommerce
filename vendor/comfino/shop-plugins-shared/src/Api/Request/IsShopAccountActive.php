<?php

namespace Comfino\Api\Request;

use Comfino\Api\Request;

class IsShopAccountActive extends Request
{
    public function __construct()
    {
        $this->setRequestMethod('GET');
        $this->setApiEndpointPath('user/is-active');
    }

    /**
     * @inheritDoc
     */
    protected function prepareRequestBody(): ?array
    {
        return null;
    }
}
