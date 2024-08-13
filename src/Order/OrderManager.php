<?php

namespace Comfino\Order;

use Comfino\Common\Shop\Cart;
use Comfino\Shop\Order\Cart\CartItem;
use Comfino\Shop\Order\Cart\CartItemInterface;
use Comfino\Shop\Order\Cart\Product;
use Comfino\Tools;

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

    public static function getShopCartFromProduct(\Product $product): Cart
    {
        return new Cart(
            (int) ($product->getPrice() * 100),
            0,
            [
                new CartItem(
                    new Product(
                        is_array($product->name) ? current($product->name) : $product->name,
                        (int) ($product->getPrice() * 100),
                        (string) $product->id,
                        null,
                        null,
                        null,
                        array_map('intval', $product->getCategories())
                    ),
                    1
                ),
            ]
        );
    }

    public static function checkCartCurrency(\PaymentModule $module, \Cart $cart): bool
    {
        $currencyOrder = new \Currency($cart->id_currency);
        $currenciesModule = $module->getCurrency($cart->id_currency);

        if (is_array($currenciesModule)) {
            foreach ($currenciesModule as $currencyModule) {
                if ((int) $currencyOrder->id === (int) $currencyModule['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function validateCustomerData(\PaymentModule $module, array $params): string
    {
        $vatNumber = $params['form']->getField('vat_number');
        $tools = new Tools(\Context::getContext());

        if (!empty($vatNumber->getValue()) && !$tools->isValidTaxId($vatNumber->getValue())) {
            $vatNumber->addError($module->l('Invalid VAT number.'));

            return '0';
        }

        return '1';
    }
}