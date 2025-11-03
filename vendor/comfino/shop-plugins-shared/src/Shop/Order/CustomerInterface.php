<?php

declare(strict_types=1);

namespace Comfino\Shop\Order;

use Comfino\Shop\Order\Customer\AddressInterface;

interface CustomerInterface
{
    /** @return string */
    public function getFirstName(): string;

    /** @return string */
    public function getLastName(): string;

    /** @return string */
    public function getEmail(): string;

    /** @return string */
    public function getPhoneNumber(): string;

    /** @return string */
    public function getIp(): string;

    /** @return string|null */
    public function getTaxId(): ?string;

    /** @return bool|null */
    public function isRegular(): ?bool;

    /** @return bool|null */
    public function isLogged(): ?bool;

    /** @return AddressInterface|null */
    public function getAddress(): ?AddressInterface;
}
