<?php

namespace Comfino\Api\Request;

use Comfino\Api\Request;

/**
 * Loan application cancellation request.
 */
class CancelOrder extends Request
{
    /**
     * @param string $orderId
     */
    public function __construct(string $orderId)
    {
        $this->setRequestMethod('PUT');
        $this->setApiEndpointPath(sprintf('orders/%s/cancel', $orderId));
    }

    /**
     * @inheritDoc
     */
    protected function prepareRequestBody(): ?array
    {
        return null;
    }
}
