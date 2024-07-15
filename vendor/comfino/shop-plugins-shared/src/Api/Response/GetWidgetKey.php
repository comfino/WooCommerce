<?php

namespace Comfino\Api\Response;

use Comfino\Api\Exception\ResponseValidationError;

class GetWidgetKey extends Base
{
    /** @var string
     * @readonly */
    public $widgetKey;

    /**
     * @inheritDoc
     * @param mixed[]|string|bool|null $deserializedResponseBody
     */
    protected function processResponseBody($deserializedResponseBody): void
    {
        if (!is_string($deserializedResponseBody)) {
            throw new ResponseValidationError('Invalid response data: string expected.');
        }

        $this->widgetKey = $deserializedResponseBody;
    }
}
