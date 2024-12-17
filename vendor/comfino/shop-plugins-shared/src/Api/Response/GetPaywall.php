<?php

namespace Comfino\Api\Response;

class GetPaywall extends Base
{
    /** @var string
     * @readonly */
    public $paywallBody;
    /** @var string
     * @readonly */
    public $paywallHash;

    /**
     * @inheritDoc
     * @param mixed[]|string|bool|null|float|int $deserializedResponseBody
     */
    protected function processResponseBody($deserializedResponseBody): void
    {
        $this->checkResponseType($deserializedResponseBody, 'array');
        $this->checkResponseStructure($deserializedResponseBody, ['paywallBody', 'paywallHash']);
        $this->checkResponseType($deserializedResponseBody['paywallBody'], 'string');
        $this->checkResponseType($deserializedResponseBody['paywallHash'], 'string');

        $this->paywallBody = $deserializedResponseBody['paywallBody'];
        $this->paywallHash = $deserializedResponseBody['paywallHash'];
    }
}
