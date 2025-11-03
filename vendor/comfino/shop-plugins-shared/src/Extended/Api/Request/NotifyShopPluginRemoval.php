<?php

declare(strict_types=1);

namespace Comfino\Extended\Api\Request;

use Comfino\Api\Request;

/**
 * Shop plugin uninstallation notifying request.
 */
class NotifyShopPluginRemoval extends Request
{
    public function __construct()
    {
        $this->setRequestMethod('PUT');
        $this->setApiEndpointPath('log-plugin-remove');
    }

    /**
     * @inheritDoc
     */
    protected function prepareRequestBody(): ?array
    {
        return null;
    }
}
