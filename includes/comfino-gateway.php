<?php

class Comfino_Gateway extends WC_Payment_Gateway
{
    public const WAITING_FOR_PAYMENT_STATUS = "WAITING_FOR_PAYMENT";
    public const ACCEPTED_STATUS = "ACCEPTED";
    public const REJECTED_STATUS = "REJECTED";
    public const CANCELLED_STATUS = "CANCELLED";
    public const CANCELLED_BY_SHOP_STATUS = "CANCELLED_BY_SHOP";
    public const PAID_STATUS = "PAID";
    public const RESIGN_STATUS = "RESIGN";

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
        self::CANCELLED_BY_SHOP_STATUS,
        self::RESIGN_STATUS,
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

    public const COMFINO_WIDGET_JS_SANDBOX = 'https://widget.craty.pl/comfino.min.js';
    public const COMFINO_WIDGET_JS_PRODUCTION = 'https://widget.comfino.pl/comfino.min.js';

    /**
     * Comfino_Gateway constructor.
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

        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_wc_comfino_gateway', [$this, 'webhook']);

        add_action('woocommerce_order_status_cancelled', [$this, 'cancel_order']);

        add_action('woocommerce_order_item_add_action_buttons', [$this, 'order_buttons'], 10, 1);
        add_action('save_post', [$this, 'update_order'], 10, 3);
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
            'title' => [
                'title' => __('Title:', 'comfino'),
                'type' => 'text',
                'default' => 'Comfino',
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
            'sandbox_mode' => [
                'title' => __('Sandbox mode:', 'comfino'),
                'type' => 'checkbox',
                'label' => __('Enable Sandbox Mode', 'comfino'),
                'default' => 'no',
            ],
            'sandbox_key' => [
                'title' => __('Sandbox Key', 'comfino'),
                'type' => 'text'
            ],
            'widget_enabled' => [
                'title' => __('Widget Enable', 'comfino'),
                'type' => 'checkbox',
                'label' => __('Enable Comfino Widget', 'comfino'),
                'default' => 'no',
                'description' => __('Show Widget Comfino in the product', 'comfino')
            ],
            'widget_type' => [
                'title' => __('Widget Type', 'comfino'),
                'type' => 'select',
                'options' => [
                    'simple' => __('Textual widget', 'comfino'),
                    'mixed' => __('Graphical widget with banner', 'comfino'),
                    'with-modal' => __('Graphical widget with installments calculator', 'comfino'),
                ]
            ],
            'widget_offer_type' => [
                'title' => __('Widget Offer Type', 'comfino'),
                'type' => 'select',
                'options' => [
                    'INSTALLMENTS_ZERO_PERCENT' => __('Zero percent installments', 'comfino'),
                    'CONVENIENT_INSTALLMENTS' => __('Convenient installments', 'comfino'),
                    'PAY_LATER' => __('Pay later', 'comfino'),
                ]
            ],
            'widget_price_selector' => [
                'title' => __('Widget Price Selector', 'comfino'),
                'type' => 'text',
                'default' => '.price .woocommerce-Price-amount bdi',
            ],
            'widget_target_selector' => [
                'title' => __('Widget Target Selector', 'comfino'),
                'type' => 'text',
                'default' => '.summary .product_meta',
            ],
            'widget_embed_method' => [
                'title' => __('Widget Embed Method', 'comfino'),
                'type' => 'select',
                'options' => [
                    'INSERT_INTO_FIRST' => 'INSERT_INTO_FIRST',
                    'INSERT_INTO_LAST' => 'INSERT_INTO_LAST',
                    'INSERT_BEFORE' => 'INSERT_BEFORE',
                    'INSERT_AFTER' => 'INSERT_AFTER',
                ]
            ],
            'widget_key' => [
                'title' => __('Widget Key', 'comfino'),
                'type' => 'text',
            ],
            'widget_js_code' => [
                'title' => __('Widget code', 'comfino'),
                'type' => 'textarea',
                'css' => 'width: 800px; height: 400px',
                'default' => '
var script = document.createElement(\'script\');
script.onload = function () {
    ComfinoProductWidget.init({
        widgetKey: \'{WIDGET_KEY}\',
        priceSelector: \'{WIDGET_PRICE_SELECTOR}\',
        widgetTargetSelector: \'{WIDGET_TARGET_SELECTOR}\',
        price: null,
        type: \'{WIDGET_TYPE}\',
        offerType: \'{OFFER_TYPE}\',
        embedMethod: \'{EMBED_METHOD}\',
        callbackBefore: function () {},
        callbackAfter: function () {}
    });
};
script.src = \'{WIDGET_SCRIPT_URL}\';
script.async = true;
document.getElementsByTagName(\'head\')[0].appendChild(script);'
            ],
        ];
    }

    public function process_admin_options()
    {
        $this->init_settings();

        $post_data = $this->get_post_data();

        foreach ($this->get_form_fields() as $key => $field) {
            if ('title' !== $this->get_field_type($field)) {
                try {
                    $this->settings[$key] = $this->get_field_value($key, $field, $post_data);
                } catch (Exception $e) {
                    $this->add_error($e->getMessage());
                }
            }
        }

        return update_option(
            $this->get_option_key(),
            apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings),
            'yes'
        );
    }

    public function admin_options()
    {
        global $wp_version;

        echo "<h2>$this->method_title</h2>";
        echo "<p>$this->method_description</p>";

        echo '<p>'.sprintf(
                __('Do you want to ask about something? Write to us at %s or contact us by phone. We are waiting on the number: %s. We will answer all your questions!', 'comfino'),
                '<a href="mailto:pomoc@comfino.pl?subject='.sprintf(__('WordPress %s WooCommerce %s Comfino %s - question'), $wp_version, WC_VERSION, ComfinoPaymentGateway::VERSION).
                '&body='.str_replace(',', '%2C', sprintf(__('WordPress %s WooCommerce %s Comfino %s, PHP %s'), $wp_version, WC_VERSION, ComfinoPaymentGateway::VERSION, PHP_VERSION)).'">pomoc@comfino.pl</a>', '887-106-027'
            ).'</p>';

        echo '<table class="form-table">';
        echo $this->generate_settings_html();
        echo '</table>';
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
                        'rrso' => number_format($loanParams['rrso'] * 100, 2, ',', ' '),
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

        wp_enqueue_style('comfino', plugins_url('assets/css/comfino.css', __FILE__));
        wp_enqueue_script('comfino', plugins_url('assets/js/comfino.js', __FILE__));
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
            'notifyUrl' => add_query_arg('wc-api', 'Comfino_Gateway', home_url('/')),
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
    public function cancel_order(string $order_id): void
    {
        if (!$this->getStatusNote($order_id, [self::CANCELLED_BY_SHOP_STATUS, self::RESIGN_STATUS])) {
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
    }

    /**
     * @param string $order_id
     */
    public function resign_order(string $order_id): void
    {
        $order = wc_get_order($order_id);

        $body = wp_json_encode([
            'amount' => (int)$order->get_total() * 100
        ]);

        $args = [
            'headers' => $this->get_header_request(),
            'body' => $body,
            'method' => 'PUT'
        ];

        $response = wp_remote_request($this->host . self::COMFINO_ORDERS_ENDPOINT . "/{$order->get_id()}/resign", $args);

        if (is_wp_error($response)) {
            wc_add_notice('Connection error.', 'error');
        }

        $order->add_order_note(__("Send to Comfino resign order", 'comfino'));
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
        $phone_number = $order->get_billing_phone();

        if (empty($phone_number)) {
            // Try to find phone number in order metadata
            $order_metadata = $order->get_meta_data();

            foreach ($order_metadata as $meta_data_item) {
                /** @var WC_Meta_Data $meta_data_item */
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

        return [
            'firstName' => $order->get_billing_first_name(),
            'lastName' => $order->get_billing_last_name(),
            'ip' => WC_Geolocation::get_ip_address(),
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
            'user-agent' => sprintf('WP Comfino [%s], WP [%s], WC [%s], PHP [%s]', ComfinoPaymentGateway::VERSION, $wp_version, WC_VERSION, PHP_VERSION),
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

    /**
     * Show buttons order
     *
     * @param $order
     */
    public function order_buttons($order): void
    {
        echo '<input type="hidden" id="comfino_action" value="" name="comfino_action" />';

        if ($order->get_payment_method() === 'comfino' && !($order->has_status(['cancelled', 'resigned', 'rejected']))) {
            echo '<button type="button" class="button cancel-items" onclick="if(confirm(\'' . __('Are you sure you want to cancel?', 'comfino') . '\')){document.getElementById(\'comfino_action\').value = \'cancel\'; document.post.submit();}">' . __('Cancel', 'comfino') . wc_help_tip(__('Attention: You are cancelling a customer order. Check if you do not have to return the money to Comfino.', 'comfino')) . '</button>';
        }

        if ($this->isActiveResign($order)) {
            echo '<button type="button" class="button cancel-items" onclick="if(confirm(\'' . __('Are you sure you want to resign?', 'comfino') . '\')){document.getElementById(\'comfino_action\').value = \'resign\'; document.post.submit();}">' . __('Resign', 'comfino') . wc_help_tip(__('Attention: you are initiating a resignation of the Customer\'s contract. Required refund to Comfino.', 'comfino')) . '</button>';
        }
    }

    /**
     * @param $order
     *
     * @return bool
     */
    private function isActiveResign($order): bool
    {
        $date = new DateTime();
        $date->sub(new DateInterval('P14D'));

        if ($order->get_payment_method() === 'comfino' && $order->has_status(['processing', 'completed'])) {
            $notes = $this->getStatusNote($order->ID, [self::ACCEPTED_STATUS]);

            return !(isset($notes[self::ACCEPTED_STATUS]) && $notes[self::ACCEPTED_STATUS]->date_created->getTimestamp() < $date->getTimestamp());
        }

        return false;
    }

    /**
     * @param int   $order_id
     * @param array $statuses
     *
     * @return array
     */
    private function getStatusNote(int $order_id, array $statuses): array
    {
        $elements = wc_get_order_notes(['order_id' => $order_id]);
        $notes = [];

        foreach ($elements as $element) {
            foreach ($statuses as $status) {
                if ($element->added_by === 'system' && $element->content === 'Comfino status: ' . $status) {
                    $notes[$status] = $element;
                }
            }
        }

        return $notes;
    }

    /**
     * @param $post_id
     * @param $post
     * @param $update
     */
    public function update_order($post_id, $post, $update): void
    {
        if (is_admin()) {
            if ('shop_order' !== $post->post_type) {
                return;
            }

            if (isset($_POST['comfino_action']) && $_POST['comfino_action']) {
                $order = wc_get_order($post->ID);

                if ($_POST['comfino_action'] === 'cancel') {
                    $this->cancel_order($order->ID);
                }

                if ($_POST['comfino_action'] === 'resign') {
                    $this->resign_order($order->ID);
                }
            }
        }
    }
}
