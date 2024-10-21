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
        $total = (int) ($cart->get_total('edit') * 100);

        if ($loanAmount > $total) {
            // Loan amount with price modifier (e.g. custom commission).
            $total = $loanAmount;
        }

        return new Cart(
            $total,
            (int) ($cart->get_shipping_total() * 100),
            array_map(static function (array $item): CartItemInterface {
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
                        $taxRate !== null ? (int) ($taxRate['rate'] * 100) : null,
                        $taxRate !== null ? (int) (wc_get_price_including_tax($product) - wc_get_price_excluding_tax($product)) : null
                    ),
                    (int) $item['quantity']
                );
            }, $cart->get_cart())
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
            0,
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
                        $taxRate !== null ? (int) ($taxRate['rate'] * 100) : null,
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
