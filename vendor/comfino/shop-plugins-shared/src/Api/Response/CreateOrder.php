<?php

namespace Comfino\Api\Response;

use Comfino\Api\Exception\ResponseValidationError;

class CreateOrder extends Base
{
    /** @var string
     * @readonly */
    public $status;
    /** @var string
     * @readonly */
    public $externalId;
    /** @var string
     * @readonly */
    public $applicationUrl;

    /**
     * @param mixed[]|string|bool|null $deserializedResponseBody
     */
    protected function processResponseBody($deserializedResponseBody): void
    {
        if (!is_array($deserializedResponseBody)) {
            throw new ResponseValidationError('Invalid response data: array expected.');
        }

        $this->status = $deserializedResponseBody['status'];
        $this->externalId = $deserializedResponseBody['externalId'];
        $this->applicationUrl = $deserializedResponseBody['applicationUrl'];
    }
}
