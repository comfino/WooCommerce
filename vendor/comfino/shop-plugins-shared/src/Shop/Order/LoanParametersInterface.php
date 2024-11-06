<?php

namespace Comfino\Shop\Order;

use Comfino\Api\Dto\Payment\LoanTypeEnum;

interface LoanParametersInterface
{
    /**
     * Requested loan amount.
     *
     * @return int
     */
    public function getAmount(): int;

    /**
     * Number of requested installments.
     *
     * @return int|null
     */
    public function getTerm(): ?int;

    /**
     * Selected financial product type.
     *
     * @return LoanTypeEnum|null
     */
    public function getType(): ?LoanTypeEnum;

    /**
     * List of allowed product types as alternatives to the selected product type, displayed on the transaction website.
     *
     * @return LoanTypeEnum[]|null
     */
    public function getAllowedProductTypes(): ?array;
}
