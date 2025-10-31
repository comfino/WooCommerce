<?php

declare(strict_types=1);

namespace Comfino\Extended\Api\Request;

use Comfino\Api\Request;

/**
 * Cart abandonment notifying request.
 */
class NotifyAbandonedCart extends Request
{
    /**
     * @readonly
     * @var string
     */
    private $type;
    public function __construct(string $type)
    {
        $this->type = $type;
        $this->setRequestMethod('POST');
        $this->setApiEndpointPath('abandoned_cart');
    }

    /**
     * @inheritDoc
     */
    protected function prepareRequestBody(): ?array
    {
        return ['type' => $this->type];
    }
}
