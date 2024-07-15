<?php

namespace Comfino\Shop\Order;

use Comfino\Shop\Order\LoanParametersInterface;

class Order implements OrderInterface
{
    /** @var string */
    private $id;
    /** @var string|null */
    private $notifyUrl;
    /** @var string */
    private $returnUrl;
    /** @var LoanParametersInterface */
    private $loanParameters;
    /** @var CartInterface */
    private $cart;
    /** @var CustomerInterface */
    private $customer;
    /** @var SellerInterface|null */
    private $seller;
    /** @var string|null */
    private $accountNumber;
    /** @var string|null */
    private $transferTitle;

    /**
     * @param string $id
     * @param string $returnUrl
     * @param LoanParametersInterface $loanParameters
     * @param CartInterface $cart
     * @param CustomerInterface $customer
     * @param string|null $notifyUrl
     * @param SellerInterface|null $seller
     * @param string|null $accountNumber
     * @param string|null $transferTitle
     */
    public function __construct(
        string $id,
        string $returnUrl,
        LoanParametersInterface $loanParameters,
        CartInterface $cart,
        CustomerInterface $customer,
        ?string $notifyUrl = null,
        ?SellerInterface $seller = null,
        ?string $accountNumber = null,
        ?string $transferTitle = null
    ) {
        $this->id = $id;
        $this->notifyUrl = $notifyUrl;
        $this->returnUrl = $returnUrl;
        $this->loanParameters = $loanParameters;
        $this->cart = $cart;
        $this->customer = $customer;
        $this->seller = $seller;
        $this->accountNumber = $accountNumber;
        $this->transferTitle = $transferTitle;
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @inheritDoc
     */
    public function getNotifyUrl(): ?string
    {
        return $this->notifyUrl;
    }

    /**
     * @inheritDoc
     */
    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }

    /**
     * @inheritDoc
     */
    public function getLoanParameters(): LoanParametersInterface
    {
        return $this->loanParameters;
    }

    /**
     * @inheritDoc
     */
    public function getCart(): CartInterface
    {
        return $this->cart;
    }

    /**
     * @inheritDoc
     */
    public function getCustomer(): CustomerInterface
    {
        return $this->customer;
    }

    /**
     * @inheritDoc
     */
    public function getSeller(): ?SellerInterface
    {
        return $this->seller;
    }

    /**
     * @inheritDoc
     */
    public function getAccountNumber(): ?string
    {
        return $this->accountNumber;
    }

    /**
     * @inheritDoc
     */
    public function getTransferTitle(): ?string
    {
        return $this->transferTitle;
    }
}
