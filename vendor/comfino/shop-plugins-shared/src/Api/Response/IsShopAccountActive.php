<?php

declare(strict_types=1);

namespace Comfino\Api\Response;

class IsShopAccountActive extends Base
{
    /** @var bool
     * @readonly */
    public $isActive;

    /**
     * @inheritDoc
     * @param mixed[]|string|bool|null|float|int $deserializedResponseBody
     */
    protected function processResponseBody($deserializedResponseBody): void
    {
        $this->checkResponseType($deserializedResponseBody, 'boolean');

        $this->isActive = $deserializedResponseBody;
    }
}
