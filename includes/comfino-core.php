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

    private static $key;

    public static function set_key(string $key)
    {
        \Comfino\Api_Client::$key = $key;
    }

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
        if (!self::valid_signature(self::get_signature(), $request->get_body())) {
            return \WP_REST_Response('Failed comparison of CR-Signature and shop hash.', 400);
        }

        $data = json_decode( $request->get_body(), true);

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
            }

            if (in_array($status, self::$rejected_states, true)) {
                $order->cancel_order();
            }
        }

        return new \WP_REST_Response('OK', 200);
    }

    public static function get_configuration(\WP_REST_Request $request): \WP_REST_Response
    {
        $config_manager = new Config_Manager();

        $signature = self::get_header_by_name('CR_SIGNATURE');
        $json_request_body = $request->get_body();

        if (!self::valid_signature($signature, $json_request_body)) {
            return new \WP_REST_Response(['status' => 'Failed comparison of CR-Signature and shop hash.'], 400);
        }

        $configuration_options = $request->get_json_params();

        if (is_array($configuration_options)) {
            $config_manager->update_configuration($configuration_options);

            return new \WP_REST_Response(null, 204);
        }

        return new \WP_REST_Response('Wrong input data.', 400);
    }

    public static function update_configuration(\WP_REST_Request $request): \WP_REST_Response
    {
        return \WP_REST_Response(['status' => 'OK']);
    }

    public static function get_signature(): string
    {
        return self::get_header_by_name('CR_SIGNATURE');
    }

    private static function valid_signature(string $signature, string $json_data): bool
    {
        return hash_equals(hash('sha3-256', \Comfino\Api_Client::$key . $json_data), $signature);
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
