<?php

namespace Comfino;

class Api_Client
{
    private const COMFINO_PRODUCTION_HOST = 'https://api-ecommerce.comfino.pl';
    private const COMFINO_SANDBOX_HOST = 'https://api-ecommerce.ecraty.pl';

    private const COMFINO_PRODUCT_TYPES = [
        'INSTALLMENTS_ZERO_PERCENT',
        'CONVENIENT_INSTALLMENTS',
        'PAY_LATER',
        'COMPANY_INSTALLMENTS',
        'RENEWABLE_LIMIT',
    ];

    /** @var bool */
    private $sandbox_mode;

    /** @var string */
    private $sandbox_key;

    /** @var string */
    private $production_key;

    /** @var string */
    private $api_host;

    /** @var string */
    private $api_key;

    public function __construct(bool $sandbox_mode, string $sandbox_key, string $production_key)
    {
        $this->sandbox_mode = $sandbox_mode;
        $this->sandbox_key = $sandbox_key;
        $this->production_key = $production_key;
    }

    public function get_api_key(): string
    {
        if (empty($this->api_key)) {
            return $this->sandbox_mode ? $this->sandbox_key : $this->production_key;
        }

        return $this->api_key;
    }

    public function get_offers(int $loan_amount): array
    {
        $url = $this->get_api_host().'/v1/financial-products?'.http_build_query(['loanAmount' => $loan_amount]);
        $args = ['headers' => $this->get_header_request()];

        $response = wp_remote_get($url, $args);

        if (!is_wp_error($response)) {
            $decoded = json_decode(wp_remote_retrieve_body($response), true);

            if (!is_array($decoded) || isset($decoded['errors'])) {
                ErrorLogger::send_error(
                    'Payment error',
                    wp_remote_retrieve_response_code($response),
                    is_array($decoded) && isset($decoded['errors'])
                        ? implode(', ', $decoded['errors'])
                        : 'API call error '.wp_remote_retrieve_response_code($response),
                    $url,
                    null,
                    wp_remote_retrieve_body($response)
                );

                $decoded = [];
            }

            return $decoded;
        }

        ErrorLogger::send_error(
            'Communication error',
            implode(', ', $response->get_error_codes()),
            implode(', ', $response->get_error_messages()),
            $url,
            null,
            wp_remote_retrieve_body($response)
        );

        return [];
    }

    public function create_order(\WC_Cart $cart, \WC_Order $order, string $return_url, string $loan_term, ?string $type): array
    {
        if (!in_array($type, self::COMFINO_PRODUCT_TYPES, true)) {
            $type = null;
        }

        /* Products */

        $products = [];

        foreach ($cart->get_cart() as $item) {
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
                'photoUrl' => $image_url,
                'externalId' => (string)$product->get_id(),
                'price' => (int)(wc_get_price_including_tax($product) * 100),
            ];
        }

        /* Customer */

        $phone_number = $order->get_billing_phone();

        if (empty($phone_number)) {
            // Try to find phone number in order metadata
            $order_metadata = $order->get_meta_data();

            foreach ($order_metadata as $meta_data_item) {
                /** @var \WC_Meta_Data $meta_data_item */
                $meta_data = $meta_data_item->get_data();

                if (stripos($meta_data['key'], 'tel') !== false || stripos($meta_data['key'], 'phone') !== false) {
                    $metaValue = str_replace(['-', ' ', '(', ')'], '', $meta_data['value']);

                    if (preg_match('/^(?:\+{0,1}\d{1,2})?\d{9}$|^(?:\d{2,3})?\d{7}$/', $metaValue)) {
                        $phone_number = $metaValue;
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

        $body = wp_json_encode([
            'returnUrl' => $return_url,
            'orderId' => (string)$order->get_id(),
            'notifyUrl' => add_query_arg('wc-api', 'Comfino_Gateway', home_url('/')),
            'loanParameters' => [
                'term' => (int)$loan_term,
                'type' => $type,
            ],
            'cart' => [
                'totalAmount' => (int)($order->get_total() * 100),
                'deliveryCost' => (int)($order->get_shipping_total() * 100),
                'products' => $products,
            ],
            'customer' => [
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
            ],
        ]);

        $url = $this->get_api_host().'/v1/orders';
        $args = [
            'headers' => $this->get_header_request(),
            'body' => $body,
        ];

        $response = wp_remote_post($url, $args);

        if (!is_wp_error($response)) {
            $decoded = json_decode(wp_remote_retrieve_body($response), true);

            if (!is_array($decoded) || isset($decoded['errors']) || empty($decoded['applicationUrl'])) {
                ErrorLogger::send_error(
                    'Payment error',
                    wp_remote_retrieve_response_code($response),
                    is_array($decoded) && isset($decoded['errors'])
                        ? implode(', ', $decoded['errors'])
                        : 'API call error '.wp_remote_retrieve_response_code($response),
                    $url,
                    $body,
                    wp_remote_retrieve_body($response)
                );

                return ['result' => 'failure', 'redirect' => ''];
            }

            $order->add_order_note(__("Comfino create order", 'comfino-payment-gateway'));
            $order->reduce_order_stock();

            $cart->empty_cart();

            return ['result' => 'success', 'redirect' => $decoded['applicationUrl']];
        }

        $timestamp = time();

        ErrorLogger::send_error(
            "Communication error [$timestamp]",
            implode(', ', $response->get_error_codes()),
            implode(', ', $response->get_error_messages()),
            $url,
            $body,
            wp_remote_retrieve_body($response)
        );

        wc_add_notice(
            'Communication error: '.$timestamp.'. Please contact with support and note this error id.',
            'error'
        );

        return [];
    }

    public function cancel_order(string $order_id): void
    {
        if (!$this->get_status_note($order_id, ['CANCELLED_BY_SHOP', 'RESIGN'])) {
            $order = wc_get_order($order_id);

            $url = $this->get_api_host()."/v1/orders/{$order->get_id()}/cancel";
            $args = [
                'headers' => $this->get_header_request(),
                'method' => 'PUT'
            ];

            $response = wp_remote_request($url, $args);

            if (is_wp_error($response)) {
                $timestamp = time();

                ErrorLogger::send_error(
                    "Communication error [$timestamp]",
                    implode(', ', $response->get_error_codes()),
                    implode(', ', $response->get_error_messages()),
                    $url,
                    null,
                    wp_remote_retrieve_body($response)
                );

                wc_add_notice(
                    'Communication error: '.$timestamp.'. Please contact with support and note this error id.',
                    'error'
                );
            }

            $order->add_order_note(__("Send to Comfino canceled order", 'comfino-payment-gateway'));
        }
    }

    public function resign_order(string $order_id): void
    {
        $order = wc_get_order($order_id);

        $body = wp_json_encode(['amount' => (int)$order->get_total() * 100]);

        $url = $this->get_api_host()."/v1/orders/{$order->get_id()}/resign";
        $args = [
            'headers' => $this->get_header_request(),
            'body' => $body,
            'method' => 'PUT'
        ];

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $timestamp = time();

            ErrorLogger::send_error(
                "Communication error [$timestamp]",
                implode(', ', $response->get_error_codes()),
                implode(', ', $response->get_error_messages()),
                $url,
                $body,
                wp_remote_retrieve_body($response)
            );

            wc_add_notice(
                'Communication error: '.$timestamp.'. Please contact with support and note this error id.',
                'error'
            );
        }

        $order->add_order_note(__("Send to Comfino resign order", 'comfino-payment-gateway'));
    }

    public function get_widget_key(string $api_host, string $api_key): string
    {
        $this->api_host = $api_host;
        $this->api_key = $api_key;

        $widget_key = '';

        if (!empty($this->api_key)) {
            $response = wp_remote_get(
                $this->api_host.'/v1/widget-key',
                ['headers' => $this->get_header_request()]
            );

            if (!is_wp_error($response)) {
                $widget_key = json_decode(wp_remote_retrieve_body($response), true);
            }
        }

        return $widget_key !== false ? $widget_key : '';
    }

    public function is_api_key_valid(string $api_host, string $api_key): bool
    {
        $this->api_host = $api_host;
        $this->api_key = $api_key;

        $api_key_valid = false;

        if (!empty($this->api_key)) {
            $response = wp_remote_get(
                $this->api_host.'/v1/user/is-active',
                ['headers' => $this->get_header_request()]
            );

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $api_key_valid = strpos(wp_remote_retrieve_body($response), 'errors') === false;
            }
        }

        return $api_key_valid;
    }

    public function get_logo_url(): string
    {
        return $this->get_api_host().'/v1/get-logo-url';
    }

    /**
     * @return array|bool
     */
    public function register_shop_account(string $name, string $url, string $contact_name, string $email, string $phone, array $agreements)
    {
        $data = [
            'name' => $name,
            'webSiteUrl' => $url,
            'contactName' => $contact_name,
            'contactEmail' => $email,
            'contactPhone' => $phone,
            'platformId' => 11,
            'agreements' => $agreements,
        ];

        $url = $this->get_api_host().'/v1/user';
        $body = wp_json_encode($data);
        $args = [
            'headers' => $this->get_header_request(),
            'body' => $body,
        ];

        $response = wp_remote_post($url, $args);

        if (!is_wp_error($response)) {
            $decoded = json_decode(wp_remote_retrieve_body($response), true);

            if (!is_array($decoded) || isset($decoded['errors'])) {
                ErrorLogger::send_error(
                    'Registration error',
                    wp_remote_retrieve_response_code($response),
                    is_array($decoded) && isset($decoded['errors'])
                        ? implode(', ', $decoded['errors'])
                        : 'API call error '.wp_remote_retrieve_response_code($response),
                    $url,
                    $body,
                    wp_remote_retrieve_body($response)
                );

                return false;
            }

            return $decoded;
        }

        $timestamp = time();

        ErrorLogger::send_error(
            "Communication error [$timestamp]",
            implode(', ', $response->get_error_codes()),
            implode(', ', $response->get_error_messages()),
            $url,
            null,
            wp_remote_retrieve_body($response)
        );

        wc_add_notice(
            'Communication error: '.$timestamp.'. Please contact with support and note this error id.',
            'error'
        );

        return false;
    }

    /**
     * @return array|bool
     */
    public function get_shop_account_agreements()
    {
        $agreements = false;

        $url = $this->get_api_host().'/v1/fetch-agreements';
        $args = ['headers' => $this->get_header_request()];

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            ErrorLogger::send_error(
                'Communication error',
                implode(', ', $response->get_error_codes()),
                implode(', ', $response->get_error_messages()),
                $url,
                null,
                wp_remote_retrieve_body($response)
            );
        } else {
            $agreements = json_decode(wp_remote_retrieve_body($response), true);
        }

        return $agreements;
    }

    public function is_shop_account_active(): bool
    {
        $account_active = false;

        $url = $this->get_api_host().'/v1/user/is-active';
        $args = ['headers' => $this->get_header_request()];

        $response = wp_remote_get($url, $args);

        if (!empty($this->get_api_key())) {
            if (is_wp_error($response)) {
                ErrorLogger::send_error(
                    'Communication error',
                    implode(', ', $response->get_error_codes()),
                    implode(', ', $response->get_error_messages()),
                    $url,
                    null,
                    wp_remote_retrieve_body($response)
                );
            } else {
                $account_active = json_decode(wp_remote_retrieve_body($response), true);
            }
        }

        return $account_active;
    }

    public function send_logged_error(ShopPluginError $error): bool
    {
        $request = new ShopPluginErrorRequest();

        if (!$request->prepare_request($error, $this->get_user_agent_header())) {
            ErrorLogger::log_error('Error request preparation failed', $error->error_message);

            return false;
        }

        $args = [
            'headers' => $this->get_user_agent_header(),
            'body' => wp_json_encode(['error_details' => $request->error_details, 'hash' => $request->hash]),
        ];

        $response = wp_remote_post($this->get_api_host().'/v1/log-plugin-error', $args);

        return !is_wp_error($response) &&
            strpos(wp_remote_retrieve_body($response), '"errors":') === false &&
            wp_remote_retrieve_response_code($response) < 400;
    }

    private function get_api_host(): string
    {
        if (empty($this->api_host)) {
            return $this->sandbox_mode ? self::COMFINO_SANDBOX_HOST : self::COMFINO_PRODUCTION_HOST;
        }

        return $this->api_host;
    }

    private function get_status_note(int $order_id, array $statuses): array
    {
        $elements = wc_get_order_notes(['order_id' => $order_id]);
        $notes = [];

        foreach ($elements as $element) {
            foreach ($statuses as $status) {
                if ($element->added_by === 'system' && $element->content === "Comfino status: $status") {
                    $notes[$status] = $element;
                }
            }
        }

        return $notes;
    }

    private function get_header_request(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Api-Key' => $this->get_api_key(),
            'User-Agent' => $this->get_user_agent_header(),
        ];
    }

    private function get_user_agent_header(): string
    {
        global $wp_version;

        return sprintf(
            'WP Comfino [%s], WP [%s], WC [%s], PHP [%s]',
            \Comfino_Payment_Gateway::VERSION,
            $wp_version,
            WC_VERSION,
            PHP_VERSION
        );
    }
}
