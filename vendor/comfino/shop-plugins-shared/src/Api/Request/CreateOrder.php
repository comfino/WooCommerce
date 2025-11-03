<?php

declare(strict_types=1);

namespace Comfino\Api\Request;

use Comfino\Api\Request;
use Comfino\Shop\Order\CartTrait;
use Comfino\Shop\Order\OrderInterface;

/**
 * Loan application creation request.
 */
class CreateOrder extends Request
{
    /**
     * @var OrderInterface
     * @readonly
     */
    private $order;
    /**
     * @var bool
     * @readonly
     */
    private $validateOnly = false;
    use CartTrait;

    /**
     * @param OrderInterface $order Full order data (cart, loan details).
     * @param bool $validateOnly Flag used for order validation (if true, order is not created and only validation result is returned).
     */
    public function __construct(OrderInterface $order, bool $validateOnly = false)
    {
        $this->order = $order;
        $this->validateOnly = $validateOnly;
        $this->setRequestMethod('POST');
        $this->setApiEndpointPath('orders');
    }

    /**
     * @inheritDoc
     */
    protected function prepareRequestBody(): array
    {
        $customer = $this->order->getCustomer();

        return array_filter(
            [
                // Basic order data
                'notifyUrl' => $this->order->getNotifyUrl(),
                'returnUrl' => $this->order->getReturnUrl(),
                'orderId' => $this->order->getId(),

                // Payment data
                'loanParameters' => array_filter(
                    [
                        'amount' => $this->order->getLoanParameters()->getAmount(),
                        'term' => $this->order->getLoanParameters()->getTerm(),
                        'type' => $this->order->getLoanParameters()->getType(),
                        'allowedProductTypes' => $this->order->getLoanParameters()->getAllowedProductTypes(),
                    ],
                    static function ($value) : bool {
                        return $value !== null;
                    }
                ),

                // Cart with list of products
                'cart' => $this->getCartAsArray($this->order->getCart()),

                // Customer data (mandatory)
                'customer' => array_filter(
                    [
                        'firstName' => $customer->getFirstName(),
                        'lastName' => $customer->getLastName(),
                        'email' => $customer->getEmail(),
                        'phoneNumber' => $customer->getPhoneNumber(),
                        'taxId' => $customer->getTaxId(),
                        'ip' => $customer->getIp(),
                        'regular' => $customer->isRegular(),
                        'logged' => $customer->isLogged(),

                        // Customer address (optional)
                        'address' => count(
                            $address = array_filter(
                                [
                                    'street' => ($nullsafeVariable1 = $customer->getAddress()) ? $nullsafeVariable1->getStreet() : null,
                                    'buildingNumber' => ($nullsafeVariable2 = $customer->getAddress()) ? $nullsafeVariable2->getBuildingNumber() : null,
                                    'apartmentNumber' => ($nullsafeVariable3 = $customer->getAddress()) ? $nullsafeVariable3->getApartmentNumber() : null,
                                    'postalCode' => ($nullsafeVariable4 = $customer->getAddress()) ? $nullsafeVariable4->getPostalCode() : null,
                                    'city' => ($nullsafeVariable5 = $customer->getAddress()) ? $nullsafeVariable5->getCity() : null,
                                    'countryCode' => ($nullsafeVariable6 = $customer->getAddress()) ? $nullsafeVariable6->getCountryCode() : null,
                                ],
                                static function ($value) : bool {
                                    return $value !== null;
                                }
                            )
                        ) ? $address : null,
                    ],
                    static function ($value) : bool {
                        return $value !== null;
                    }
                ),

                // Seller data (optional)
                'seller' => count(
                    $seller = array_filter(
                        ['taxId' => ($nullsafeVariable7 = $this->order->getSeller()) ? $nullsafeVariable7->getTaxId() : null],
                        static function ($value) : bool {
                            return $value !== null;
                        }
                    )
                ) ? $seller : null,

                // Extra data (optional)
                'accountNumber' => $this->order->getAccountNumber(),
                'transferTitle' => $this->order->getTransferTitle(),
                'simulation' => $this->validateOnly ?: null,
            ],
            static function ($value) : bool {
                return $value !== null;
            }
        );
    }
}
