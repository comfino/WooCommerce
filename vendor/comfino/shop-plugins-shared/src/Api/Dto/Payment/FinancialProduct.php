<?php

declare(strict_types=1);

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
    public $creditorName;
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
    /** @var int|null
     * @readonly */
    public $initialPaymentValue;
    /** @var float|null
     * @readonly */
    public $initialPaymentRate;
    /** @var int|null
     * @readonly */
    public $redemptionPaymentValue;
    /** @var float|null
     * @readonly */
    public $redemptionPaymentRate;
    /** @var float|null
     * @readonly */
    public $offerRate;

    /**
     * @param string $name
     * @param LoanTypeEnum $type
     * @param string $creditorName
     * @param string $description
     * @param string $icon
     * @param int $instalmentAmount
     * @param int $toPay
     * @param int $loanTerm
     * @param float $rrso
     * @param string $representativeExample
     * @param string|null $remarks
     * @param LoanParameters[] $loanParameters
     * @param int|null $initialPaymentValue
     * @param float|null $initialPaymentRate
     * @param int|null $redemptionPaymentValue
     * @param float|null $redemptionPaymentRate
     * @param float|null $offerRate
     */
    public function __construct(
        string $name,
        LoanTypeEnum $type,
        string $creditorName,
        string $description,
        string $icon,
        int $instalmentAmount,
        int $toPay,
        int $loanTerm,
        float $rrso,
        string $representativeExample,
        ?string $remarks,
        array $loanParameters,
        ?int $initialPaymentValue = null,
        ?float $initialPaymentRate = null,
        ?int $redemptionPaymentValue = null,
        ?float $redemptionPaymentRate = null,
        ?float $offerRate = null
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->creditorName = $creditorName;
        $this->description = $description;
        $this->icon = $icon;
        $this->instalmentAmount = $instalmentAmount;
        $this->toPay = $toPay;
        $this->loanTerm = $loanTerm;
        $this->rrso = $rrso;
        $this->representativeExample = $representativeExample;
        $this->remarks = $remarks;
        $this->loanParameters = $loanParameters;
        $this->initialPaymentValue = $initialPaymentValue;
        $this->initialPaymentRate = $initialPaymentRate;
        $this->redemptionPaymentValue = $redemptionPaymentValue;
        $this->redemptionPaymentRate = $redemptionPaymentRate;
        $this->offerRate = $offerRate;
    }
}
