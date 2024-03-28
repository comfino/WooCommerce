<?php

namespace Comfino;

use Comfino_Gateway;

class Core
{
    const COMFINO_PRODUCTION_HOST = 'https://api-ecommerce.comfino.pl';
    const COMFINO_SANDBOX_HOST = 'https://api-ecommerce.ecraty.pl';

    const COMFINO_FRONTEND_JS_SANDBOX = 'https://widget.craty.pl/comfino-frontend.min.js';
    const COMFINO_FRONTEND_JS_PRODUCTION = 'https://widget.comfino.pl/comfino-frontend.min.js';

    const COMFINO_PAYWALL_PRODUCTION_HOST = 'https://api-ecommerce.comfino.pl';
    const COMFINO_PAYWALL_SANDBOX_HOST = 'https://api-ecommerce.ecraty.pl';

    const COMFINO_PAYWALL_FRONTEND_JS_SANDBOX = 'https://widget.craty.pl/paywall-frontend.min.js';
    const COMFINO_PAYWALL_FRONTEND_JS_PRODUCTION = 'https://widget.comfino.pl/paywall-frontend.min.js';

    const COMFINO_PAYWALL_FRONTEND_CSS_SANDBOX = 'https://widget.craty.pl/css/paywall-frontend.css';
    const COMFINO_PAYWALL_FRONTEND_CSS_PRODUCTION = 'https://widget.comfino.pl/css/paywall-frontend.css';

    const COMFINO_WIDGET_JS_SANDBOX_HOST = 'https://widget.craty.pl';
    const COMFINO_WIDGET_JS_PRODUCTION_HOST = 'https://widget.comfino.pl';

    const WAITING_FOR_PAYMENT_STATUS = "WAITING_FOR_PAYMENT";
    const ACCEPTED_STATUS = "ACCEPTED";
    const REJECTED_STATUS = "REJECTED";
    const CANCELLED_STATUS = "CANCELLED";
    const CANCELLED_BY_SHOP_STATUS = "CANCELLED_BY_SHOP";
    const PAID_STATUS = "PAID";
    const RESIGN_STATUS = "RESIGN";

    const ERROR_LOG_NUM_LINES = 40;

    private static $logged_states = [
        self::ACCEPTED_STATUS,
        self::CANCELLED_STATUS,
        self::CANCELLED_BY_SHOP_STATUS,
        self::REJECTED_STATUS,
        self::RESIGN_STATUS,
    ];

    /**
     * Reject status.
     *
     * @var array
     */
    private static $rejected_states = [
        self::REJECTED_STATUS,
        self::CANCELLED_STATUS,
        self::CANCELLED_BY_SHOP_STATUS,
        self::RESIGN_STATUS,
    ];

    /**
     * Positive status.
     *
     * @var array
     */
    private static $completed_states = [
        self::ACCEPTED_STATUS,
        self::PAID_STATUS,
        self::WAITING_FOR_PAYMENT_STATUS,
    ];

    /**
     * @var Config_Manager
     */
    private static $config_manager;

    public static function init()
    {
        if (self::$config_manager === null) {
            self::$config_manager = new Config_Manager();
        }

        Api_Client::init(self::$config_manager);
    }

    public static function get_shop_domain(): string
    {
        return parse_url(wc_get_page_permalink('shop'), PHP_URL_HOST);
    }

    public static function get_shop_url(): string
    {
        $url_parts = parse_url(wc_get_page_permalink('shop'));

        return $url_parts['host'] . (isset($url_parts['port']) ? ':' . $url_parts['port'] : '');
    }

    public static function get_offers_url(): string
    {
        return get_rest_url(null, 'comfino/offers');
    }

    public static function get_notify_url(): string
    {
        return get_rest_url(null, 'comfino/notification');
    }

    public static function get_available_offer_types_url(): string
    {
        return get_rest_url(null, 'comfino/availableoffertypes');
    }

    public static function get_configuration_url(): string
    {
        return get_rest_url(null, 'comfino/configuration');
    }

    /**
     * Prepare product data.
     *
     * @return array
     */
    public static function get_products(): array
    {
        $products = [];

        foreach (WC()->cart->get_cart() as $item) {
            /** @var \WC_Product_Simple $product */
            $product = $item['data'];
            $image_id = $product->get_image_id();

            if ($image_id !== '') {
                $image_url = wp_get_attachment_image_url($image_id, 'full');
            } else {
                $image_url = null;
            }

            $products[] = [
                'name' => $product->get_name(),
                'quantity' => (int)$item['quantity'],
                'price' => (int)(wc_get_price_including_tax($product) * 100),
                'photoUrl' => $image_url,
                'externalId' => (string)$product->get_id(),
                'category' => implode(',', $product->get_category_ids())
            ];
        }

        return $products;
    }

    public static function process_notification(\WP_REST_Request $request): \WP_REST_Response
    {
        self::init();

        if (!self::valid_signature(self::get_signature(), $request->get_body())) {
            return new \WP_REST_Response('Failed comparison of CR-Signature and shop hash.', 400);
        }

        $data = json_decode($request->get_body(), true);

        if ($data === null) {
            return new \WP_REST_Response('Wrong input data.', 400);
        }

        if (!isset($data['externalId'])) {
            return new \WP_REST_Response('External ID must be set.', 400);
        }

        if (!isset($data['status'])) {
            return new \WP_REST_Response('External ID must be set.', 400);
        }

        $order = wc_get_order($data['externalId']);
        $status = $data['status'];

        if ($order) {
            if (in_array($status, self::$logged_states, true)) {
                $order->add_order_note(__('Comfino status', 'comfino-payment-gateway') . ": " . __($status, 'comfino-payment-gateway'));
            }

            if (in_array($status, self::$completed_states, true)) {
                $order->payment_complete();
            } elseif (in_array($status, self::$rejected_states, true)) {
                $order->cancel_order();
            }
        } else {
            return new \WP_REST_Response('Order not found.', 404);
        }

        return new \WP_REST_Response('OK', 200);
    }

    public static function get_available_offer_types(\WP_REST_Request $request): \WP_REST_Response
    {
        self::init();

        $available_product_types = array_keys(self::$config_manager->get_offer_types('paywall'));

        if (empty($product_id = $request->get_param('product_id') ?? '')) {
            return new \WP_REST_Response($available_product_types, 200);
        }

        $product = wc_get_product($product_id);

        if (!$product) {
            return new \WP_REST_Response($available_product_types, 200);
        }

        $filtered_product_types = [];

        foreach ($available_product_types as $product_type) {
            if (self::$config_manager->is_financial_product_available($product_type, [$product])) {
                $filtered_product_types[] = $product_type;
            }
        }

        return new \WP_REST_Response($filtered_product_types, 200);
    }

    public static function get_configuration(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wp_version, $wpdb;

        self::init();

        if (empty($verification_key = $request->get_param('vkey') ?? '')) {
            return new \WP_REST_Response('Access not allowed.', 403);
        }

        if (!self::valid_signature(self::get_signature(), $verification_key)) {
            return new \WP_REST_Response(['status' => 'Failed comparison of CR-Signature and shop hash.'], 400);
        }

        $response = [
            'shop_info' => [
                'plugin_version' => \Comfino_Payment_Gateway::VERSION,
                'shop_version' => WC_VERSION,
                'wordpress_version' => $wp_version,
                'php_version' => PHP_VERSION,
                'server_software' => sanitize_text_field($_SERVER['SERVER_SOFTWARE']),
                'server_name' => sanitize_text_field($_SERVER['SERVER_NAME']),
                'server_addr' => sanitize_text_field($_SERVER['SERVER_ADDR']),
                'database_version' => $wpdb->db_version(),
            ],
            'shop_configuration' => self::$config_manager->return_configuration_options(),
        ];

        return new \WP_REST_Response($response, 200);
    }

    public static function update_configuration(\WP_REST_Request $request): \WP_REST_Response
    {
        self::init();

        if (!self::valid_signature(self::get_signature(), $request->get_body())) {
            return new \WP_REST_Response(['status' => 'Failed comparison of CR-Signature and shop hash.'], 400);
        }

        $configuration_options = $request->get_json_params();

        if (is_array($configuration_options)) {
            $current_options = self::$config_manager->return_configuration_options(true);
            $input_options = self::$config_manager->filter_configuration_options($configuration_options);

            if (self::$config_manager->update_configuration(
                '',
                self::$config_manager->prepare_configuration_options(array_merge($current_options, $input_options)),
                true
            )) {
                return new \WP_REST_Response(null, 204);
            }

            if (count(self::$config_manager->get_errors())) {
                return new \WP_REST_Response('Wrong input data.', 400);
            }

            return new \WP_REST_Response(null, 204);
        }

        return new \WP_REST_Response('Wrong input data.', 400);
    }

    public static function get_signature(): string
    {
        $signature = self::get_header_by_name('CR_SIGNATURE');

        if ($signature !== '') {
            return $signature;
        }

        return self::get_header_by_name('X_CR_SIGNATURE');
    }

    public static function get_widget_init_code(Comfino_Gateway $comfino_gateway, $product_id): string
    {
        self::init();

        $widget_variables = self::$config_manager->get_widget_variables($product_id);

        $code = str_replace(
            array_merge(
                [
                    '{WIDGET_KEY}',
                    '{WIDGET_PRICE_SELECTOR}',
                    '{WIDGET_TARGET_SELECTOR}',
                    '{WIDGET_PRICE_OBSERVER_SELECTOR}',
                    '{WIDGET_PRICE_OBSERVER_LEVEL}',
                    '{WIDGET_TYPE}',
                    '{OFFER_TYPE}',
                    '{EMBED_METHOD}',
                ],
                array_keys($widget_variables)
            ),
            array_merge(
                [
                    $comfino_gateway->get_option('widget_key'),
                    html_entity_decode($comfino_gateway->get_option('widget_price_selector')),
                    html_entity_decode($comfino_gateway->get_option('widget_target_selector')),
                    $comfino_gateway->get_option('widget_price_observer_selector'),
                    $comfino_gateway->get_option('widget_price_observer_level'),
                    $comfino_gateway->get_option('widget_type'),
                    $comfino_gateway->get_option('widget_offer_type'),
                    $comfino_gateway->get_option('widget_embed_method'),
                ],
                array_values($widget_variables)
            ),
            self::$config_manager->get_current_widget_code($product_id)
        );

        return '<script>' . str_replace(
            ['&#039;', '&gt;', '&amp;', '&quot;', '&#34;'],
            ["'", '>', '&', '"', '"'],
            esc_html($code)
        ) . '</script>';
    }

    private static function valid_signature(string $signature, string $request_data): bool
    {
        return hash_equals(hash('sha3-256', Api_Client::$api_key . $request_data), $signature);
    }

    private static function get_header_by_name(string $name): string
    {
        $header = '';

        foreach ($_SERVER as $key => $value) {
            if ($key === 'HTTP_' . strtoupper($name)) {
                $header = sanitize_text_field($value);

                break;
            }
        }

        return $header;
    }
}
