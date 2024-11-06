<?php

namespace Comfino\Shop\Order\Customer;

class Address implements AddressInterface
{
    /**
     * @var string|null
     * @readonly
     */
    private $street;
    /**
     * @var string|null
     * @readonly
     */
    private $buildingNumber;
    /**
     * @var string|null
     * @readonly
     */
    private $apartmentNumber;
    /**
     * @var string|null
     * @readonly
     */
    private $postalCode;
    /**
     * @var string|null
     * @readonly
     */
    private $city;
    /**
     * @var string|null
     * @readonly
     */
    private $countryCode;
    /**
     * @param string|null $street
     * @param string|null $buildingNumber
     * @param string|null $apartmentNumber
     * @param string|null $postalCode
     * @param string|null $city
     * @param string|null $countryCode
     */
    public function __construct(?string $street = null, ?string $buildingNumber = null, ?string $apartmentNumber = null, ?string $postalCode = null, ?string $city = null, ?string $countryCode = null)
    {
        $this->street = $street;
        $this->buildingNumber = $buildingNumber;
        $this->apartmentNumber = $apartmentNumber;
        $this->postalCode = $postalCode;
        $this->city = $city;
        $this->countryCode = $countryCode;
    }

    /**
     * @inheritDoc
     */
    public function getStreet(): ?string
    {
        return $this->street;
    }

    /**
     * @inheritDoc
     */
    public function getBuildingNumber(): ?string
    {
        return $this->buildingNumber;
    }

    /**
     * @inheritDoc
     */
    public function getApartmentNumber(): ?string
    {
        return $this->apartmentNumber;
    }

    /**
     * @inheritDoc
     */
    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    /**
     * @inheritDoc
     */
    public function getCity(): ?string
    {
        return $this->city;
    }

    /**
     * @inheritDoc
     */
    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }
}
