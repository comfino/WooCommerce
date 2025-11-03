<?php

declare(strict_types=1);

namespace Comfino\Api\Dto\Order\Customer;

class Address
{
    /** @var string|null
     * @readonly */
    public $street;
    /** @var string|null
     * @readonly */
    public $buildingNumber;
    /** @var string|null
     * @readonly */
    public $apartmentNumber;
    /** @var string|null
     * @readonly */
    public $postalCode;
    /** @var string|null
     * @readonly */
    public $city;
    /** @var string|null
     * @readonly */
    public $countryCode;

    /**
     * @param string|null $street
     * @param string|null $buildingNumber
     * @param string|null $apartmentNumber
     * @param string|null $postalCode
     * @param string|null $city
     * @param string|null $countryCode
     */
    public function __construct(
        ?string $street,
        ?string $buildingNumber,
        ?string $apartmentNumber,
        ?string $postalCode,
        ?string $city,
        ?string $countryCode
    ) {
        $this->street = $street;
        $this->buildingNumber = $buildingNumber;
        $this->apartmentNumber = $apartmentNumber;
        $this->postalCode = $postalCode;
        $this->city = $city;
        $this->countryCode = $countryCode;
    }
}
