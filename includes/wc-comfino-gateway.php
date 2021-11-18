<?php

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
    private const COMPANY_INSTALLMENTS = 'COMPANY_INSTALLMENTS';
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
        self::COMPANY_INSTALLMENTS,
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
    private $show_logo;

    private const COMFINO_OFFERS_ENDPOINT = '/v1/financial-products';
    private const COMFINO_ORDERS_ENDPOINT = '/v1/orders';
    private const COMFINO_PRODUCTION_HOST = 'https://api-ecommerce.comfino.pl';
    private const COMFINO_SANDBOX_HOST = 'https://api-ecommerce.ecraty.pl';

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

        $this->supports = [
            'products'
        ];

        $this->init_form_fields();

        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');
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

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
        add_action('woocommerce_api_wc_comfino_gateway', [$this, 'webhook']);
        add_action('woocommerce_order_status_cancelled', [$this, 'cancel_order']);
    }

    /**
     * Plugin options
     */
    public function init_form_fields(): void
    {
        $this->form_fields = [
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
            'show_logo' => [
                'title' => __('Show Logo', 'comfino'),
                'type' => 'checkbox',
                'label' => __('Show logo on payment method', 'comfino'),
                'default' => 'yes',
            ],
        ];
    }

    /**
     * Show offers
     */
    public function payment_fields(): void
    {
        global $woocommerce;

        $total = (int)round($woocommerce->cart->total) * 100;
        $offers = $this->fetch_offers($total);
        $paymentInfos = [];

        foreach ($offers as $offer) {
            $instalmentAmount = ((float)$offer['instalmentAmount']) / 100;
            $rrso = ((float)$offer['rrso']) * 100;
            $toPay = ((float)$offer['toPay']) / 100;

            $paymentInfos[] = [
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
                'loanParameters' => array_map(static function ($loanParams) use ($total) {
                    return [
                        'loanTerm' => $loanParams['loanTerm'],
                        'instalmentAmount' => number_format(((float)$loanParams['instalmentAmount']) / 100, 2, ',', ' '),
                        'toPay' => number_format(((float)$loanParams['toPay']) / 100, 2, ',', ' '),
                        'sumAmount' => number_format($total / 100, 2, ',', ' '),
                    ];
                }, $offer['loanParameters']),
            ];
        }

        echo '
            <div id="comfino-box" class="comfino">
                <div class="comfino-box">
                    <div class="header">
                        <div class="comfino-title">' . __('Choose payment method', 'comfino') . '</div>
                    </div>
                    <main>
                        <section id="comfino-offer-items" class="comfino-select-payment"></section>
                        <section class="comfino-payment-box">
                            <div class="comfino-payment-title">' . __('Value of purchase', 'comfino') . ':</div>
                            <div id="comfino-total-payment" class="comfino-total-payment"></div>
                        </section>
                        <section id="comfino-installments">
                            <section class="comfino-installments-box">
                                <div class="comfino-installments-title">' . __('Choose number of instalments', 'comfino') . '</div>
                                <div id="comfino-quantity-select" class="comfino-quantity-select"></div>
                            </section>
                            <section class="comfino-monthly-box">
                                <div class="comfino-monthly-title">' . __('Monthly instalment', 'comfino') . ':</div>
                                <div id="comfino-monthly-rate" class="comfino-monthly-rate"></div>
                            </section>
                            <section class="comfino-summary-box">
                                <div class="comfino-summary-total">' . __('Total amount to pay', 'comfino') . ': <span id="comfino-summary-total"></span></div>
                                <div class="comfino-rrso">RRSO <span id="comfino-rrso"></span></div>
                                <div id="comfino-description-box" class="comfino-description-box"></div>
                            </section>
                            <footer>
                                <a id="comfino-repr-example-link" class="representative comfino-footer-link">' . __('Representative example', 'comfino') . '</a>
                                <div id="modal-repr-example" class="comfino-modal">
                                    <div class="comfino-modal-bg comfino-modal-exit"></div>
                                    <div class="comfino-modal-container">
                                        <span id="comfino-repr-example"></span>
                                        <button class="comfino-modal-close comfino-modal-exit">&times;</button>
                                    </div>
                                </div>
                            </footer>
                        </section>
                        <section id="comfino-payment-delay" class="comfino-payment-delay">
                            <div class="comfino-payment-delay__title">' . __('Buy now, pay in 30 days', 'comfino') . ' <span>' . __('How it\'s working?', 'comfino') . '</span></div>
                            <div class="comfino-payment-delay__box">
                                <div class="comfino-helper-box">
                                    <div class="comfino-payment-delay__single-instruction">
                                        <div class="single-instruction-img__background">
                                            <img src="' . plugins_url('assets/img/icons/cart.svg', __FILE__) . '" alt="" class="single-instruction-img" />
                                        </div>
                                        <div class="comfin-single-instruction__text">' . __('Put the product in the basket', 'comfino') . '</div>
                                    </div>
                                    <div class="comfino-payment-delay__single-instruction">
                                        <div class="single-instruction-img__background">
                                            <img src="' . plugins_url('assets/img/icons/twisto.svg', __FILE__) . '" alt="" class="single-instruction-img" />
                                        </div>
                                        <div class="comfin-single-instruction__text">' . __('Choose Twisto payment', 'comfino') . '</div>
                                    </div>
                                </div>
                                <div class="comfino-helper-box">
                                    <div class="comfino-payment-delay__single-instruction">
                                        <div class="single-instruction-img__background">
                                            <img src="' . plugins_url('assets/img/icons/check.svg', __FILE__) . '" alt="" class="single-instruction-img" />
                                        </div>
                                        <div class="comfin-single-instruction__text">' . __('Check the products at home', 'comfino') . '</div>
                                    </div>
                                    <div class="comfino-payment-delay__single-instruction">
                                        <div class="single-instruction-img__background">
                                            <img src="' . plugins_url('assets/img/icons/wallet.svg', __FILE__) . '" alt="" class="single-instruction-img" />
                                        </div>
                                        <div class="comfin-single-instruction__text">' . __('Pay in 30 days', 'comfino') . '</div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </main>
                </div>
            </div>
            <input id="comfino-loan-term" name="comfino_loan_term" type="hidden" />
            <input id="comfino-type" name="comfino_type" type="hidden" />            
            <script>Comfino.initPayments(' . json_encode($paymentInfos) . ')</script>
        ';
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
        wp_enqueue_script('woocommerce-comfino', plugins_url('assets/js/comfino.js', __FILE__));
    }

    /**
     * @param $order_id
     *
     * @return array
     */
    public function process_payment($order_id): array
    {
        global $woocommerce;

        $loanTerm = $_POST['comfino_loan_term'];
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
                'term' => (int)$loanTerm,
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
        }

        wc_add_notice('Connection error.', 'error');

        return [];
    }

    /**
     * @param string $order_id
     */
    public function cancel_order($order_id): void
    {
        $order = wc_get_order($order_id);

        $args = [
            'headers' => $this->get_header_request(),
            'method' => 'PUT'
        ];

        $response = wp_remote_request($this->host . self::COMFINO_ORDERS_ENDPOINT . "/{$order->get_id()}/cancel", $args);

        if (is_wp_error($response)) {
            wc_add_notice('Connection error.', 'error');
        }

        $order->add_order_note(__("Send to Comfino canceled order", 'comfino'));
    }

    /**
     * @param string $order_id
     */
    public function resign_order($order_id): void
    {
        $order = wc_get_order($order_id);

        $args = [
            'headers' => $this->get_header_request(),
            'method' => 'PUT'
        ];

        $response = wp_remote_request($this->host.self::COMFINO_ORDERS_ENDPOINT."/{$order->get_id()}/resign", $args);

        if (is_wp_error($response)) {
            wc_add_notice('Connection error.', 'error');
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
            $order->add_order_note(__('Comfino status', 'comfino') . ": $status");

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
     * @param int $loanAmount
     *
     * @return array
     */
    private function fetch_offers(int $loanAmount): array
    {
        $args = [
            'headers' => $this->get_header_request(),
        ];

        $params = [
            'loanAmount' => $loanAmount,
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

            $product = wc_get_product($data['product_id']);
            $image_id = $product->get_image_id();

            if ($image_id !== '') {
                $image_url = wp_get_attachment_image_url($image_id, 'full');
            } else {
                $image_url = null;
            }

            $products[] = [
                'name' => $data['name'],
                'quantity' => (int)$data['quantity'],
                'photoUrl' => $image_url,
                'ean' => null,
                'externalId' => (string)$data['product_id'],
                'price' => (int)$data['total'] * 100,
            ];
        }

        return $products;
    }

    /**
     * Prepare customer data
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
        global $wp_version;

        return [
            'Content-Type' => 'application/json',
            'Api-Key' => $this->key,
            'user-agent' => sprintf('WP Comfino [%s], WP [%s], WC [%s], PHP [%s]', WC_ComfinoPaymentGateway::VERSION, $wp_version, WC_VERSION, PHP_VERSION),
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
            $icon = '<img style="height: 18px; margin: 0 5px;" src="' . plugins_url('assets/img/comfino.png', __FILE__) . '" alt="Comfino Logo" />';
        } else {
            $icon = '';
        }

        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }
}
