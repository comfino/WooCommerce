<?php

namespace Comfino\Order;

use Comfino\Common\Shop\Cart;
use Comfino\Shop\Order\Cart\CartItem;
use Comfino\Shop\Order\Cart\CartItemInterface;
use Comfino\Shop\Order\Cart\Product;
use Comfino\Shop\Order\Customer;
use Comfino\Shop\Order\Customer\Address;

if (!defined('ABSPATH')) {
    exit;
}

final class OrderManager
{
    /**
     * @param \WC_Cart $cart
     * @param int $priceModifier
     *
     * @return Cart Comfino cart structure.
     *
     * @throws \Exception|\InvalidArgumentException
     */
    public static function getShopCart(\WC_Cart $cart, int $priceModifier = 0): Cart
    {
        $totalValue = (int) round($cart->get_total('edit') * 100);

        if ($totalValue < 0) {
            throw new \InvalidArgumentException('Total value must be greater than 0.');
        }

        if ($priceModifier > 0 && $priceModifier < $totalValue) {
            // Add price modifier (e.g. custom commission).
            $totalValue += $priceModifier;
        }

        $cartItems = array_map(
            static function (array $item): CartItemInterface {
                /** @var \WC_Product $product */
                $product = $item['data'];
                $imageId = $product->get_image_id();

                if ($imageId !== '') {
                    $imageUrl = wp_get_attachment_image_url($imageId, 'full');
                } else {
                    $imageUrl = null;
                }

                $categoryIds = $product->get_category_ids();

                if (empty($categoryIds) && $product instanceof \WC_Product_Variation
                    && ($parentProduct = wc_get_product($product->get_parent_id())) instanceof \WC_Product
                ) {
                    $categoryIds = $parentProduct->get_category_ids();
                }

                $grossPrice = (int) round(wc_get_price_including_tax($product) * 100);
                $netPrice = (int) round(wc_get_price_excluding_tax($product) * 100);

                if (!empty($taxRates = \WC_Tax::get_rates($product->get_tax_class()))) {
                    $taxRate = reset($taxRates);
                } else {
                    $taxRate = null;
                }

                return new CartItem(
                    new Product(
                        $product->get_name(),
                        $grossPrice,
                        (string) $product->get_id(),
                        self::getProductCategories($categoryIds),
                        $product->get_sku(),
                        $imageUrl,
                        $categoryIds,
                        $taxRate !== null ? $netPrice : null,
                        $taxRate !== null ? (int) $taxRate['rate'] : null,
                        $taxRate !== null ? $grossPrice - $netPrice : null
                    ),
                    (int) $item['quantity']
                );
            },
            $cart->get_cart()
        );

        $totalNetValue = 0;
        $totalTaxValue = 0;

        foreach ($cartItems as $cartItem) {
            if ($cartItem->getProduct()->getNetPrice() !== null) {
                $totalNetValue += ($cartItem->getProduct()->getNetPrice() * $cartItem->getQuantity());
            }

            if ($cartItem->getProduct()->getTaxValue() !== null) {
                $totalTaxValue += ($cartItem->getProduct()->getTaxValue() * $cartItem->getQuantity());
            }
        }

        if (is_float($totalNetValue) || $totalNetValue > PHP_INT_MAX) {
            throw new \InvalidArgumentException('Total net value must be integer not greater than PHP_INT_MAX.');
        }

        if (is_float($totalTaxValue) || $totalTaxValue > PHP_INT_MAX) {
            throw new \InvalidArgumentException('Total tax value must be integer not greater than PHP_INT_MAX.');
        }

        if ($totalNetValue === 0) {
            $totalNetValue = null;
        }

        if ($totalTaxValue === 0) {
            $totalTaxValue = null;
        }

        $deliveryCost = (int) round(($cart->get_shipping_total() + $cart->get_shipping_tax()) * 100);
        $deliveryNetCost = null;
        $deliveryTaxValue = null;
        $deliveryTaxRate = null;

        if (!empty($taxClasses = $cart->get_cart_item_tax_classes_for_shipping())) {
            $cartTaxRates = [];

            foreach ($taxClasses as $taxClass) {
                if (!empty($taxRates = \WC_Tax::get_rates($taxClass))) {
                    $cartTaxRates[] = $taxRates;
                }
            }

            if (count($cartTaxRates = array_merge([], ...$cartTaxRates)) > 0) {
                $taxRate = reset($cartTaxRates);
            } else {
                $taxRate = null;
            }

            if ($taxRate !== null) {
                $deliveryNetCost = (int) round($cart->get_shipping_total() * 100);
                $deliveryTaxValue = (int) round($cart->get_shipping_tax() * 100);
                $deliveryTaxRate = (int) $taxRate['rate'];
            }
        }

        return new Cart(
            $totalValue,
            $totalNetValue,
            $totalTaxValue,
            $deliveryCost,
            $deliveryNetCost,
            $deliveryTaxRate,
            $deliveryTaxValue,
            $cartItems
        );
    }

    /**
     * @param \WC_Product $product WooCommerce product entity.
     *
     * @return Cart Comfino cart structure.
     */
    public static function getShopCartFromProduct(\WC_Product $product): Cart
    {
        if (!empty($taxRates = \WC_Tax::get_rates($product->get_tax_class()))) {
            $taxRate = reset($taxRates);
        } else {
            $taxRate = null;
        }

        $categoryIds = $product->get_category_ids();

        if (empty($categoryIds) && $product instanceof \WC_Product_Variation
            && ($parentProduct = wc_get_product($product->get_parent_id())) instanceof \WC_Product
        ) {
            $categoryIds = $parentProduct->get_category_ids();
        }

        $grossPrice = (int) (wc_get_price_including_tax($product) * 100);
        $netPrice = ($taxRates !== null ? (int) (wc_get_price_excluding_tax($product) * 100) : null);
        $taxValue = ($taxRate !== null ? $grossPrice - $netPrice : null);

        return new Cart(
            $grossPrice,
            $netPrice,
            $taxValue,
            0,
            null,
            null,
            null,
            [
                new CartItem(
                    new Product(
                        $product->get_name(),
                        $grossPrice,
                        (string) $product->get_id(),
                        self::getProductCategories($categoryIds),
                        $product->get_sku(),
                        null,
                        $categoryIds,
                        $netPrice,
                        $taxRate !== null ? (int) $taxRate['rate'] : null,
                        $taxValue
                    ),
                    1
                ),
            ]
        );
    }

    /**
     * @param \WC_Order $order WooCommerce order entity.
     *
     * @return Customer Comfino customer structure.
     */
    public static function getShopCustomerFromOrder(\WC_Order $order): Customer
    {
        $phoneNumber = trim($order->get_billing_phone());

        if (empty($phoneNumber)) {
            // Try to find phone number in order metadata.
            $orderMetadata = $order->get_meta_data();

            foreach ($orderMetadata as $metaDataItem) {
                /** @var \WC_Meta_Data $metaDataItem */
                $metaData = $metaDataItem->get_data();

                if (stripos($metaData['key'], 'tel') !== false || stripos($metaData['key'], 'phone') !== false) {
                    $metaValue = str_replace(['-', ' ', '(', ')'], '', trim($metaData['value']));

                    if (preg_match('/^(?:\+?\d{1,2})?\d{9}$|^(?:\d{2,3})?\d{7}$/', $metaValue)) {
                        $phoneNumber = $metaValue;

                        break;
                    }
                }
            }
        }

        if (empty($phoneNumber)) {
            $phoneNumber = trim($order->get_shipping_phone());
        }

        if (!empty(trim($order->get_billing_first_name()))) {
            // Use billing address to get customer names.
            [$firstName, $lastName] = self::prepareCustomerNames($order->get_billing_first_name(), $order->get_billing_last_name());
        } else {
            // Use delivery address to get customer names.
            [$firstName, $lastName] = self::prepareCustomerNames($order->get_shipping_first_name(), $order->get_shipping_last_name());
        }

        $billingAddressLines = $order->get_billing_address_1();

        if (!empty($order->get_billing_address_2())) {
            $billingAddressLines .= " {$order->get_billing_address_2()}";
        }

        if (empty($billingAddressLines)) {
            $deliveryAddressLines = $order->get_shipping_address_1();

            if (!empty($order->get_shipping_address_2())) {
                $deliveryAddressLines .= " {$order->get_shipping_address_2()}";
            }

            $street = trim($deliveryAddressLines);
        } else {
            $street = trim($billingAddressLines);
        }

        $addressParts = explode(' ', $street);
        $buildingNumber = '';

        if (count($addressParts) > 1) {
            foreach ($addressParts as $idx => $addressPart) {
                if (preg_match('/^\d+[a-zA-Z]?$/', trim($addressPart))) {
                    $street = implode(' ', array_slice($addressParts, 0, $idx));
                    $buildingNumber = trim($addressPart);
                }
            }
        }

        /** @see https://woocommerce.com/document/eu-vat-number/ */
        $customerTaxId = function_exists('wc_eu_vat_get_vat_from_order') ? trim(str_replace('-', '', wc_eu_vat_get_vat_from_order($order))) : '';

        return new Customer(
            $firstName,
            $lastName,
            $order->get_billing_email(),
            $phoneNumber,
            \WC_Geolocation::get_ip_address(),
            preg_match('/^[A-Z]{0,3}\d{7,}$/', $customerTaxId) ? $customerTaxId : null,
            $order->get_user() !== false,
            is_user_logged_in(),
            new Address(
                $street,
                $buildingNumber,
                null,
                $order->get_billing_postcode(),
                $order->get_billing_city(),
                $order->get_billing_country()
            )
        );
    }

    public static function getOrderStatusNotes(int $orderId, array $statuses): array
    {
        $orderNotes = wc_get_order_notes(['order_id' => $orderId]);
        $notes = [];

        foreach ($orderNotes as $note) {
            foreach ($statuses as $status) {
                if ($note->added_by === 'system' && $note->content === "Comfino status: $status") {
                    $notes[$status] = $note;
                }
            }
        }

        return $notes;
    }

    /**
     * @param int[] $categoryIds
     */
    private static function getProductCategories(array $categoryIds): string
    {
        if (empty($categoryIds)) {
            return '';
        }

        $categories = [];

        foreach ($categoryIds as $categoryId) {
            if (($term = get_term($categoryId, 'product_cat')) instanceof \WP_Term) {
                $categories[] = trim($term->name);
            }
        }

        return implode('→', $categories);
    }

    private static function prepareCustomerNames(string $firstName, string $lastName): array
    {
        $firstName = trim($firstName);
        $lastName = trim($lastName);

        if (empty($lastName)) {
            $nameParts = explode(' ', $firstName);

            if (count($nameParts) > 1) {
                [$firstName, $lastName] = $nameParts;
            }
        }

        return [$firstName, $lastName];
    }
}
