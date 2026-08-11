<?php

declare(strict_types=1);

namespace Comfino\Api\Request;

use Comfino\Api\Request;

class GetUserSettings extends Request
{
    public function __construct()
    {
        $this->setRequestMethod('GET');
        $this->setApiEndpointPath('user/settings/flags');
    }

    protected function prepareRequestBody(): ?array
    {
        return null;
    }
}
