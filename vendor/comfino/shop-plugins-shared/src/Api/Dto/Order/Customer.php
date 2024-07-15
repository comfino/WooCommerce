<?php

namespace Comfino\Api\Dto\Order;

use Comfino\Api\Dto\Order\Customer\Address;

class Customer
{
    /** @var string
     * @readonly */
    public $firstName;
    /** @var string
     * @readonly */
    public $lastName;
    /** @var string
     * @readonly */
    public $email;
    /** @var string
     * @readonly */
    public $phoneNumber;
    /** @var string
     * @readonly */
    public $ip;
    /** @var string|null
     * @readonly */
    public $taxId;
    /** @var bool|null
     * @readonly */
    public $regular;
    /** @var bool|null
     * @readonly */
    public $logged;
    /** @var Address|null
     * @readonly */
    public $address;

    /**
     * @param string $firstName
     * @param string $lastName
     * @param string $email
     * @param string $phoneNumber
     * @param string $ip
     * @param string|null $taxId
     * @param bool|null $regular
     * @param bool|null $logged
     * @param Address|null $address
     */
    public function __construct(
        string $firstName,
        string $lastName,
        string $email,
        string $phoneNumber,
        string $ip,
        ?string $taxId,
        ?bool $regular,
        ?bool $logged,
        ?Address $address
    ) {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->phoneNumber = $phoneNumber;
        $this->ip = $ip;
        $this->taxId = $taxId;
        $this->regular = $regular;
        $this->logged = $logged;
        $this->address = $address;
    }
}
