<?php

namespace Comfino\Shop\Order\Customer;

interface AddressInterface
{
    /** @return string|null */
    public function getStreet(): ?string;

    /** @return string|null */
    public function getBuildingNumber(): ?string;

    /** @return string|null */
    public function getApartmentNumber(): ?string;

    /**@return string|null */
    public function getPostalCode(): ?string;

    /** @return string|null */
    public function getCity(): ?string;

    /** @return string|null */
    public function getCountryCode(): ?string;
}
