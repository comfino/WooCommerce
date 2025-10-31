<?php

declare(strict_types=1);

namespace Comfino\Api\Dto\Order;

use Comfino\Api\Dto\Payment\LoanTypeEnum;

class LoanParameters
{
    /** @var int
     * @readonly */
    public $amount;
    /** @var int|null
     * @readonly */
    public $maxAmount;
    /** @var int
     * @readonly */
    public $term;
    /** @var LoanTypeEnum
     * @readonly */
    public $type;
    /** @var LoanTypeEnum[]|null
     * @readonly */
    public $allowedProductTypes;

    /**
     * @param int $amount
     * @param int|null $maxAmount
     * @param int $term
     * @param LoanTypeEnum $type
     * @param LoanTypeEnum[]|null $allowedProductTypes
     */
    public function __construct(
        int $amount,
        ?int $maxAmount,
        int $term,
        LoanTypeEnum $type,
        ?array $allowedProductTypes
    ) {
        $this->amount = $amount;
        $this->maxAmount = $maxAmount;
        $this->term = $term;
        $this->type = $type;
        $this->allowedProductTypes = $allowedProductTypes;
    }
}
