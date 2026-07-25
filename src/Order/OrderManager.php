<?php

namespace Comfino\Order;

use Comfino\Common\Shop\Cart;
use Comfino\DebugLogger;
use Comfino\PaymentGateway;
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
     * Converts WooCommerce cart to Comfino cart structure for API submission.
     *
     * Transforms cart data including products, prices, delivery costs, and tax information
     * into the format expected by Comfino API. Handles product variations, category inheritance,
     * and optional price modifiers.
     *
     * @param \WC_Cart $cart WooCommerce cart object containing items and totals
     * @param int $priceModifier Optional price modifier in cents (e.g., custom commission). Default 0.
     *
     * @return Cart Comfino cart structure with cart items, totals, and delivery information
     *
     * @throws \InvalidArgumentException If cart total value is negative
     * @throws \Exception If cart data cannot be retrieved or processed
     */
    public static function getShopCart(\WC_Cart $cart, int $priceModifier = 0): Cart
    {
        $totalValue = (int) round($cart->get_total('edit') * 100);

        if ($totalValue < 0) {
            throw new \InvalidArgumentException('Total value must be greater than 0.');
        }

        if ($priceModifier > 0 && $priceModifier < $totalValue) {
            // Add price modifier (e.g., custom commission).
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

                if (empty($categoryIds) && $product instanceof \WC_Product_Variation  &&
                    ($parentProduct = wc_get_product($product->get_parent_id())) instanceof \WC_Product
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
                        $taxRate !== null ? $netPrice : $grossPrice,
                        $taxRate !== null ? (int) $taxRate['rate'] : null,
                        $taxRate !== null ? $grossPrice - $netPrice : 0
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

        /* Paid delivery defaults to no-VAT semantics (net equals gross, tax value 0, rate null); the actual net
           cost, tax value and rate are filled in below when a shipping tax rate applies. Free delivery stays null. */
        $deliveryNetCost = $deliveryCost > 0 ? $deliveryCost : null;
        $deliveryTaxValue = $deliveryCost > 0 ? 0 : null;
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

            if ($taxRate !== null && (float) $cart->get_shipping_tax() > 0.0) {
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
     * Creates Comfino cart structure from a single product for widget display.
     *
     * Generates a minimal cart containing only the specified product, used for calculating
     * available payment options on product pages. Handles product variations and category
     * inheritance from parent products.
     *
     * @param \WC_Product $product WooCommerce product object (simple, variable, or variation)
     *
     * @return Cart Comfino cart structure with single product, prices including/excluding tax, and tax information
     */
    public static function getShopCartFromProduct(\WC_Product $product): Cart
    {
        if (!empty($taxRates = \WC_Tax::get_rates($product->get_tax_class()))) {
            $taxRate = reset($taxRates);
        } else {
            $taxRate = null;
        }

        $categoryIds = $product->get_category_ids();

        if (empty($categoryIds) && $product instanceof \WC_Product_Variation &&
            ($parentProduct = wc_get_product($product->get_parent_id())) instanceof \WC_Product
        ) {
            $categoryIds = $parentProduct->get_category_ids();
        }

        $grossPrice = (int) (wc_get_price_including_tax($product) * 100);
        $netPrice = ($taxRate !== null ? (int) (wc_get_price_excluding_tax($product) * 100) : $grossPrice);
        $taxValue = ($taxRate !== null ? $grossPrice - $netPrice : 0);

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
     * Extracts customer information from WooCommerce order for Comfino API.
     *
     * Converts order billing and shipping data into Comfino customer structure. Attempts to find
     * phone number from billing info or order metadata. Includes both billing and delivery addresses.
     *
     * @param \WC_Order $order WooCommerce order object with customer and address information
     *
     * @return Customer Comfino customer structure with name, email, phone, tax ID, and addresses
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

        $city = self::resolveAddressField(
            $order->get_billing_city(),
            $order->get_shipping_city(),
            ['billing_city', 'shipping_city']
        );
        $postcode = self::resolveAddressField(
            $order->get_billing_postcode(),
            $order->get_shipping_postcode(),
            ['billing_postcode', 'shipping_postcode']
        );

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
                $postcode,
                $city,
                $order->get_billing_country()
            )
        );
    }

    /**
     * Loads WooCommerce order with proper error distinction.
     *
     * HPOS-compatible order loading that distinguishes between "order not found"
     * and "database error during loading" scenarios. Uses wc_get_order() which
     * abstracts both legacy (CPT-based) and HPOS (custom tables) storage.
     *
     * @param int|string $orderId Order identifier - numeric ID or order reference
     *
     * @return \WC_Order|null WooCommerce order object if found, null otherwise
     *
     * @throws \RuntimeException If database error occurs during loading
     */
    public static function loadOrder($orderId): ?\WC_Order
    {
        global $wpdb;

        DebugLogger::logEvent('[ORDER]', 'loadOrder', ['$orderId' => $orderId]);

        // Clear any previous database errors to get accurate state.
        $wpdb->suppress_errors(false);
        $wpdb->last_error = '';

        // wc_get_order() works with both HPOS and legacy storage.
        $order = wc_get_order($orderId);

        if (!$order) {
            // Check if database error occurred during loading.
            if (!empty($wpdb->last_error)) {
                DebugLogger::logEvent(
                    '[ORDER]', 'loadOrder - error',
                    ['$orderId' => $orderId, '$wpdb->last_error' => $wpdb->last_error]
                );

                throw new \RuntimeException(esc_html($wpdb->last_error));
            }

            DebugLogger::logEvent('[ORDER]', 'loadOrder - not found', ['$orderId' => $orderId]);

            // No database error, order simply doesn't exist - return null.
            return null;
        }

        return $order;
    }

    /**
     * Loads WooCommerce order by custom order number (sequential or formatted).
     *
     * This method supports various sequential order number plugins by trying multiple
     * approaches in order of preference:
     *
     * 1. Modern HPOS meta_query (if supported by WooCommerce version).
     * 2. Plugin-specific APIs (for legacy compatibility without HPOS meta_query support).
     *
     * Supported plugins:
     * - SkyVerge Sequential Order Numbers (free & Pro)
     * - WebToffee Sequential Order Numbers
     * - YITH WooCommerce Sequential Order Number
     * - Custom Order Numbers for WooCommerce (Algoritmika/Booster)
     * - Tyche Softwares Custom Order Numbers
     *
     * @param string $orderNumber Custom order number (e.g., "2026-12345", "ORD-00123")
     *
     * @return \WC_Order|null WooCommerce order object if found, null otherwise
     *
     * @throws \RuntimeException If database error occurs during loading
     */
    public static function loadOrderByNumber(string $orderNumber): ?\WC_Order
    {
        global $wpdb;

        DebugLogger::logEvent('[ORDER]', 'loadOrderByNumber', ['$orderNumber' => $orderNumber]);

        try {
            // Try direct loading first (might work with order numbers in some setups).
            if (($order = self::loadOrder($orderNumber)) !== null) {
                return $order;
            }
        } catch (\RuntimeException $e) {
            // Continue to other methods if direct loading fails.
        }

        /* Modern approach: Use HPOS meta_query if supported (WooCommerce 8.2+).
           https://developer.woocommerce.com/docs/features/high-performance-order-storage/wc-order-query-improvements/#metadata-queries-meta_query */
        if (self::supportsMetaQuery()) {
            // Common meta keys used by sequential order number plugins.
            $metaKeys = [
                '_order_number',                     // YITH, SkyVerge Pro, generic
                '_alg_wc_full_custom_order_number',  // Algoritmika/Booster (full number with prefix/suffix)
                '_alg_wc_custom_order_number',       // Algoritmika/Booster (base number)
                '_wcj_order_number',                 // Booster legacy key
                'wt_order_number',                   // WebToffee alternative key
                '_custom_order_number',              // Generic/other plugins
            ];

            try {
                $orders = wc_get_orders([
                    'meta_query' => array_merge(
                        array_map(
                            static function (string $metaKey) use ($orderNumber): array {
                                return ['key' => $metaKey, 'value' => $orderNumber, 'compare' => '='];
                            },
                            $metaKeys
                        ),
                        ['relation' => 'OR']
                    ),
                    'payment_method' => PaymentGateway::GATEWAY_ID,
                    'limit' => 1,
                ]);

                if (count($orders) && ($orders[0] instanceof \WC_Order)) {
                    return $orders[0];
                }

                DebugLogger::logEvent('[ORDER]', 'loadOrderByNumber[meta_query]:wc_get_orders - not found');
            } catch (\Exception $e) {
                DebugLogger::logEvent(
                    '[ORDER]', 'loadOrderByNumber[meta_query]:wc_get_orders - error',
                    ['errorMessage' => $e->getMessage()]
                );

                throw new \RuntimeException(esc_html($e->getMessage()));
            }
        }

        /* Legacy fallback: Plugin-specific APIs for systems without HPOS meta_query support. */

        if (function_exists('wc_sequential_order_numbers')) {
            // SkyVerge Sequential Order Numbers (free & Pro)
            if ($orderId = wc_sequential_order_numbers()->find_order_by_order_number($orderNumber)) {
                try {
                    if (($order = self::loadOrder($orderId)) !== null) {
                        return $order;
                    }
                } catch (\RuntimeException $e) {
                    // Continue to other methods.
                    DebugLogger::logEvent(
                        '[ORDER]', 'loadOrderByNumber[SkyVerge]:find_order_by_order_number - error',
                        ['errorMessage' => $e->getMessage()]
                    );
                }
            }

            DebugLogger::logEvent('[ORDER]', 'loadOrderByNumber[SkyVerge]:find_order_by_order_number - not found');
        } elseif (class_exists('Wt_Advanced_Order_Number')) {
            // WebToffee Sequential Order Number for WooCommerce
            if ($orderId = (new \Wt_Advanced_Order_Number())->wt_order_id_from_order_number($orderNumber)) {
                try {
                    if (($order = self::loadOrder($orderId)) !== null) {
                        return $order;
                    }
                } catch (\RuntimeException $e) {
                    // Continue to other methods.
                    DebugLogger::logEvent(
                        '[ORDER]', 'loadOrderByNumber[WebToffee]:wt_order_id_from_order_number - error',
                        ['errorMessage' => $e->getMessage()]
                    );
                }

                DebugLogger::logEvent('[ORDER]', 'loadOrderByNumber[WebToffee]:wt_order_id_from_order_number - not found');}
        } elseif (class_exists('YITH_WooCommerce_Sequential_Order_Number') || class_exists('YITH_Sequential_Order_Number')) {
            // YITH WooCommerce Sequential Order Number
            try {
                /* YITH doesn't provide a public API method, so we use direct meta query.
                   YITH typically stores order number in '_order_number' meta key. */
                $orderId = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_order_number' AND meta_value = %s LIMIT 1",
                    $orderNumber
                ));

                if ($orderId && ($order = self::loadOrder($orderId)) !== null) {
                    return $order;
                }
            } catch (\Exception $e) {
                // Continue to other methods.
                DebugLogger::logEvent('[ORDER]', 'loadOrderByNumber[YITH]:SQL - error', ['errorMessage' => $e->getMessage()]);
            }

            DebugLogger::logEvent('[ORDER]', 'loadOrderByNumber[YITH]:SQL - not found');
        } elseif (class_exists('Alg_WC_Custom_Order_Numbers') || function_exists('alg_wc_custom_order_numbers')) {
            // Custom Order Numbers for WooCommerce (Algoritmika/Booster)
            try {
                /* Try direct meta query for Algoritmika keys.
                   Try full custom order number first (includes prefix/suffix). */
                $orderId = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_alg_wc_full_custom_order_number' AND meta_value = %s LIMIT 1",
                    $orderNumber
                ));

                if (!$orderId) {
                    // Try base custom order number (without prefix/suffix).
                    $orderId = $wpdb->get_var($wpdb->prepare(
                        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_alg_wc_custom_order_number' AND meta_value = %s LIMIT 1",
                        $orderNumber
                    ));
                }

                if ($orderId && ($order = self::loadOrder($orderId)) !== null) {
                    return $order;
                }

                DebugLogger::logEvent('[ORDER]', 'loadOrderByNumber[Algoritmika/Booster]:SQL - not found');
            } catch (\Exception $e) {
                // Continue to other methods.
                DebugLogger::logEvent(
                    '[ORDER]', 'loadOrderByNumber[Algoritmika/Booster]:SQL - error',
                    ['errorMessage' => $e->getMessage()]
                );
            }
        } elseif (class_exists('Tyche_Softwares_Order_Numbers') || function_exists('tyche_order_number')) {
            // Tyche Softwares Custom Order Numbers for WooCommerce
            try {
                // Tyche typically uses '_custom_order_number' meta key.
                $orderId = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_custom_order_number' AND meta_value = %s LIMIT 1",
                    $orderNumber
                ));

                if ($orderId && ($order = self::loadOrder($orderId)) !== null) {
                    return $order;
                }

                DebugLogger::logEvent('[ORDER]', 'loadOrderByNumber[Tyche Softwares]:SQL - not found');
            } catch (\Exception $e) {
                // Continue...
                DebugLogger::logEvent(
                    '[ORDER]', 'loadOrderByNumber[Tyche Softwares]:SQL - error',
                    ['errorMessage' => $e->getMessage()]
                );
            }
        } else {
            try {
                /* Final fallback: Generic meta key search for any other plugins.
                   This covers plugins that might use different meta keys not listed above. */
                $orderId = $wpdb->get_var($wpdb->prepare(
                    "SELECT
                         post_id
                     FROM
                         {$wpdb->postmeta}
                     WHERE
                         meta_key IN ('_order_number', '_custom_order_number', 'order_number', 'custom_order_number') AND
                         meta_value = %s LIMIT 1",
                    $orderNumber
                ));

                if ($orderId && ($order = self::loadOrder($orderId)) !== null) {
                    return $order;
                }

                DebugLogger::logEvent('[ORDER]', 'loadOrderByNumber[final fallback]:SQL - not found');
            } catch (\Exception $e) {
                // All methods exhausted.
                DebugLogger::logEvent(
                    '[ORDER]', 'loadOrderByNumber[final fallback]:SQL - error',
                    ['errorMessage' => $e->getMessage()]
                );
            }
        }

        return null;
    }

    /**
     * Checks if the current WooCommerce version supports meta_query in wc_get_orders().
     *
     * Meta query support for HPOS was added in WooCommerce 8.2.0.
     * https://developer.woocommerce.com/docs/extensions/core-concepts/wc-get-orders/
     *
     * @return bool True if meta_query is supported, false otherwise
     */
    private static function supportsMetaQuery(): bool
    {
        if (!defined('WC_VERSION') || version_compare(WC_VERSION, '8.2.0', '<')) {
            return false;
        }

        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
            return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }

        return false;
    }

    /**
     * Retrieves order notes containing specific Comfino status updates.
     *
     * Searches through WooCommerce order notes to find system-generated notes
     * recording Comfino payment status changes. Used to prevent duplicate status
     * notifications to Comfino API.
     *
     * @param int $orderId WooCommerce order ID to search notes for
     * @param array $statuses Array of Comfino status strings to search for (e.g., ['CANCELLED_BY_SHOP', 'RESIGN'])
     *
     * @return array Associative array with status as key and order note object as value
     */
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

    /**
     * Resolves an address field value with fallbacks for checkout plugins/themes that disrupt WooCommerce's standard
     * billing/shipping field persistence on the order object (e.g., FunnelKit Checkout saving fields after payment
     * gateway processing already ran).
     *
     * Tries, in order: the primary (billing) order field, the secondary (shipping) order field, then the raw checkout
     * POST data under the given field names. Falling back to $_POST is a last resort for cases where the order object
     * hasn't been fully persisted yet by a third-party checkout flow, but the customer did submit the data.
     *
     * @param string $primaryValue Value from the order's primary (billing) getter
     * @param string $secondaryValue Value from the order's secondary (shipping) getter
     * @param string[] $postFieldNames POST field names to check, in priority order
     *
     * @return string Resolved field value, or empty string if not found anywhere
     */
    private static function resolveAddressField(string $primaryValue, string $secondaryValue, array $postFieldNames): string
    {
        if (!empty(trim($primaryValue))) {
            return trim($primaryValue);
        }

        if (!empty(trim($secondaryValue))) {
            return trim($secondaryValue);
        }

        foreach ($postFieldNames as $postFieldName) {
            if (!empty($_POST[$postFieldName])) {
                $postValue = trim(sanitize_text_field(wp_unslash($_POST[$postFieldName])));

                if ($postValue !== '') {
                    DebugLogger::logEvent(
                        '[ORDER]',
                        'resolveAddressField - used POST fallback',
                        ['field' => $postFieldName]
                    );

                    return $postValue;
                }
            }
        }

        return '';
    }
}
