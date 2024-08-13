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
        $total = (int) ($cart->get_total() * 100);

        if ($loanAmount > $total) {
            // Loan amount with price modifier (e.g. custom commission).
            $total = $loanAmount;
        }

        return new Cart(
            $total,
            (int) ($cart->get_shipping_total() * 100),
            array_map(static function (array $item): CartItemInterface {
                /** @var \WC_Product_Simple $product */
                $product = $item['data'];
                $imageId = $product->get_image_id();

                if ($imageId !== '') {
                    $imageUrl = wp_get_attachment_image_url($imageId, 'full');
                } else {
                    $imageUrl = null;
                }

                return new CartItem(
                    new Product(
                        $product->get_name(),
                        (int) (wc_get_price_including_tax($product) * 100),
                        (string) $product->get_id(),
                        strip_tags(wc_get_product_category_list($product->get_id())),
                        $product->get_sku(),
                        $imageUrl,
                        $product->get_category_ids()
                    ),
                    (int) $item['quantity']
                );
            }, $cart->get_cart())
        );
    }

    public static function getShopCartFromProduct(\WC_Product_Simple $product): Cart
    {
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
                        $product->get_category_ids()
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
