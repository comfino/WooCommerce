<?php

namespace Comfino\Api\Request;

use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Api\Request;
use Comfino\Shop\Order\CartInterface;
use Comfino\Shop\Order\CartTrait;

class GetPaywallItemDetails extends Request
{
    /**
     * @var CartInterface
     * @readonly
     */
    private $cart;
    use CartTrait;

    /**
     * @param int $loanAmount
     * @param LoanTypeEnum $loanType
     * @param CartInterface $cart
     */
    public function __construct(int $loanAmount, LoanTypeEnum $loanType, CartInterface $cart)
    {
        $this->cart = $cart;
        $this->setRequestMethod('POST');
        $this->setApiEndpointPath('shop-plugin-paywall-product-details');
        $this->setRequestParams(['loanAmount' => $loanAmount, 'loanTypeSelected' => (string) $loanType]);
    }

    /**
     * @inheritDoc
     */
    protected function prepareRequestBody(): ?array
    {
        return $this->getCartAsArray($this->cart);
    }
}
