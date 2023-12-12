<?php

namespace Comfino;

class Api_Client
{
    /** @var string */
    public static $host;

    /** @var string */
    public static $key;

    /** @var string */
    public static $frontend_script_url;

    /** @var string */
    public static $widget_script_url;

    /** @var string */
    public static $api_language;

    public static function init(Config_Manager $config_manager)
    {
        if ($config_manager->get_option('sandbox_mode') === 'yes') {
            self::$host = Core::COMFINO_SANDBOX_HOST;
            self::$key = $config_manager->get_option('sandbox_key');
            self::$frontend_script_url = Core::COMFINO_FRONTEND_JS_SANDBOX;
            self::$widget_script_url = Core::COMFINO_WIDGET_JS_SANDBOX;
        } else {
            self::$host = Core::COMFINO_PRODUCTION_HOST;
            self::$key = $config_manager->get_option('production_key');
            self::$frontend_script_url = Core::COMFINO_FRONTEND_JS_PRODUCTION;
            self::$widget_script_url = Core::COMFINO_WIDGET_JS_PRODUCTION;
        }
    }

    /**
     * Fetch products.
     *
     * @param int $loanAmount
     * @return array
     */
    public static function fetch_offers(int $loanAmount): array
    {
        $url = self::get_api_host() . '/v1/financial-products' . '?' . http_build_query(['loanAmount' => $loanAmount]);
        $args = ['headers' => self::get_request_headers()];

        $response = wp_remote_get($url, $args);

        if (!is_wp_error($response)) {
            $decoded = json_decode(wp_remote_retrieve_body($response), true);

            if (!is_array($decoded) || isset($decoded['errors'])) {
                Error_Logger::send_error(
                    'Payment error',
                    wp_remote_retrieve_response_code($response),
                    is_array($decoded) && isset($decoded['errors'])
                        ? implode(', ', $decoded['errors'])
                        : 'API call error ' . wp_remote_retrieve_response_code($response),
                    $url,
                    self::get_api_request_for_log($args['headers']),
                    wp_remote_retrieve_body($response)
                );

                $decoded = [];
            }

            return $decoded;
        }

        Error_Logger::send_error(
            'Communication error',
            implode(', ', $response->get_error_codes()),
            implode(', ', $response->get_error_messages()),
            $url,
            self::get_api_request_for_log($args['headers']),
            wp_remote_retrieve_body($response)
        );

        return [];
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
        $available_product_types = array_keys($config_manager->get_offer_types());

        // Check product category filters.
        foreach ($available_product_types as $product_type) {
            if (!$config_manager->is_financial_product_available($product_type, WC()->cart->get_cart())) {
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
        self::$host = $api_host;
        self::$key = $api_key;

        $widget_key = '';

        if (!empty(self::$key)) {
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
    public static function get_product_types()
    {
        static $product_types = null;

        if ($product_types !== null) {
            return $product_types;
        }

        $response = wp_remote_get(
            self::get_api_host() . '/v1/product-types',
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

        return $product_types;
    }

    public static function is_api_key_valid(string $api_host, string $api_key): bool
    {
        self::$host = $api_host;
        self::$key = $api_key;

        $api_key_valid = false;

        if (!empty(self::$key)) {
            $response = wp_remote_get(
                self::get_api_host() . '/v1/user/is-active',
                ['headers' => self::get_request_headers()]
            );

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $api_key_valid = strpos(wp_remote_retrieve_body($response), 'errors') === false;
            }
        }

        return $api_key_valid;
    }

    public static function get_logo_url(): string
    {
        return self::get_api_host(true) . '/v1/get-logo-url';
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

    /**
     * @param string $order_id
     */
    public static function resign_order(string $order_id)
    {
        $order = wc_get_order($order_id);

        $body = wp_json_encode(['amount' => (int)$order->get_total() * 100]);

        $url = self::get_api_host() . "/v1/orders/{$order->get_id()}/resign";
        $args = [
            'headers' => self::get_request_headers('PUT', $body),
            'body' => $body,
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
                self::get_api_request_for_log($args['headers'], $body),
                wp_remote_retrieve_body($response)
            );

            wc_add_notice(
                'Communication error: ' . $timestamp . '. Please contact with support and note this error id.',
                'error'
            );
        }

        $order->add_order_note(__("Send to Comfino resign order", 'comfino-payment-gateway'));
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

        return $api_host ?? self::$host;
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
            'Api-Key' => self::$key,
            'Api-Language' => !empty(self::$api_language) ? self::$api_language : substr(get_locale(), 0, 2),
            'User-Agent' => self::get_user_agent_header(),
        ]);
    }

    private static function get_user_agent_header(): string
    {
        global $wp_version;

        return sprintf(
            'WP Comfino [%s], WP [%s], WC [%s], PHP [%s], %s',
            \Comfino_Payment_Gateway::VERSION, $wp_version, WC_VERSION, PHP_VERSION, Core::get_shop_domain()
        );
    }
}
