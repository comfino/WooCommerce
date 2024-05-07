<?php

namespace Comfino;

class Api_Client
{
    /** @var string */
    public static $api_host;

    /** @var string */
    public static $api_key;

    /** @var string */
    private static $api_paywall_host;

    /** @var string */
    public static $frontend_script_url;

    /** @var string */
    public static $paywall_frontend_script_url;

    /** @var string */
    public static $paywall_frontend_style_url;

    /** @var string */
    public static $widget_script_url;

    /** @var string */
    public static $widget_key;

    /** @var string */
    public static $api_language;

    public static function init(Config_Manager $config_manager)
    {
        self::$widget_key = $config_manager->get_option('widget_key');

        if ($config_manager->get_option('sandbox_mode') === 'yes') {
            self::$api_host = Core::COMFINO_SANDBOX_HOST;
            self::$api_key = $config_manager->get_option('sandbox_key');
            self::$frontend_script_url = Core::COMFINO_FRONTEND_JS_SANDBOX;
            self::$api_paywall_host = Core::COMFINO_PAYWALL_SANDBOX_HOST;
            self::$paywall_frontend_script_url = Core::COMFINO_PAYWALL_FRONTEND_JS_SANDBOX;
            self::$paywall_frontend_style_url = Core::COMFINO_PAYWALL_FRONTEND_CSS_SANDBOX;
            self::$widget_script_url = Core::COMFINO_WIDGET_JS_SANDBOX_HOST;

            $widget_dev_script_version = $config_manager->get_option('widget_dev_script_version', '');

            if (empty($widget_dev_script_version)) {
                self::$widget_script_url .= '/comfino.min.js';
            } else {
                self::$widget_script_url .= ('/' . trim($widget_dev_script_version, '/'));
            }
        } else {
            self::$api_host = Core::COMFINO_PRODUCTION_HOST;
            self::$api_key = $config_manager->get_option('production_key');
            self::$frontend_script_url = Core::COMFINO_FRONTEND_JS_PRODUCTION;
            self::$api_paywall_host = Core::COMFINO_PAYWALL_PRODUCTION_HOST;
            self::$paywall_frontend_script_url = Core::COMFINO_PAYWALL_FRONTEND_JS_PRODUCTION;
            self::$paywall_frontend_style_url = Core::COMFINO_PAYWALL_FRONTEND_CSS_PRODUCTION;
            self::$widget_script_url = Core::COMFINO_WIDGET_JS_PRODUCTION_HOST;

            $widget_prod_script_version = $config_manager->get_option('widget_prod_script_version', '');

            if (empty($widget_prod_script_version)) {
                self::$widget_script_url .= '/comfino.min.js';
            } else {
                self::$widget_script_url .= ('/' . trim($widget_prod_script_version, '/'));
            }
        }
    }

    public static function process_payment(\WC_Abstract_Order $order, string $return_url, string $notify_url): array
    {
        $loan_term = sanitize_text_field($_POST['comfino_loan_term']);
        $type = sanitize_text_field($_POST['comfino_type']);

        if (!ctype_digit($loan_term)) {
            return ['result' => 'failure', 'redirect' => ''];
        }

        $total = (int) ($order->get_total() * 100);
        $delivery = (int) ($order->get_shipping_total() * 100);

        $products = Core::get_products();
        $cart_total = 0;

        foreach ($products as $product) {
            $cart_total += ($product['price'] * $product['quantity']);
        }

        $cart_total_with_delivery = $cart_total + $delivery;

        if ($cart_total_with_delivery > $total) {
            // Add discount item to the list - problems with cart items value and order total value inconsistency.
            $products[] = [
                'name' => 'Rabat',
                'quantity' => 1,
                'price' => (int) ($total - $cart_total_with_delivery),
                'photoUrl' => '',
                'ean' => '',
                'externalId' => '',
                'category' => 'DISCOUNT',
            ];
        } elseif ($cart_total_with_delivery < $total) {
            // Add correction item to the list - problems with cart items value and order total value inconsistency.
            $products[] = [
                'name' => 'Korekta',
                'quantity' => 1,
                'price' => (int) ($total - $cart_total_with_delivery),
                'photoUrl' => '',
                'ean' => '',
                'externalId' => '',
                'category' => 'CORRECTION',
            ];
        }

        $config_manager = new Config_Manager();

        $allowed_product_types = null;
        $disabled_product_types = [];
        $available_product_types = array_keys($config_manager->get_offer_types('paywall'));

        // Check product category filters.
        foreach ($available_product_types as $product_type) {
            if (!$config_manager->is_financial_product_available(
                $product_type,
                array_map(static function ($item) { return $item['data']; }, WC()->cart->get_cart())
            )) {
                $disabled_product_types[] = $product_type;
            }
        }

        if (count($disabled_product_types)) {
            $allowed_product_types = array_values(array_diff($available_product_types, $disabled_product_types));
        }

        $data = [
            'orderId' => (string)$order->get_id(),
            'returnUrl' => $return_url,
            'notifyUrl' => $notify_url,
            'loanParameters' => [
                'term' => (int)$loan_term,
                'type' => $type,
            ],
            'cart' => [
                'totalAmount' => $total,
                'deliveryCost' => $delivery,
                'products' => $products,
            ],
            'customer' => self::get_customer($order),
        ];

        if ($allowed_product_types !== null) {
            $data['loanParameters']['allowedProductTypes'] = $allowed_product_types;
        }

        $body = wp_json_encode($data);

        $url = self::get_api_host() . '/v1/orders';
        $args = [
            'headers' => self::get_request_headers('POST', $body),
            'body' => $body,
        ];

        $response = wp_remote_post($url, $args);

        if (!is_wp_error($response)) {
            $decoded = json_decode(wp_remote_retrieve_body($response), true);

            if (!is_array($decoded) || isset($decoded['errors']) || empty($decoded['applicationUrl'])) {
                Error_Logger::send_error(
                    'Payment error',
                    wp_remote_retrieve_response_code($response),
                    is_array($decoded) && isset($decoded['errors'])
                        ? implode(', ', $decoded['errors'])
                        : 'API call error ' . wp_remote_retrieve_response_code($response),
                    $url,
                    self::get_api_request_for_log($args['headers'], $body),
                    wp_remote_retrieve_body($response)
                );

                return ['result' => 'failure', 'redirect' => ''];
            }

            if ($order->get_status() == 'failed') {
                $order->update_status('pending');
            }

            $order->add_order_note(__("Comfino create order", 'comfino-payment-gateway'));
            $order->reduce_order_stock();

            WC()->cart->empty_cart();

            return ['result' => 'success', 'redirect' => $decoded['applicationUrl']];
        }

        $timestamp = time();

        Error_Logger::send_error(
            "Communication error [$timestamp]",
            implode(', ', $response->get_error_codes()),
            implode(', ', $response->get_error_messages()),
            $url,
            self::get_api_request_for_log($args['headers'], $body),
            wp_remote_retrieve_body($response)
        );

        wc_add_notice(
            'Communication error: ' . $timestamp . '. Please contact with support and note this error id.',
            'error'
        );

        return [];
    }

    /**
     * Fetch widget key.
     *
     * @param string $api_host
     * @param string $api_key
     *
     * @return string
     */
    public static function get_widget_key(string $api_host, string $api_key): string
    {
        self::$api_host = $api_host;
        self::$api_key = $api_key;

        $widget_key = '';

        if (!empty(self::$api_key)) {
            $headers = self::get_request_headers();

            $response = wp_remote_get(
                self::get_api_host() . '/v1/widget-key',
                ['headers' => $headers]
            );

            if (!is_wp_error($response)) {
                $json_response = wp_remote_retrieve_body($response);

                if (strpos($json_response, 'errors') === false) {
                    $widget_key = json_decode($json_response, true);
                } else {
                    $timestamp = time();
                    $errors = json_decode($json_response, true)['errors'];

                    Error_Logger::send_error(
                        "Widget key retrieving error [$timestamp]",
                        wp_remote_retrieve_response_code($response),
                        implode(', ', $errors),
                        self::get_api_host() . '/v1/widget-key',
                        self::get_api_request_for_log($headers),
                        $json_response
                    );

                    wc_add_notice(
                        'Widget key retrieving error: ' . $timestamp . '. Please contact with support and note this error id.',
                        'error'
                    );
                }
            } else {
                $timestamp = time();

                Error_Logger::send_error(
                    "Widget key retrieving error [$timestamp]",
                    implode(', ', $response->get_error_codes()),
                    implode(', ', $response->get_error_messages()),
                    self::get_api_host() . '/v1/widget-key',
                    self::get_api_request_for_log($headers),
                    wp_remote_retrieve_body($response)
                );

                wc_add_notice(
                    'Widget key retrieving error: ' . $timestamp . '. Please contact with support and note this error id.',
                    'error'
                );
            }
        }

        return $widget_key !== false ? $widget_key : '';
    }

    /**
     * @return string[]|bool
     */
    public static function get_product_types($list_type)
    {
        static $product_types = [];

        if (!isset($product_types[$list_type])) {
            $response = wp_remote_get(
                self::get_api_host() . '/v1/product-types?listType=' . $list_type,
                ['headers' => self::get_request_headers()]
            );

            if (!is_wp_error($response)) {
                $json_response = wp_remote_retrieve_body($response);

                if (strpos($json_response, 'errors') === false) {
                    $product_types = json_decode($json_response, true);
                } else {
                    $product_types = false;
                }
            } else {
                $product_types = false;
            }
        }

        return $product_types;
    }

    /**
     * @return string[]|bool
     */
    public static function get_widget_types()
    {
        static $widget_types = null;

        if ($widget_types !== null) {
            return $widget_types;
        }

        $response = wp_remote_get(
            self::get_api_host() . '/v1/widget-types',
            ['headers' => self::get_request_headers()]
        );

        if (!is_wp_error($response)) {
            $json_response = wp_remote_retrieve_body($response);

            if (strpos($json_response, 'errors') === false) {
                $widget_types = json_decode($json_response, true);
            } else {
                $widget_types = false;
            }
        } else {
            $widget_types = false;
        }

        return $widget_types;
    }

    public static function is_api_key_valid(string $api_host, string $api_key): bool
    {
        self::$api_host = $api_host;
        self::$api_key = $api_key;

        $api_key_valid = false;

        if (!empty(self::$api_key)) {
            $response = wp_remote_get(
                self::get_api_host() . '/v1/user/is-active',
                ['headers' => self::get_request_headers()]
            );

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $api_key_valid = strpos(wp_remote_retrieve_body($response), 'errors') === false;
            } elseif (is_wp_error($response)) {
                Error_Logger::log_error(
                    'Comfino API communication error',
                    implode(' ', $response->get_error_messages()) . ' [' . implode(', ', $response->get_error_codes()) . ']'
                );
            }
        }

        return $api_key_valid;
    }

    public static function get_logo_url(): string
    {
        return self::get_api_host(true) . '/v1/get-logo-url?auth=' . self::get_logo_auth_hash();
    }

    public static function get_paywall_logo_url(): string
    {
        return self::get_api_host(true) . '/v1/get-paywall-logo?auth=' . self::get_logo_auth_hash(true);
    }

    public static function cancel_order(\WC_Abstract_Order $order)
    {
        $url = self::get_api_host() . "/v1/orders/{$order->get_id()}/cancel";
        $args = [
            'headers' => self::get_request_headers('PUT'),
            'method' => 'PUT'
        ];

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $timestamp = time();

            Error_Logger::send_error(
                "Communication error [$timestamp]",
                implode(', ', $response->get_error_codes()),
                implode(', ', $response->get_error_messages()),
                $url,
                self::get_api_request_for_log($args['headers']),
                wp_remote_retrieve_body($response)
            );

            wc_add_notice(
                'Communication error: ' . $timestamp . '. Please contact with support and note this error id.',
                'error'
            );
        }
    }

    public static function notify_plugin_removal()
    {
        $url = self::get_api_host() . '/v1/log-plugin-remove';

        $args = [
            'headers' => self::get_request_headers('PUT'),
            'method' => 'PUT'
        ];

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $timestamp = time();

            Error_Logger::send_error(
                "Communication error [$timestamp]",
                implode(', ', $response->get_error_codes()),
                implode(', ', $response->get_error_messages()),
                $url,
                self::get_api_request_for_log($args['headers']),
                wp_remote_retrieve_body($response)
            );
        }
    }

    public static function abandoned_cart(string $type)
    {
        $body = wp_json_encode([
            'type' => $type
        ]);

        $url = self::get_api_host() . "/v1/abandoned_cart";

        $args = [
            'headers' => self::get_request_headers('POST', $body),
            'body' => $body,
            'method' => 'POST'
        ];

        wp_remote_request($url, $args);
    }

    /**
     * @return string
     */
    public static function get_api_key(): string
    {
        return self::$api_key;
    }

    public static function get_frontend_script_url(): string
    {
        if (getenv('COMFINO_DEV') && getenv('COMFINO_DEV_FRONTEND_SCRIPT_URL') &&
            getenv('COMFINO_DEV') === 'WC_' . WC_VERSION . '_' . Core::get_shop_url()
        ) {
            return getenv('COMFINO_DEV_FRONTEND_SCRIPT_URL');
        }

        return self::$frontend_script_url;
    }

    public static function get_widget_script_url(): string
    {
        if (getenv('COMFINO_DEV') && getenv('COMFINO_DEV_WIDGET_SCRIPT_URL') &&
            getenv('COMFINO_DEV') === 'WC_' . WC_VERSION . '_' . Core::get_shop_url()
        ) {
            return getenv('COMFINO_DEV_WIDGET_SCRIPT_URL');
        }

        return self::$widget_script_url;
    }

    public static function send_logged_error(Shop_Plugin_Error $error): bool
    {
        $request = new Shop_Plugin_Error_Request();

        if (!$request->prepare_request($error, self::get_user_agent_header())) {
            Error_Logger::log_error('Error request preparation failed', $error->error_message);

            return false;
        }

        $body = wp_json_encode(['error_details' => $request->error_details, 'hash' => $request->hash]);

        $args = [
            'headers' => self::get_request_headers('POST', $body),
            'body' => $body,
        ];

        $response = wp_remote_post(self::get_api_host() . '/v1/log-plugin-error', $args);

        return !is_wp_error($response) && strpos(wp_remote_retrieve_body($response), '"errors":') === false &&
            wp_remote_retrieve_response_code($response) < 400;
    }

    /**
     * @return string
     */
    public static function get_paywall_frontend_script_url()
    {
        if (getenv('COMFINO_DEV') && getenv('COMFINO_DEV_PAYWALL_FRONTEND_SCRIPT_URL')
            && getenv('COMFINO_DEV') === 'WC_' . WC_VERSION . '_' . Core::get_shop_url()
        ) {
            return getenv('COMFINO_DEV_PAYWALL_FRONTEND_SCRIPT_URL');
        }

        return self::$paywall_frontend_script_url;
    }

    /**
     * @return string
     */
    public static function get_paywall_frontend_style_url()
    {
        if (getenv('COMFINO_DEV') && getenv('COMFINO_DEV_PAYWALL_FRONTEND_STYLE_URL')
            && getenv('COMFINO_DEV') === 'WC_' . WC_VERSION . '_' . Core::get_shop_url()
        ) {
            return getenv('COMFINO_DEV_PAYWALL_FRONTEND_STYLE_URL');
        }

        return self::$paywall_frontend_style_url;
    }

    public static function get_api_host($frontend_host = false, $api_host = null)
    {
        if (getenv('COMFINO_DEV') && getenv('COMFINO_DEV') === 'WC_' . WC_VERSION . '_' . Core::get_shop_url()) {
            if ($frontend_host) {
                if (getenv('COMFINO_DEV_API_HOST_FRONTEND')) {
                    return getenv('COMFINO_DEV_API_HOST_FRONTEND');
                }
            } else {
                if (getenv('COMFINO_DEV_API_HOST_BACKEND')) {
                    return getenv('COMFINO_DEV_API_HOST_BACKEND');
                }
            }
        }

        return $api_host ?? self::$api_host;
    }

    /**
     * @return string
     */
    public static function get_paywall_api_host()
    {
        if (getenv('COMFINO_DEV') && getenv('COMFINO_DEV_API_PAYWALL_HOST')
            && getenv('COMFINO_DEV') === 'WC_' . WC_VERSION . '_' . Core::get_shop_url()
        ) {
            return getenv('COMFINO_DEV_API_PAYWALL_HOST');
        }

        return self::$api_paywall_host;
    }

    /**
     * Prepare customer data.
     */
    private static function get_customer(\WC_Abstract_Order $order): array
    {
        $phone_number = $order->get_billing_phone();

        if (empty($phone_number)) {
            // Try to find phone number in order metadata
            $order_metadata = $order->get_meta_data();

            foreach ($order_metadata as $meta_data_item) {
                /** @var \WC_Meta_Data $meta_data_item */
                $meta_data = $meta_data_item->get_data();

                if (stripos($meta_data['key'], 'tel') !== false || stripos($meta_data['key'], 'phone') !== false) {
                    $meta_value = str_replace(['-', ' ', '(', ')'], '', $meta_data['value']);

                    if (preg_match('/^(?:\+{0,1}\d{1,2})?\d{9}$|^(?:\d{2,3})?\d{7}$/', $meta_value)) {
                        $phone_number = $meta_value;

                        break;
                    }
                }
            }
        }

        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();

        if ($last_name === '') {
            $name = explode(' ', $first_name);

            if (count($name) > 1) {
                $first_name = $name[0];
                $last_name = $name[1];
            }
        }

        return [
            'firstName' => $first_name,
            'lastName' => $last_name,
            'ip' => \WC_Geolocation::get_ip_address(),
            'email' => $order->get_billing_email(),
            'phoneNumber' => $phone_number,
            'address' => [
                'street' => $order->get_billing_address_1(),
                'postalCode' => $order->get_billing_postcode(),
                'city' => $order->get_billing_city(),
                'countryCode' => $order->get_billing_country(),
            ],
        ];
    }

    /**
     * @param array $headers
     * @param string|null $body
     * @return string
     */
    private static function get_api_request_for_log(array $headers, $body = null): string
    {
        return "Headers: " . self::get_headers_for_log($headers) . "\nBody: " . ($body ?? 'n/a');
    }

    private static function get_headers_for_log(array $headers): string
    {
        $headers_str = [];

        foreach ($headers as $header_name => $header_value) {
            $headers_str[] = "$header_name: $header_value";
        }

        return implode(', ', $headers_str);
    }

    /**
     * Prepare request headers.
     */
    private static function get_request_headers(string $method = 'GET', $data = null): array
    {
        $headers = [];

        if (($method === 'POST' || $method === 'PUT') && $data !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        return array_merge($headers, [
            'Api-Key' => self::$api_key,
            'Api-Language' => !empty(self::$api_language) ? self::$api_language : substr(get_locale(), 0, 2),
            'User-Agent' => self::get_user_agent_header(),
        ]);
    }

    private static function get_user_agent_header(): string
    {
        global $wp_version;

        return sprintf(
            'WC Comfino [%s], WP [%s], WC [%s], PHP [%s], %s',
            \Comfino_Payment_Gateway::VERSION, $wp_version, WC_VERSION, PHP_VERSION, Core::get_shop_domain()
        );
    }

    /**
     * @param bool $paywall_logo
     * @return string
     */
    private static function get_logo_auth_hash(bool $paywall_logo = false): string
    {
        $platformVersion = array_map('intval', explode('.', WC_VERSION));
        $pluginVersion = array_map('intval', explode('.', \Comfino_Payment_Gateway::VERSION));
        $packedPlatformVersion = pack('c*', ...$platformVersion);
        $packedPluginVersion = pack('c*', ...$pluginVersion);
        $platformVersionLength = pack('c', strlen($packedPlatformVersion));
        $pluginVersionLength = pack('c', strlen($packedPluginVersion));

        $authHash = "WC$platformVersionLength$pluginVersionLength$packedPlatformVersion$packedPluginVersion";

        if ($paywall_logo) {
            $authHash .= self::$widget_key;
            $authHash .= hash_hmac('sha3-256', $authHash, self::get_api_key(), true);
        }

        return urlencode(base64_encode($authHash));
    }
}
