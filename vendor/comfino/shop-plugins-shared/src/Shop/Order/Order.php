<?php

declare(strict_types=1);

namespace Comfino\Shop\Order;

class Order implements OrderInterface
{
    /**
     * @var string
     * @readonly
     */
    private $id;
    /**
     * @var string
     * @readonly
     */
    private $returnUrl;
    /**
     * @var LoanParametersInterface
     * @readonly
     */
    private $loanParameters;
    /**
     * @var CartInterface
     * @readonly
     */
    private $cart;
    /**
     * @var CustomerInterface
     * @readonly
     */
    private $customer;
    /**
     * @var string|null
     * @readonly
     */
    private $notifyUrl;
    /**
     * @var SellerInterface|null
     * @readonly
     */
    private $seller;
    /**
     * @var string|null
     * @readonly
     */
    private $accountNumber;
    /**
     * @var string|null
     * @readonly
     */
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
    public function __construct(string $id, string $returnUrl, LoanParametersInterface $loanParameters, CartInterface $cart, CustomerInterface $customer, ?string $notifyUrl = null, ?SellerInterface $seller = null, ?string $accountNumber = null, ?string $transferTitle = null)
    {
        $this->id = $id;
        $this->returnUrl = $returnUrl;
        $this->loanParameters = $loanParameters;
        $this->cart = $cart;
        $this->customer = $customer;
        $this->notifyUrl = $notifyUrl;
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
        return $this->notifyUrl !== null ? trim(strip_tags($this->notifyUrl)) : null;
    }

    /**
     * @inheritDoc
     */
    public function getReturnUrl(): string
    {
        return trim(strip_tags($this->returnUrl));
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
        return $this->accountNumber !== null ? trim(html_entity_decode(strip_tags($this->accountNumber))) : null;
    }

    /**
     * @inheritDoc
     */
    public function getTransferTitle(): ?string
    {
        return $this->transferTitle !== null ? trim(html_entity_decode(strip_tags($this->transferTitle))) : null;
    }
}
