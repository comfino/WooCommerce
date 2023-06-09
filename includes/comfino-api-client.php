<?php

namespace Comfino;

class Api_Client
{
    public static $host;
    public static $key;

    /**
     * Fetch products
     *
     * @param int $loanAmount
     *
     * @return array
     */
    public static function fetch_offers(int $loanAmount): array
    {
        $url = self::$host . '/v1/financial-products' . '?' . http_build_query(['loanAmount' => $loanAmount]);
        $args = ['headers' => self::get_header_request()];

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
                    null,
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
            null,
            wp_remote_retrieve_body($response)
        );

        return [];
    }

    public static function process_payment(\WC_Abstract_Order $order, string $return_url, string $notify_url): array
    {
        global $woocommerce;

        $loan_term = sanitize_text_field($_POST['comfino_loan_term']);
        $type = sanitize_text_field($_POST['comfino_type']);

        if (!ctype_digit($loan_term)) {
            return ['result' => 'failure', 'redirect' => ''];
        }

        $body = wp_json_encode([
            'orderId' => (string)$order->get_id(),
            'returnUrl' => $return_url,
            'notifyUrl' => $notify_url,
            'loanParameters' => [
                'term' => (int)$loan_term,
                'type' => $type,
            ],
            'cart' => [
                'totalAmount' => (int)($order->get_total() * 100),
                'deliveryCost' => (int)($order->get_shipping_total() * 100),
                'products' => $this->get_products(),
            ],
            'customer' => $this->get_customer($order),
        ]);

        $url = \Comfino\Api_Client::$host. self::COMFINO_ORDERS_ENDPOINT;
        $args = [
            'headers' => self::get_header_request(),
            'body' => $body,
        ];

        $response = wp_remote_post($url, $args);

        if (!is_wp_error($response)) {
            $decoded = json_decode(wp_remote_retrieve_body($response), true);

            if (!is_array($decoded) || isset($decoded['errors']) || empty($decoded['applicationUrl'])) {
                \Comfino\Error_Logger::send_error(
                    'Payment error',
                    wp_remote_retrieve_response_code($response),
                    is_array($decoded) && isset($decoded['errors'])
                        ? implode(', ', $decoded['errors'])
                        : 'API call error ' . wp_remote_retrieve_response_code($response),
                    $url,
                    $body,
                    wp_remote_retrieve_body($response)
                );

                return ['result' => 'failure', 'redirect' => ''];
            }

            $order->add_order_note(__("Comfino create order", 'comfino-payment-gateway'));
            $order->reduce_order_stock();

            $woocommerce->cart->empty_cart();

            return ['result' => 'success', 'redirect' => $decoded['applicationUrl']];
        }

        $timestamp = time();

        \Comfino\Error_Logger::send_error(
            "Communication error [$timestamp]",
            implode(', ', $response->get_error_codes()),
            implode(', ', $response->get_error_messages()),
            $url,
            $body,
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
        self::$host= $api_host;
        self::$key = $api_key;

        $widget_key = '';

        if (!empty(self::$key)) {
            $response = wp_remote_get(
                self::$host . '/v1/widget-key',
                ['headers' => self::get_header_request()]
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
                        self::$host . '/v1/widget-key',
                        null,
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
                    self::$host . '/v1/widget-key',
                    null,
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

    public static function is_api_key_valid(string $api_host, string $api_key): bool
    {
        self::$host = $api_host;
        self::$key = $api_key;

        $api_key_valid = false;

        if (!empty(self::$key)) {
            $response = wp_remote_get(
                self::$host . '/v1/user/is-active',
                ['headers' => self::get_header_request()]
            );

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $api_key_valid = strpos(wp_remote_retrieve_body($response), 'errors') === false;
            }
        }

        return $api_key_valid;
    }

    public static function cancel_order(string $order_id)
    {
        if (!$this->get_status_note($order_id, [\Comfino\Core::CANCELLED_BY_SHOP_STATUS, \Comfino\Core::RESIGN_STATUS])) {
            $order = wc_get_order($order_id);

            $url = self::$host . "/v1/orders/{$order->get_id()}/cancel";
            $args = [
                'headers' => self::get_header_request(),
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
                    null,
                    wp_remote_retrieve_body($response)
                );

                wc_add_notice(
                    'Communication error: ' . $timestamp . '. Please contact with support and note this error id.',
                    'error'
                );
            }

            $order->add_order_note(__("Send to Comfino canceled order", 'comfino-payment-gateway'));
        }
    }

    /**
     * @param string $order_id
     */
    public static function resign_order(string $order_id)
    {
        $order = wc_get_order($order_id);

        $body = wp_json_encode(['amount' => (int)$order->get_total() * 100]);

        $url = self::$host . "/v1/orders/{$order->get_id()}/resign";
        $args = [
            'headers' => self::get_header_request(),
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
                $body,
                wp_remote_retrieve_body($response)
            );

            wc_add_notice(
                'Communication error: ' . $timestamp . '. Please contact with support and note this error id.',
                'error'
            );
        }

        $order->add_order_note(__("Send to Comfino resign order", 'comfino-payment-gateway'));
    }

    public static function send_logged_error(Shop_Plugin_Error $error): bool
    {
        $request = new Shop_Plugin_Error_Request();

        if (!$request->prepare_request($error, self::get_user_agent_header())) {
            Error_Logger::log_error('Error request preparation failed', $error->error_message);

            return false;
        }

        $args = [
            'headers' => self::get_header_request(),
            'body' => wp_json_encode(['error_details' => $request->error_details, 'hash' => $request->hash]),
        ];

        $response = wp_remote_post(self::$host . '/v1/log-plugin-error', $args);

        return !is_wp_error($response) && strpos(wp_remote_retrieve_body($response), '"errors":') === false &&
            wp_remote_retrieve_response_code($response) < 400;
    }

    /**
     * Prepare request headers.
     */
    private static function get_header_request(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Api-Key' => self::$key,
            'User-Agent' => self::get_user_agent_header(),
        ];
    }

    private static function get_user_agent_header(): string
    {
        global $wp_version;

        return sprintf(
            'WP Comfino [%s], WP [%s], WC [%s], PHP [%s]',
            \Comfino_Payment_Gateway::VERSION, $wp_version, WC_VERSION, PHP_VERSION
        );
    }
}
