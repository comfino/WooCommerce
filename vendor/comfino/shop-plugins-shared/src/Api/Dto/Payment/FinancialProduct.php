<?php

namespace Comfino\Api\Dto\Payment;

class FinancialProduct
{
    /** @var string
     * @readonly */
    public $name;
    /** @var LoanTypeEnum
     * @readonly */
    public $type;
    /** @var string
     * @readonly */
    public $description;
    /** @var string
     * @readonly */
    public $icon;
    /** @var int
     * @readonly */
    public $instalmentAmount;
    /** @var int
     * @readonly */
    public $toPay;
    /** @var int
     * @readonly */
    public $loanTerm;
    /** @var float
     * @readonly */
    public $rrso;
    /** @var string
     * @readonly */
    public $representativeExample;
    /** @var string|null
     * @readonly */
    public $remarks;
    /** @var LoanParameters[]
     * @readonly */
    public $loanParameters;

    /**
     * @param string $name
     * @param LoanTypeEnum $type
     * @param string $description
     * @param string $icon
     * @param int $instalmentAmount
     * @param int $toPay
     * @param int $loanTerm
     * @param float $rrso
     * @param string $representativeExample
     * @param string|null $remarks
     * @param LoanParameters[] $loanParameters
     */
    public function __construct(
        string $name,
        LoanTypeEnum $type,
        string $description,
        string $icon,
        int $instalmentAmount,
        int $toPay,
        int $loanTerm,
        float $rrso,
        string $representativeExample,
        ?string $remarks,
        array $loanParameters
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->description = $description;
        $this->icon = $icon;
        $this->instalmentAmount = $instalmentAmount;
        $this->toPay = $toPay;
        $this->loanTerm = $loanTerm;
        $this->rrso = $rrso;
        $this->representativeExample = $representativeExample;
        $this->remarks = $remarks;
        $this->loanParameters = $loanParameters;
    }
}
