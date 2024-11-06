<?php

namespace Comfino\Api\Response;

use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Api\Exception\ResponseValidationError;

class GetProductTypes extends Base
{
    /** @var LoanTypeEnum[]
     * @readonly */
    public $productTypes;
    /** @var string[]
     * @readonly */
    public $productTypesWithNames;

    /**
     * @inheritDoc
     * @param mixed[]|string|bool|null $deserializedResponseBody
     */
    protected function processResponseBody($deserializedResponseBody): void
    {
        if (!is_array($deserializedResponseBody)) {
            throw new ResponseValidationError('Invalid response data: array expected.');
        }

        $this->productTypesWithNames = $deserializedResponseBody;
        $this->productTypes = array_map(
            static function (string $productType) : LoanTypeEnum {
                return LoanTypeEnum::from($productType, false);
            },
            array_keys($deserializedResponseBody)
        );
    }
}
