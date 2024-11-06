<?php

namespace Comfino\Order;

use Comfino\Common\Shop\Cart;
use Comfino\Shop\Order\Cart\CartItem;
use Comfino\Shop\Order\Cart\CartItemInterface;
use Comfino\Shop\Order\Cart\Product;

if (!defined('ABSPATH')) {
    exit;
}

final class OrderManager
{
    public static function getShopCart(\WC_Cart $cart, int $loanAmount): Cart
    {
        $totalValue = (int) ($cart->get_total('edit') * 100);

        if ($loanAmount > $totalValue) {
            // Loan amount with price modifier (e.g. custom commission).
            $totalValue = $loanAmount;
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

                $grossPrice = (int) (wc_get_price_including_tax($product) * 100);
                $netPrice = (int) (wc_get_price_excluding_tax($product) * 100);

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
                        strip_tags(wc_get_product_category_list($product->get_id()), ','),
                        $product->get_sku(),
                        $imageUrl,
                        $product->get_category_ids(),
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

        if ($totalNetValue === 0) {
            $totalNetValue = null;
        }

        if ($totalTaxValue === 0) {
            $totalTaxValue = null;
        }

        $deliveryCost = (int) (($cart->get_shipping_total() + $cart->get_shipping_tax()) * 100);
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

            if (count(array_merge([], ...$cartTaxRates)) > 0) {
                $deliveryTaxValue = (int) ($cart->get_shipping_tax() * 100);
                $deliveryNetCost = $deliveryCost - $deliveryTaxValue;
                $deliveryTaxRate = 0;
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

    public static function getShopCartFromProduct(\WC_Product $product): Cart
    {
        if (!empty($taxRates = \WC_Tax::get_rates($product->get_tax_class()))) {
            $taxRate = reset($taxRates);
        } else {
            $taxRate = null;
        }

        return new Cart(
            (int) (wc_get_price_including_tax($product) * 100),
            null,
            null,
            0,
            null,
            null,
            null,
            [
                new CartItem(
                    new Product(
                        $product->get_name(),
                        (int) (wc_get_price_including_tax($product) * 100),
                        (string) $product->get_id(),
                        null,
                        null,
                        null,
                        $product->get_category_ids(),
                        $taxRates !== null ? (int) (wc_get_price_excluding_tax($product) * 100) : null,
                        $taxRate !== null ? (int) $taxRate['rate'] : null,
                        $taxRate !== null ? (int) ((wc_get_price_including_tax($product) - wc_get_price_excluding_tax($product)) * 100) : null
                    ),
                    1
                ),
            ]
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
}
