<?php

declare(strict_types=1);

namespace Comfino\Api\Response;

class GetWidgetKey extends Base
{
    /** @var string
     * @readonly */
    public $widgetKey;

    /**
     * @inheritDoc
     * @param mixed[]|string|bool|null|float|int $deserializedResponseBody
     */
    protected function processResponseBody($deserializedResponseBody): void
    {
        $this->checkResponseType($deserializedResponseBody, 'string');

        $this->widgetKey = $deserializedResponseBody;
    }
}
