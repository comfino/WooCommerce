<?php

namespace Comfino\Api\Response;

use Comfino\Api\Exception\ResponseValidationError;

class GetPaywallItemDetails extends Base
{
    /** @var string
     * @readonly */
    public $productDetails;
    /** @var string
     * @readonly */
    public $listItemData;

    /**
     * @inheritDoc
     * @param mixed[]|string|bool|null $deserializedResponseBody
     */
    protected function processResponseBody($deserializedResponseBody): void
    {
        if (!is_array($deserializedResponseBody)) {
            throw new ResponseValidationError('Invalid response data: array expected.');
        }

        $this->productDetails = $deserializedResponseBody['productDetails'];
        $this->listItemData = $deserializedResponseBody['listItemData'];
    }
}
