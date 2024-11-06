<?php

namespace Comfino\Api\Dto\Payment;

class LoanParameters
{
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

    /**
     * @param int $instalmentAmount
     * @param int $toPay
     * @param int $loanTerm
     * @param float $rrso
     */
    public function __construct(int $instalmentAmount, int $toPay, int $loanTerm, float $rrso)
    {
        $this->instalmentAmount = $instalmentAmount;
        $this->toPay = $toPay;
        $this->loanTerm = $loanTerm;
        $this->rrso = $rrso;
    }
}
