<?php

namespace Comfino\Api\Response;

use Comfino\Api\Exception\ResponseValidationError;

class GetPaywallFragments extends Base
{
    /** @var array
     * @readonly */
    public $paywallFragments;

    /**
     * @inheritDoc
     * @param mixed[]|string|bool|null $deserializedResponseBody
     */
    protected function processResponseBody($deserializedResponseBody): void
    {
        if (!is_array($deserializedResponseBody)) {
            throw new ResponseValidationError('Invalid response data: array expected.');
        }

        $this->paywallFragments = $deserializedResponseBody;
    }
}
