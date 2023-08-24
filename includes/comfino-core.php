<?php

namespace Comfino;

use Comfino_Gateway;

class Core
{
    const COMFINO_PRODUCTION_HOST = 'https://api-ecommerce.comfino.pl';
    const COMFINO_SANDBOX_HOST = 'https://api-ecommerce.ecraty.pl';

    const COMFINO_FRONTEND_JS_SANDBOX = 'https://widget.craty.pl/comfino-frontend.min.js';
    const COMFINO_FRONTEND_JS_PRODUCTION = 'https://widget.comfino.pl/comfino-frontend.min.js';

    const COMFINO_WIDGET_JS_SANDBOX = 'https://widget.craty.pl/comfino.min.js';
    const COMFINO_WIDGET_JS_PRODUCTION = 'https://widget.comfino.pl/comfino.min.js';

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

    public static function get_shop_domain(): string
    {
        $url_parts = parse_url(get_permalink(wc_get_page_id('shop')));

        return $url_parts['host'];
    }

    public static function get_shop_url(): string
    {
        $url_parts = parse_url(get_permalink(wc_get_page_id('shop')));

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

    public static function get_offers(\WP_REST_Request $request): \WP_REST_Response
    {
        global $woocommerce;

        $total = (int)round($woocommerce->cart->total) * 100;
        $offers = Api_Client::fetch_offers($total);
        $payment_offers = [];

        foreach ($offers as $offer) {
            $instalmentAmount = ((float)$offer['instalmentAmount']) / 100;
            $rrso = ((float)$offer['rrso']) * 100;
            $toPay = ((float)$offer['toPay']) / 100;

            $payment_offers[] = [
                'name' => $offer['name'],
                'description' => $offer['description'],
                'icon' => str_ireplace('<?xml version="1.0" encoding="UTF-8"?>', '', $offer['icon']),
                'type' => $offer['type'],
                'sumAmount' => number_format($total / 100, 2, ',', ' '),
                'representativeExample' => $offer['representativeExample'],
                'rrso' => number_format($rrso, 2, ',', ' '),
                'loanTerm' => $offer['loanTerm'],
                'instalmentAmount' => number_format($instalmentAmount, 2, ',', ' '),
                'toPay' => number_format($toPay, 2, ',', ' '),
                'commission' => number_format(((int)$offer['toPay'] - $total) / 100, 2, ',', ' '),
                'loanParameters' => array_map(static function ($loan_params) use ($total) {
                    return [
                        'loanTerm' => $loan_params['loanTerm'],
                        'instalmentAmount' => number_format(((float)$loan_params['instalmentAmount']) / 100, 2, ',', ' '),
                        'toPay' => number_format(((float)$loan_params['toPay']) / 100, 2, ',', ' '),
                        'commission' => number_format(((int)$loan_params['toPay'] - $total) / 100, 2, ',', ' '),
                        'sumAmount' => number_format($total / 100, 2, ',', ' '),
                        'rrso' => number_format($loan_params['rrso'] * 100, 2, ',', ' '),
                    ];
                }, $offer['loanParameters']),
            ];
        }

        return new \WP_REST_Response($payment_offers, 200);
    }

    public static function get_configuration(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wp_version, $wpdb;

        self::init();

        if (empty($verification_key = $request->get_query_params()['vkey'] ?? '')) {
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

    public static function get_widget_init_code(Comfino_Gateway $comfino_gateway): string
    {
        self::init();

        $code = str_replace(
            [
                '{WIDGET_KEY}',
                '{WIDGET_PRICE_SELECTOR}',
                '{WIDGET_TARGET_SELECTOR}',
                '{WIDGET_TYPE}',
                '{OFFER_TYPE}',
                '{EMBED_METHOD}',
                '{WIDGET_PRICE_OBSERVER_LEVEL}',
                '{WIDGET_PRICE_OBSERVER_SELECTOR}',
                '{WIDGET_SCRIPT_URL}',
            ],
            [
                $comfino_gateway->get_option('widget_key'),
                html_entity_decode($comfino_gateway->get_option('widget_price_selector')),
                html_entity_decode($comfino_gateway->get_option('widget_target_selector')),
                $comfino_gateway->get_option('widget_type'),
                $comfino_gateway->get_option('widget_offer_type'),
                $comfino_gateway->get_option('widget_embed_method'),
                $comfino_gateway->get_option('widget_price_observer_level'),
                $comfino_gateway->get_option('widget_price_observer_selector'),
                Api_Client::get_widget_script_url(),
            ],
            $comfino_gateway->get_option('widget_js_code')
        );

        return '<script>' . str_replace(
                   ['&#039;', '&gt;', '&amp;', '&quot;', '&#34;'],
                   ["'", '>', '&', '"', '"'],
                   esc_html($code)
               ) . '</script>';
    }

    private static function init()
    {
        if (self::$config_manager === null) {
            self::$config_manager = new Config_Manager();
        }

        if (self::$config_manager->get_option('sandbox_mode') === 'yes') {
            Api_Client::$host = self::COMFINO_SANDBOX_HOST;
            Api_Client::$key = self::$config_manager->get_option('sandbox_key');
            Api_Client::$frontend_script_url = self::COMFINO_FRONTEND_JS_SANDBOX;
            Api_Client::$widget_script_url = self::COMFINO_WIDGET_JS_SANDBOX;
        } else {
            Api_Client::$host = self::COMFINO_PRODUCTION_HOST;
            Api_Client::$key = self::$config_manager->get_option('production_key');
            Api_Client::$frontend_script_url = self::COMFINO_FRONTEND_JS_PRODUCTION;
            Api_Client::$widget_script_url = self::COMFINO_WIDGET_JS_PRODUCTION;
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
