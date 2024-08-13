<?php

namespace Comfino\Api\Response;

use Comfino\Api\Dto\Order\Cart;
use Comfino\Api\Dto\Order\Customer;
use Comfino\Api\Dto\Order\LoanParameters;
use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Api\Exception\ResponseValidationError;

class GetOrder extends Base
{
    /** @var string
     * @readonly */
    public $orderId;
    /** @var string
     * @readonly */
    public $status;
    /** @var \DateTime|null
     * @readonly */
    public $createdAt;
    /** @var string
     * @readonly */
    public $applicationUrl;
    /** @var string
     * @readonly */
    public $notifyUrl;
    /** @var string
     * @readonly */
    public $returnUrl;
    /** @var LoanParameters
     * @readonly */
    public $loanParameters;
    /** @var Cart
     * @readonly */
    public $cart;
    /** @var Customer
     * @readonly */
    public $customer;

    /**
     * @param mixed[]|string|bool|null $deserializedResponseBody
     */
    protected function processResponseBody($deserializedResponseBody): void
    {
        if (!is_array($deserializedResponseBody)) {
            throw new ResponseValidationError('Invalid response data: array expected.');
        }

        try {
            $createdAt = new \DateTime($deserializedResponseBody['createdAt']);
        } catch (\Exception $exception)  {
            $createdAt = null;
        }

        $this->orderId = $deserializedResponseBody['orderId'];
        $this->status = $deserializedResponseBody['status'];
        $this->createdAt = $createdAt;
        $this->applicationUrl = $deserializedResponseBody['applicationUrl'];
        $this->notifyUrl = $deserializedResponseBody['notifyUrl'];
        $this->returnUrl = $deserializedResponseBody['returnUrl'];

        $this->loanParameters = new LoanParameters(
            $deserializedResponseBody['loanParameters']['amount'],
            $deserializedResponseBody['loanParameters']['maxAmount'],
            $deserializedResponseBody['loanParameters']['term'],
            LoanTypeEnum::from($deserializedResponseBody['loanParameters']['type']),
            $deserializedResponseBody['loanParameters']['allowedProductTypes'] !== null ? array_map(
                static function (string $productType) : LoanTypeEnum {
                    return LoanTypeEnum::from($productType);
                },
                $deserializedResponseBody['loanParameters']['allowedProductTypes']
            ) : null
        );

        $this->cart = new Cart(
            $deserializedResponseBody['cart']['totalAmount'],
            $deserializedResponseBody['cart']['deliveryCost'],
            $deserializedResponseBody['cart']['category'],
            array_map(
                static function (array $cartItem) : Cart\CartItem {
                    return new Cart\CartItem(
                        $cartItem['name'],
                        $cartItem['price'],
                        $cartItem['quantity'],
                        $cartItem['externalId'],
                        $cartItem['photoUrl'],
                        $cartItem['ean'],
                        $cartItem['category']
                    );
                },
                $deserializedResponseBody['cart']['products']
            )
        );

        $this->customer = new Customer(
            $deserializedResponseBody['customer']['firstName'],
            $deserializedResponseBody['customer']['lastName'],
            $deserializedResponseBody['customer']['email'],
            $deserializedResponseBody['customer']['phoneNumber'],
            $deserializedResponseBody['customer']['ip'],
            $deserializedResponseBody['customer']['taxId'],
            $deserializedResponseBody['customer']['regular'],
            $deserializedResponseBody['customer']['logged'],
            $deserializedResponseBody['customer']['address'] !== null ? new Customer\Address(
                $deserializedResponseBody['customer']['address']['street'],
                $deserializedResponseBody['customer']['address']['buildingNumber'],
                $deserializedResponseBody['customer']['address']['apartmentNumber'],
                $deserializedResponseBody['customer']['address']['postalCode'],
                $deserializedResponseBody['customer']['address']['city'],
                $deserializedResponseBody['customer']['address']['countryCode']
            ) : null
        );
    }
}
