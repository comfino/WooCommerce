<?php

namespace Comfino\Api\Request;

use Comfino\Api\Request;

class GetWidgetTypes extends Request
{
    public function __construct()
    {
        $this->setRequestMethod('GET');
        $this->setApiEndpointPath('widget-types');
    }

    /**
     * @inheritDoc
     */
    protected function prepareRequestBody(): ?array
    {
        return null;
    }
}
