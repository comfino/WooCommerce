<?php

namespace Comfino;

class Core
{
    const COMFINO_PRODUCTION_HOST = 'https://api-ecommerce.comfino.pl';
    const COMFINO_SANDBOX_HOST = 'https://api-ecommerce.ecraty.pl';

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

    public static function get_notify_url(): string
    {
        return get_rest_url(null, 'comfino/notification');
    }

    public static function get_configuration_url(): string
    {
        return get_rest_url(null, 'comfino/configuration');
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

    public static function get_configuration(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wp_version, $wpdb;

        self::init();

        if (empty($verification_key = $request->get_query_params()['vkey'] ?? '')) {
            return new \WP_REST_Response('Access not allowed.', 403);
        }

        if (!self::valid_signature(self::get_header_by_name('CR_SIGNATURE'), $verification_key)) {
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

        $signature = self::get_header_by_name('CR_SIGNATURE');
        $json_request_body = $request->get_body();

        if (!self::valid_signature($signature, $json_request_body)) {
            return new \WP_REST_Response(['status' => 'Failed comparison of CR-Signature and shop hash.'], 400);
        }

        $configuration_options = $request->get_json_params();

        if (is_array($configuration_options)) {
            if (self::$config_manager->update_configuration(
                self::$config_manager->prepare_configuration_options($configuration_options),
                true
            )) {
                return new \WP_REST_Response(null, 204);
            }

            return new \WP_REST_Response('Wrong input data.', 400);
        }

        return new \WP_REST_Response('Wrong input data.', 400);
    }

    public static function get_signature(): string
    {
        return self::get_header_by_name('CR_SIGNATURE');
    }

    private static function init()
    {
        if (self::$config_manager === null) {
            self::$config_manager = new Config_Manager();
        }

        if (self::$config_manager->get_option('sandbox_mode') === 'yes') {
            Api_Client::$host = Core::COMFINO_SANDBOX_HOST;
            Api_Client::$key = self::$config_manager->get_option('sandbox_key');
        } else {
            Api_Client::$host = Core::COMFINO_PRODUCTION_HOST;
            Api_Client::$key = self::$config_manager->get_option('production_key');
        }
    }

    private static function valid_signature(string $signature, string $request_data): bool
    {
        return hash_equals(hash('sha3-256', Api_Client::$key . $request_data), $signature);
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
