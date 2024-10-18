<?php

namespace Comfino\Api\Request;

use Comfino\Api\Dto\Payment\LoanQueryCriteria;
use Comfino\Api\Request;

/**
 * Financial products listing request.
 */
class GetFinancialProducts extends Request
{
    /**
     * @param LoanQueryCriteria $queryCriteria
     */
    public function __construct(LoanQueryCriteria $queryCriteria)
    {
        $this->setRequestMethod('GET');
        $this->setApiEndpointPath('financial-products');
        $this->setRequestParams(
            array_filter(
                [
                    'loanAmount' => $queryCriteria->loanAmount,
                    'loanTerm' => $queryCriteria->loanTerm,
                    'loanType' => $queryCriteria->loanType,
                    'productTypes' => $queryCriteria->productTypes,
                    'taxId' => $queryCriteria->taxId,
                ],
                static function ($value) : bool {
                    return $value !== null;
                }
            )
        );
    }

    /**
     * @inheritDoc
     */
    protected function prepareRequestBody(): ?array
    {
        return null;
    }
}
