<?php

namespace Comfino\Api\Request;

use Comfino\Api\Request;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;

class GetProductTypes extends Request
{
    public function __construct(ProductTypesListTypeEnum $listType)
    {
        $this->setRequestMethod('GET');
        $this->setApiEndpointPath('product-types');
        $this->setRequestParams(['listType' => (string) $listType]);
    }

    /**
     * @inheritDoc
     */
    protected function prepareRequestBody(): ?array
    {
        return null;
    }
}
