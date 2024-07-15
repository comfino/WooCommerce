<?php

namespace Comfino\Api\Response;

use Comfino\Api\Exception\ResponseValidationError;

class IsShopAccountActive extends Base
{
    /** @var bool
     * @readonly */
    public $isActive;

    /**
     * @inheritDoc
     * @param mixed[]|string|bool|null $deserializedResponseBody
     */
    protected function processResponseBody($deserializedResponseBody): void
    {
        if (!is_bool($deserializedResponseBody)) {
            throw new ResponseValidationError('Invalid response data: bool expected.');
        }

        $this->isActive = $deserializedResponseBody;
    }
}
