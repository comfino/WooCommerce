<?php

namespace Comfino\Shop\Order;

use Comfino\Api\Dto\Payment\LoanTypeEnum;

class LoanParameters implements LoanParametersInterface
{
    /**
     * @var int
     * @readonly
     */
    private $amount;
    /**
     * @var int|null
     * @readonly
     */
    private $term;
    /**
     * @var LoanTypeEnum|null
     * @readonly
     */
    private $type;
    /**
     * @var LoanTypeEnum[]|null
     * @readonly
     */
    private $allowedProductTypes;
    /**
     * @param int $amount
     * @param int|null $term
     * @param LoanTypeEnum|null $type
     * @param LoanTypeEnum[]|null $allowedProductTypes
     */
    public function __construct(int $amount, ?int $term = null, ?LoanTypeEnum $type = null, ?array $allowedProductTypes = null)
    {
        $this->amount = $amount;
        $this->term = $term;
        $this->type = $type;
        $this->allowedProductTypes = $allowedProductTypes;
    }

    /**
     * @inheritDoc
     */
    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * @inheritDoc
     */
    public function getTerm(): ?int
    {
        return $this->term;
    }

    /**
     * @inheritDoc
     */
    public function getType(): ?LoanTypeEnum
    {
        return $this->type;
    }

    /**
     * @inheritDoc
     */
    public function getAllowedProductTypes(): ?array
    {
        return $this->allowedProductTypes;
    }
}
