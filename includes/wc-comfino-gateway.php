<?php

/**
 * @method get_option(string $string)
 */
class WC_Comfino_Gateway extends WC_Payment_Gateway
{
    public const WAITING_FOR_PAYMENT_STATUS = "WAITING_FOR_PAYMENT";
    public const ACCEPTED_STATUS = "ACCEPTED";
    public const REJECTED_STATUS = "REJECTED";
    public const CANCELLED_STATUS = "CANCELLED";
    public const PAID_STATUS = "PAID";

    private const TYPE_INSTALLMENTS_ZERO_PERCENT = 'INSTALLMENTS_ZERO_PERCENT';
    private const TYPE_CONVENIENT_INSTALLMENTS = 'CONVENIENT_INSTALLMENTS';
    private const TYPE_PAY_LATER = 'PAY_LATER';
    private const TYPE_RENEWABLE_LIMIT = 'RENEWABLE_LIMIT';

    /**
     * Reject status
     *
     * @var array
     */
    private $rejected_state = [
        self::REJECTED_STATUS,
        self::CANCELLED_STATUS,
    ];

    /**
     * Positive status
     *
     * @var array
     */
    private $completed_state = [
        self::ACCEPTED_STATUS,
        self::PAID_STATUS,
        self::WAITING_FOR_PAYMENT_STATUS,
    ];

    /**
     * Product type
     *
     * @var string[]
     */
    private $types = [
        self::TYPE_INSTALLMENTS_ZERO_PERCENT,
        self::TYPE_CONVENIENT_INSTALLMENTS,
        self::TYPE_PAY_LATER,
        self::TYPE_RENEWABLE_LIMIT,
    ];

    public $id;
    public $icon;
    public $has_fields;
    public $method_title;
    public $method_description;
    public $form_fields;
    public $supports;
    public $title;
    public $enabled;

    private $key;
    private $host;
    private $loan_term;
    private $show_logo;

    private const COMFINO_OFFERS_ENDPOINT = '/v1/financial-products';
    private const COMFINO_ORDERS_ENDPOINT = '/v1/orders';
    private const COMFINO_PRODUCTION_HOST = 'https://api-ecommerce.comfino.pl';
    private const COMFINO_SANDBOX_HOST = 'https://api-ecommerce.craty.pl';

    /**
     * WC_Comfino_Gateway constructor.
     */
    public function __construct()
    {
        $this->id = 'comfino';
        $this->icon = $this->get_icon();
        $this->has_fields = true;
        $this->method_title = __('Comfino Gateway', 'comfino');
        $this->method_description = __('Comfino payment gateway', 'comfino');

        $this->supports = array(
            'products'
        );

        $this->init_form_fields();

        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');
        $this->loan_term = (int)$this->get_option('loan_term');
        $this->show_logo = 'yes' === $this->get_option('show_logo');

        $sandbox_mode = 'yes' === $this->get_option('sandbox_mode');
        $sandbox_key = $this->get_option('sandbox_key');
        $production_key = $this->get_option('production_key');

        if ($sandbox_mode) {
            $this->host = self::COMFINO_SANDBOX_HOST;
            $this->key = $sandbox_key;
        } else {
            $this->host = self::COMFINO_PRODUCTION_HOST;
            $this->key = $production_key;
        }

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('woocommerce_api_wc_comfino_gateway', array($this, 'webhook'));
    }

    /**
     * Plugin options
     */
    public function init_form_fields(): void
    {
        $this->form_fields = array(
            'enabled' => [
                'title' => __('Enable/Disable', 'comfino'),
                'type' => 'checkbox',
                'label' => __('Enable Comfino Payment Module.', 'comfino'),
                'default' => 'no',
                'description' => __('Show in the Payment List as a payment option', 'comfino')
            ],
            'sandbox_mode' => [
                'title' => __('Sandbox mode:', 'comfino'),
                'type' => 'checkbox',
                'label' => __('Enable Sandbox Mode', 'comfino'),
                'default' => 'yes',
            ],
            'title' => [
                'title' => __('Title:', 'comfino'),
                'type' => 'text',
                'default' => 'Comfino',
            ],
            'sandbox_key' => [
                'title' => __('Sandbox Key', 'comfino'),
                'type' => 'text'
            ],
            'production_key' => [
                'title' => __('Production Key', 'comfino'),
                'type' => 'text'
            ],
            'loan_term' => [
                'title' => __('Loan Term', 'comfino'),
                'type' => 'int',
                'default' => '48',
            ],
            'show_logo' => [
                'title' => __('Show Logo', 'comfino'),
                'type' => 'checkbox',
                'label' => __('Show logo on payment method', 'comfino'),
                'default' => 'yes',
            ],
        );
    }

    /**
     * Show offers
     */
    public function payment_fields(): void
    {
        global $woocommerce;

        $loanTerm = $this->get_option('loan_term');
        $offers = $this->fetch_offers((int)$loanTerm, (int)round($woocommerce->cart->total) * 100);
        $types = [];

        foreach ($offers as $offer) {
            $instalmentAmount = number_format($offer['instalmentAmount'] / 100, 2, ',', ' ');
            $toPay = number_format($offer['toPay'] / 100, 2, ',', ' ');
            $rrso = number_format($offer['rrso'] * 100, 2, ',', ' ');
            $types[] = $offer['type'];

            echo '
                <div class="confino" id="comfino_offer_' . esc_html($offer['type']) . '" data-type="' . esc_html($offer['type']) . '">
                    <div style="text-align: center">
                        <div class="icon">' . $offer['icon'] . '</div>
                        <div class="name"><strong>' . esc_html($offer['name']) . '</strong></div>
                        <div class="offer">
                            <div><strong> ' . esc_html($loanTerm) . ' ' . __('installments', 'comfino') . ' x ' . esc_html($instalmentAmount) . ' zł</strong></div>
                            <div>' . __('Total amount to be repaid', 'comfino') . ': <b>' . esc_html($toPay) . ' zł</b>, RRSO: ' . esc_html($rrso) . ' %</div>
                        </div>
                        <div class="description">' . esc_html($offer['description']) . '</div>
                        <div><a id="representative-example-link-' . esc_html($offer['type']) . '" class="representative-examlple" href="#" data-type="' . esc_html($offer['type']) . '">' . __('Representative example', 'comfino') . '</a>
                        </div>
                        <div class="comfino-alertbar" id="representative-example-modal-' . esc_html($offer['type']) . '">
                            <div><span class="comfino-close">&times;</span></div>
                            <div class="confino-modal-content"><p>' . $offer['representativeExample'] . '</p></div>
                        </div>
                    </div>
                </div>';
        }

        echo '<select id="comfino-type" name="comfino_type">';
        echo '<option value=""> -- </option>';

        foreach ($types as $type) {
            echo '<option id="comfino_type_' . esc_html($type) . '" value="' . esc_html($type) . '">' . esc_html($type) . '</option>';
        }

        echo '</select>';
    }

    /**
     * Include CSS and JS
     */
    public function payment_scripts(): void
    {
        if ('no' === $this->enabled) {
            return;
        }

        wp_enqueue_style('woocommerce-comfino', plugins_url('assets/css/comfino.css', __FILE__));
        wp_enqueue_script('woocommerce-comfino', plugins_url('assets/js/comfino.js', __FILE__), ['jquery'], null, true);
    }

    /**
     * @param $order_id
     *
     * @return array
     */
    public function process_payment($order_id): array
    {
        global $woocommerce;

        $type = $_POST['comfino_type'];

        if (!in_array($type, $this->types, true)) {
            $type = null;
        }

        $order = wc_get_order($order_id);
        $body = wp_json_encode([
            'returnUrl' => $this->get_return_url($order),
            'orderId' => (string)$order->get_id(),
            'notifyUrl' => add_query_arg('wc-api', 'WC_Comfino_Gateway', home_url('/')),
            'loanParameters' => [
                'term' => $this->loan_term,
                'type' => $type,
            ],
            'cart' => [
                'totalAmount' => (int)$order->get_total() * 100,
                'deliveryCost' => 0,
                'products' => $this->get_products($order->get_items()),
            ],
            'customer' => $this->get_customer($order),
        ]);

        $args = [
            'headers' => $this->get_header_request(),
            'body' => $body,
        ];

        $response = wp_remote_post($this->host . self::COMFINO_ORDERS_ENDPOINT, $args);

        if (!is_wp_error($response)) {
            $body = json_decode($response['body'], true);

            $order->add_order_note(__("Comfino create order", 'comfino'));
            $order->reduce_order_stock();

            $woocommerce->cart->empty_cart();

            return [
                'result' => 'success',
                'redirect' => $body['applicationUrl'],
            ];
        } else {
            $body = json_decode($response['body'], true);
            wc_add_notice('Connection error.', 'error');

            return [];
        }
    }

    /**
     * Webhook notifications
     */
    public function webhook(): void
    {
        $body = file_get_contents('php://input');

        if (!$this->valid_signature($body)) {
            echo json_encode(['status' => 'Invalid signature', 'body' => $body, 'signature' => $this->get_signature()]);
            exit();
        }

        $data = json_decode($body, true);

        $order = wc_get_order($data['externalId']);
        $status = $data['status'];

        if ($order) {
            $order->add_order_note(__("Comfino status: " . $status, 'comfino'));

            if (in_array($status, $this->completed_state, true)) {
                $order->payment_complete();
            }

            if (in_array($status, $this->rejected_state, true)) {
                $order->cancel_order();
            }
        }
    }

    /**
     * Fetch products
     *
     * @param int $loanTerm
     * @param int $loanAmount
     *
     * @return array
     */
    private function fetch_offers(int $loanTerm, int $loanAmount): array
    {
        $args = [
            'headers' => $this->get_header_request(),
        ];

        $params = [
            'loanAmount' => $loanAmount,
            'loanTerm' => $loanTerm,
        ];

        $response = wp_remote_get($this->host . self::COMFINO_OFFERS_ENDPOINT . '?' . http_build_query($params), $args);

        if (!is_wp_error($response)) {
            return json_decode($response['body'], true);
        }

        return [];
    }

    /**
     * Prepare product data
     *
     * @param $items
     *
     * @return array
     */
    private function get_products($items): array
    {
        $products = [];

        foreach ($items as $item) {
            $data = $item->get_data();

            $products[] = [
                'name' => $data['name'],
                'quantity' => (int)$data['quantity'],
                'photoUrl' => null,
                'ean' => null,
                'externalId' => (string)$data['product_id'],
                'price' => (int)$data['total'] * 100,
            ];
        }

        return $products;
    }

    /**
     * Prepare cumstomer data
     *
     * @param $order
     *
     * @return array
     */
    private function get_customer($order): array
    {
        return [
            'firstName' => $order->get_billing_first_name(),
            'lastName' => $order->get_billing_last_name(),
            'ip' => WC_Geolocation::get_ip_address(),
            'email' => $order->get_billing_email(),
            'phoneNumber' => $order->get_billing_phone(),
            'address' => [
                'street' => $order->get_billing_address_1(),
                'postalCode' => $order->get_billing_postcode(),
                'city' => $order->get_billing_city(),
                'countryCode' => $order->get_billing_country(),
            ],
        ];
    }

    /**
     * Prepare request headers
     *
     * @return array
     */
    private function get_header_request(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Api-Key' => $this->key,
            'user-agent' => sprintf('WP Comfino [%s]', WC_ComfinoPaymentGateway::VERSION),
        ];
    }

    /**
     * @param string $jsonData
     *
     * @return bool
     */
    private function valid_signature(string $jsonData): bool
    {
        return $this->get_signature() === hash('sha3-256', $this->key . $jsonData);
    }

    /**
     * @return string
     */
    private function get_signature(): string
    {
        $headers = $_SERVER;
        $signature = '';

        foreach ($headers as $key => $value) {
            if (0 === strpos($key, 'HTTP_')) {
                $headers[substr($key, 5)] = $value;
            }
        }

        if (isset($headers['CR_SIGNATURE'])) {
            $signature = $headers['CR_SIGNATURE'];
        }

        return $signature;
    }

    /**
     * Show logo
     *
     * @return string
     */
    public function get_icon(): string
    {
        if ($this->show_logo) {
            $icon = '<img src="' . plugins_url('assets/img/comfino.png', __FILE__) . '" alt="Comfino Logo" />';
        } else {
            $icon = '';
        }

        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }
}
