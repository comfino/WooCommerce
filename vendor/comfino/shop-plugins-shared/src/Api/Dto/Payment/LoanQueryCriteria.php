<?php

declare(strict_types=1);

namespace Comfino\Api\Dto\Payment;

class LoanQueryCriteria
{
    /** @var int
     * @readonly */
    public $loanAmount;
    /** @var int|null
     * @readonly */
    public $loanTerm;
    /** @var LoanTypeEnum|null
     * @readonly */
    public $loanType;
    /** @var LoanTypeEnum[]|null
     * @readonly */
    public $productTypes;
    /** @var string|null
     * @readonly */
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
