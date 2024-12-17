<?php

namespace Comfino\Shop\Order;

use Comfino\Shop\Order\Customer\AddressInterface;

class Customer implements CustomerInterface
{
    /**
     * @var string
     * @readonly
     */
    private $firstName;
    /**
     * @var string
     * @readonly
     */
    private $lastName;
    /**
     * @var string
     * @readonly
     */
    private $email;
    /**
     * @var string
     * @readonly
     */
    private $phoneNumber;
    /**
     * @var string
     * @readonly
     */
    private $ip;
    /**
     * @var string|null
     * @readonly
     */
    private $taxId;
    /**
     * @var bool|null
     * @readonly
     */
    private $isRegular;
    /**
     * @var bool|null
     * @readonly
     */
    private $isLogged;
    /**
     * @var AddressInterface|null
     * @readonly
     */
    private $address;
    /**
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @param string $phoneNumber
     * @param string $ip
     * @param string|null $taxId
     * @param bool|null $isRegular
     * @param bool|null $isLogged
     * @param AddressInterface|null $address
     */
    public function __construct(string $firstName, string $lastName, string $email, string $phoneNumber, string $ip, ?string $taxId = null, ?bool $isRegular = null, ?bool $isLogged = null, ?AddressInterface $address = null)
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->phoneNumber = $phoneNumber;
        $this->ip = $ip;
        $this->taxId = $taxId;
        $this->isRegular = $isRegular;
        $this->isLogged = $isLogged;
        $this->address = $address;
    }

    /**
     * @inheritDoc
     */
    public function getFirstName(): string
    {
        return trim(strip_tags($this->firstName));
    }

    /**
     * @inheritDoc
     */
    public function getLastName(): string
    {
        return trim(strip_tags($this->lastName));
    }

    /**
     * @inheritDoc
     */
    public function getEmail(): string
    {
        return trim(strip_tags($this->email));
    }

    /**
     * @inheritDoc
     */
    public function getPhoneNumber(): string
    {
        return trim(strip_tags($this->phoneNumber));
    }

    /**
     * @inheritDoc
     */
    public function getIp(): string
    {
        return trim($this->ip);
    }

    /**
     * @inheritDoc
     */
    public function getTaxId(): ?string
    {
        return $this->taxId !== null ? trim(strip_tags($this->taxId)) : null;
    }

    /**
     * @inheritDoc
     */
    public function isRegular(): ?bool
    {
        return $this->isRegular;
    }

    /**
     * @inheritDoc
     */
    public function isLogged(): ?bool
    {
        return $this->isLogged;
    }

    /**
     * @inheritDoc
     */
    public function getAddress(): ?AddressInterface
    {
        return $this->address;
    }
}
