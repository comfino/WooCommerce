<?php

namespace Comfino\Api\Response;

use Comfino\Api\Exception\ResponseValidationError;

class GetPaywall extends Base
{
    /** @var string
     * @readonly */
    public $paywallPage;

    /**
     * @inheritDoc
     * @param mixed[]|string|bool|null $deserializedResponseBody
     */
    protected function processResponseBody($deserializedResponseBody): void
    {
        if (!is_string($deserializedResponseBody)) {
            throw new ResponseValidationError('Invalid response data: string expected.');
        }

        $this->paywallPage = $deserializedResponseBody;
    }
}
