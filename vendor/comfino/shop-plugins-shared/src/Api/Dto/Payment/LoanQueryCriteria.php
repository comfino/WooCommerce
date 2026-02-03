<?php

declare(strict_types=1);

namespace Comfino\Api\Dto\Payment;

class LoanQueryCriteria
{
    public $loanAmount;
    
    public $loanTerm;
    
    public $loanType;
    
    public $productTypes;
    
    public $taxId;

    /**
     * @param int $loanAmount
     * @param int|null $loanTerm
     * @param LoanTypeEnum|null $loanType
     * @param LoanTypeEnum[]|null $productTypes
     * @param string|null $taxId
     */
    public function __construct(int $loanAmount, ?int $loanTerm = null, ?LoanTypeEnum $loanType = null, ?array $productTypes = null, ?string $taxId = null)
    {
        $this->loanAmount = $loanAmount;
        $this->loanTerm = $loanTerm;
        $this->loanType = $loanType;
        $this->productTypes = $productTypes;
        $this->taxId = $taxId;
    }
}
