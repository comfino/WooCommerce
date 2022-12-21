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
    private const TYPE_COMPANY_INSTALLMENTS = 'COMPANY_INSTALLMENTS';
    private const TYPE_RENEWABLE_LIMIT = 'RENEWABLE_LIMIT';
    private const ERROR_LOG_NUM_LINES = 40;

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
        self::TYPE_COMPANY_INSTALLMENTS,
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

    private static $key;
    private static $host;
    private static $show_logo;

    private const COMFINO_OFFERS_ENDPOINT = '/v1/financial-products';
    private const COMFINO_ORDERS_ENDPOINT = '/v1/orders';
    private const COMFINO_WIDGET_KEY_ENDPOINT = '/v1/widget-key';
    private const COMFINO_ERROR_LOG_ENDPOINT = '/v1/log-plugin-error';
    private const COMFINO_USER_IS_ACTIVE_ENDPOINT = '/v1/user/is-active';
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
        $this->method_title = __('Comfino Gateway', 'comfino-payment-gateway');
        $this->method_description = __('Comfino payment gateway', 'comfino-payment-gateway');

        $this->supports = ['products'];

        $this->init_form_fields();

        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');

        self::$show_logo = 'yes' === $this->get_option('show_logo');

        $sandbox_mode = 'yes' === $this->get_option('sandbox_mode');
        $sandbox_key = $this->get_option('sandbox_key');
        $production_key = $this->get_option('production_key');

        if ($sandbox_mode) {
            self::$host = self::COMFINO_SANDBOX_HOST;
            self::$key = $sandbox_key;
        } else {
            self::$host = self::COMFINO_PRODUCTION_HOST;
            self::$key = $production_key;
        }

        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_comfino_gateway', [$this, 'webhook']);
        add_action('woocommerce_order_status_cancelled', [$this, 'cancel_order']);
        add_action('woocommerce_order_item_add_action_buttons', [$this, 'order_buttons'], 10, 1);
        add_action('save_post', [$this, 'update_order'], 10, 3);
    }

    /**
     * @param \Comfino\ShopPluginError $error
     * @return bool
     */
    public static function send_logged_error(\Comfino\ShopPluginError $error): bool
    {
        $request = new \Comfino\ShopPluginErrorRequest();

        if (!$request->prepare_request($error, self::get_user_agent_header())) {
            \Comfino\ErrorLogger::log_error('Error request preparation failed', $error->error_message);

            return false;
        }

        $args = [
            'headers' => self::get_header_request(),
            'body' => wp_json_encode(['error_details' => $request->error_details, 'hash' => $request->hash]),
        ];

        $response = wp_remote_post(self::$host.self::COMFINO_ERROR_LOG_ENDPOINT, $args);

        return !is_wp_error($response) && strpos(wp_remote_retrieve_body($response), '"errors":') === false && wp_remote_retrieve_response_code($response) < 400;
    }

    /**
     * Plugin options
     */
    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Comfino Payment Module.', 'comfino-payment-gateway'),
                'default' => 'no',
                'description' => __('Show in the Payment List as a payment option.', 'comfino-payment-gateway')
            ],
            'title' => [
                'title' => __('Title', 'comfino-payment-gateway'),
                'type' => 'text',
                'default' => 'Comfino',
            ],
            'production_key' => [
                'title' => __('Production key', 'comfino-payment-gateway'),
                'type' => 'text'
            ],
            'show_logo' => [
                'title' => __('Show logo', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Show logo on payment method', 'comfino-payment-gateway'),
                'default' => 'yes',
            ],
            'sandbox_mode' => [
                'title' => __('Sandbox mode', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable sandbox mode', 'comfino-payment-gateway'),
                'default' => 'no',
            ],
            'sandbox_key' => [
                'title' => __('Sandbox key', 'comfino-payment-gateway'),
                'type' => 'text'
            ],
            'widget_enabled' => [
                'title' => __('Widget enable', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Comfino widget', 'comfino-payment-gateway'),
                'default' => 'no',
                'description' => __('Show Comfino widget in the product.', 'comfino-payment-gateway')
            ],
            'widget_type' => [
                'title' => __('Widget type', 'comfino-payment-gateway'),
                'type' => 'select',
                'options' => [
                    'simple' => __('Textual widget', 'comfino-payment-gateway'),
                    'mixed' => __('Graphical widget with banner', 'comfino-payment-gateway'),
                    'with-modal' => __('Graphical widget with installments calculator', 'comfino-payment-gateway'),
                ]
            ],
            'widget_offer_type' => [
                'title' => __('Widget offer type', 'comfino-payment-gateway'),
                'type' => 'select',
                'options' => [
                    'INSTALLMENTS_ZERO_PERCENT' => __('Zero percent installments', 'comfino-payment-gateway'),
                    'CONVENIENT_INSTALLMENTS' => __('Convenient installments', 'comfino-payment-gateway'),
                    'PAY_LATER' => __('Pay later', 'comfino-payment-gateway'),
                ]
            ],
            'widget_embed_method' => [
                'title' => __('Widget embed method', 'comfino-payment-gateway'),
                'type' => 'select',
                'options' => [
                    'INSERT_INTO_FIRST' => 'INSERT_INTO_FIRST',
                    'INSERT_INTO_LAST' => 'INSERT_INTO_LAST',
                    'INSERT_BEFORE' => 'INSERT_BEFORE',
                    'INSERT_AFTER' => 'INSERT_AFTER',
                ]
            ],
            'widget_price_selector' => [
                'title' => __('Widget price selector', 'comfino-payment-gateway'),
                'type' => 'text',
                'default' => '.price .woocommerce-Price-amount bdi',
            ],
            'widget_target_selector' => [
                'title' => __('Widget target selector', 'comfino-payment-gateway'),
                'type' => 'text',
                'default' => '.summary .product_meta',
            ],
            'widget_price_observer_level' => [
                'title' => __('Price change detection level', 'comfino-payment-gateway'),
                'type' => 'number',
                'default' => 0,
                'description' => __(
                    'Hierarchy level of observed parent element relative to the price element.',
                    'comfino-payment-gateway'
                )
            ],
            'widget_key' => [
                'title' => __('Widget key', 'comfino-payment-gateway'),
                'type' => 'text',
            ],
            'widget_js_code' => [
                'title' => __('Widget code', 'comfino-payment-gateway'),
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
        priceObserverLevel: \'{PRICE_OBSERVER_LEVEL}\',
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
        $is_error = false;

        foreach ($this->get_form_fields() as $key => $field) {
            if ('title' !== $this->get_field_type($field)) {
                try {
                    $this->settings[$key] = $this->get_field_value($key, $field, $post_data);
                } catch (Exception $e) {
                    $this->add_error($e->getMessage());

                    $is_error = true;
                }
            }
        }

        $is_sandbox = $this->settings['sandbox_mode'] === 'yes';
        $api_host = $is_sandbox ? self::COMFINO_SANDBOX_HOST : self::COMFINO_PRODUCTION_HOST;
        $api_key = $is_sandbox ? $this->settings['sandbox_key'] : $this->settings['production_key'];

        if (!$this->is_api_key_valid($api_host, $api_key)) {
            $this->add_error(sprintf(__('API key %s is not valid.', 'comfino-payment-gateway'), $api_key));

            $is_error = true;
        }

        if ($is_error) {
            $this->display_errors();

            return false;
        }

        $this->settings['widget_key'] = $this->get_widget_key($api_host, $api_key);

        return update_option(
            $this->get_option_key(),
            apply_filters('woocommerce_settings_api_sanitized_fields_'.$this->id, $this->settings),
            'yes'
        );
    }

    public function admin_options()
    {
        global $wp_version;

        $errorsLog = \Comfino\ErrorLogger::get_error_log(self::ERROR_LOG_NUM_LINES);

        echo '<h2>'.esc_html($this->method_title).'</h2>';
        echo '<p>'.esc_html($this->method_description).'</p>';

        echo '<p>'.sprintf(
                __('Do you want to ask about something? Write to us at %s or contact us by phone. We are waiting on the number: %s. We will answer all your questions!', 'comfino-payment-gateway'),
                '<a href="mailto:pomoc@comfino.pl?subject='.sprintf(__('WordPress %s WooCommerce %s Comfino %s - question', 'comfino-payment-gateway'), $wp_version, WC_VERSION, ComfinoPaymentGateway::VERSION).
                '&body='.str_replace(',', '%2C', sprintf(__('WordPress %s WooCommerce %s Comfino %s, PHP %s', 'comfino-payment-gateway'), $wp_version, WC_VERSION, ComfinoPaymentGateway::VERSION, PHP_VERSION)).'">pomoc@comfino.pl</a>', '887-106-027'
            ).'</p>';

        echo '<table class="form-table">';
        echo $this->generate_settings_html();
        echo '<tr valign="top"><th scope="row" class="titledesc"><label>'.__('Errors log', 'comfino-payment-gateway').'</label></th>';
        echo '<td><textarea cols="20" rows="3" class="input-text wide-input" style="width: 800px; height: 400px">'.esc_textarea($errorsLog).'</textarea></td></tr>';
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
                        <div class="comfino-title">'.__('Choose payment method', 'comfino-payment-gateway').'</div>
                    </div>
                    <main>
                        <section id="comfino-offer-items" class="comfino-select-payment"></section>
                        <section class="comfino-payment-box">
                            <div class="comfino-payment-title">'.__('Value of purchase', 'comfino-payment-gateway').':</div>
                            <div id="comfino-total-payment" class="comfino-total-payment"></div>
                        </section>
                        <section id="comfino-installments">
                            <section class="comfino-installments-box">
                                <div class="comfino-installments-title">'.__('Choose number of instalments', 'comfino-payment-gateway').'</div>
                                <div id="comfino-quantity-select" class="comfino-quantity-select"></div>
                            </section>
                            <section class="comfino-monthly-box">
                                <div class="comfino-monthly-title">'.__('Monthly instalment', 'comfino-payment-gateway').':</div>
                                <div id="comfino-monthly-rate" class="comfino-monthly-rate"></div>
                            </section>
                            <section class="comfino-summary-box">
                                <div class="comfino-summary-total">'.__('Total amount to pay', 'comfino-payment-gateway').': <span id="comfino-summary-total"></span></div>
                                <div class="comfino-rrso">RRSO <span id="comfino-rrso"></span></div>
                                <div id="comfino-description-box" class="comfino-description-box"></div>
                            </section>
                            <footer>
                                <a id="comfino-repr-example-link" class="representative comfino-footer-link">'.__('Representative example', 'comfino-payment-gateway').'</a>
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
                            <div class="comfino-payment-delay__title">'.__('Buy now, pay in 30 days', 'comfino-payment-gateway').' <span>'.__('How it\'s working?', 'comfino-payment-gateway').'</span></div>
                            <div class="comfino-payment-delay__box">
                                <div class="comfino-helper-box">
                                    <div class="comfino-payment-delay__single-instruction">
                                        <div class="single-instruction-img__background">
                                            <img src="//widget.comfino.pl/image/comfino/ecommerce/woocommerce/icons/cart.svg" alt="" class="single-instruction-img" />
                                        </div>
                                        <div class="comfin-single-instruction__text">'.__('Put the product in the basket', 'comfino-payment-gateway').'</div>
                                    </div>
                                    <div class="comfino-payment-delay__single-instruction">
                                        <div class="single-instruction-img__background">
                                            <img src="//widget.comfino.pl/image/comfino/ecommerce/woocommerce/icons/twisto.svg" alt="" class="single-instruction-img" />
                                        </div>
                                        <div class="comfin-single-instruction__text">'.__('Choose Twisto payment', 'comfino-payment-gateway').'</div>
                                    </div>
                                </div>
                                <div class="comfino-helper-box">
                                    <div class="comfino-payment-delay__single-instruction">
                                        <div class="single-instruction-img__background">
                                            <img src="//widget.comfino.pl/image/comfino/ecommerce/woocommerce/icons/icons/check.svg" alt="" class="single-instruction-img" />
                                        </div>
                                        <div class="comfin-single-instruction__text">'.__('Check the products at home', 'comfino-payment-gateway').'</div>
                                    </div>
                                    <div class="comfino-payment-delay__single-instruction">
                                        <div class="single-instruction-img__background">
                                            <img src="//widget.comfino.pl/image/comfino/ecommerce/woocommerce/icons/icons/wallet.svg" alt="" class="single-instruction-img" />
                                        </div>
                                        <div class="comfin-single-instruction__text">'.__('Pay in 30 days', 'comfino-payment-gateway').'</div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </main>
                </div>
            </div>
            <input id="comfino-loan-term" name="comfino_loan_term" type="hidden" />
            <input id="comfino-type" name="comfino_type" type="hidden" />            
            <script>Comfino.initPayments(' . json_encode($paymentInfos).')</script>
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

        $loanTerm = sanitize_text_field($_POST['comfino_loan_term']);
        $type = sanitize_text_field($_POST['comfino_type']);

        if (!ctype_digit($loanTerm)) {
            return ['result' => 'failure', 'redirect' => ''];
        }

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
                'totalAmount' => (int)($order->get_total() * 100),
                'deliveryCost' => (int)($order->get_shipping_total() * 100),
                'products' => $this->get_products(),
            ],
            'customer' => $this->get_customer($order),
        ]);

        $url = self::$host.self::COMFINO_ORDERS_ENDPOINT;
        $args = [
            'headers' => self::get_header_request(),
            'body' => $body,
        ];

        $response = wp_remote_post($url, $args);

        if (!is_wp_error($response)) {
            $decoded = json_decode(wp_remote_retrieve_body($response), true);

            if (!is_array($decoded) || isset($decoded['errors']) || empty($decoded['applicationUrl'])) {
                \Comfino\ErrorLogger::send_error(
                    'Payment error',
                    wp_remote_retrieve_response_code($response),
                    is_array($decoded) && isset($decoded['errors']) ? implode(', ', $decoded['errors']) : 'API call error '.wp_remote_retrieve_response_code($response),
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

        \Comfino\ErrorLogger::send_error(
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

    /**
     * @param string $order_id
     */
    public function cancel_order(string $order_id): void
    {
        if (!$this->get_status_note($order_id, [self::CANCELLED_BY_SHOP_STATUS, self::RESIGN_STATUS])) {
            $order = wc_get_order($order_id);

            $url = self::$host.self::COMFINO_ORDERS_ENDPOINT."/{$order->get_id()}/cancel";
            $args = [
                'headers' => self::get_header_request(),
                'method' => 'PUT'
            ];

            $response = wp_remote_request($url, $args);

            if (is_wp_error($response)) {
                $timestamp = time();

                \Comfino\ErrorLogger::send_error(
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

    /**
     * @param string $order_id
     */
    public function resign_order(string $order_id): void
    {
        $order = wc_get_order($order_id);

        $body = wp_json_encode(['amount' => (int)$order->get_total() * 100]);

        $url = self::$host.self::COMFINO_ORDERS_ENDPOINT."/{$order->get_id()}/resign";
        $args = [
            'headers' => self::get_header_request(),
            'body' => $body,
            'method' => 'PUT'
        ];

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $timestamp = time();

            \Comfino\ErrorLogger::send_error(
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

    /**
     * Webhook notifications
     */
    public function webhook(): void
    {
        $body = file_get_contents('php://input');

        if (!$this->valid_signature($body)) {
            echo json_encode(['status' => 'Invalid signature', 'body' => $body, 'signature' => $this->get_signature()]);
            exit;
        }

        $data = json_decode($body, true);
        $order = wc_get_order($data['externalId']);
        $status = $data['status'];

        if ($order) {
            $order->add_order_note(__('Comfino status', 'comfino-payment-gateway') . ": $status");

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
        $url = self::$host.self::COMFINO_OFFERS_ENDPOINT.'?'.http_build_query(['loanAmount' => $loanAmount]);
        $args = ['headers' => self::get_header_request()];

        $response = wp_remote_get($url, $args);

        if (!is_wp_error($response)) {
            $decoded = json_decode(wp_remote_retrieve_body($response), true);

            if (!is_array($decoded) || isset($decoded['errors'])) {
                \Comfino\ErrorLogger::send_error(
                    'Payment error',
                    wp_remote_retrieve_response_code($response),
                    is_array($decoded) && isset($decoded['errors']) ? implode(', ', $decoded['errors']) : 'API call error '.wp_remote_retrieve_response_code($response),
                    $url,
                    null,
                    wp_remote_retrieve_body($response)
                );

                $decoded = [];
            }

            return $decoded;
        }

        \Comfino\ErrorLogger::send_error(
            'Communication error',
            implode(', ', $response->get_error_codes()),
            implode(', ', $response->get_error_messages()),
            $url,
            null,
            wp_remote_retrieve_body($response)
        );

        return [];
    }

    /**
     * Fetch widget key
     *
     * @param string $api_host
     * @param string $api_key
     *
     * @return string
     */
    private function get_widget_key(string $api_host, string $api_key): string
    {
        self::$host = $api_host;
        self::$key = $api_key;

        $widget_key = '';

        if (!empty(self::$key)) {
            $response = wp_remote_get(
                self::$host.self::COMFINO_WIDGET_KEY_ENDPOINT,
                ['headers' => self::get_header_request()]
            );

            if (!is_wp_error($response)) {
                $widget_key = json_decode(wp_remote_retrieve_body($response), true);
            }
        }

        return $widget_key !== false ? $widget_key : '';
    }

    /**
     * @param string $api_host
     * @param string $api_key
     *
     * @return bool
     */
    private function is_api_key_valid(string $api_host, string $api_key): bool
    {
        self::$host = $api_host;
        self::$key = $api_key;

        $api_key_valid = false;

        if (!empty(self::$key)) {
            $response = wp_remote_get(
                self::$host.self::COMFINO_USER_IS_ACTIVE_ENDPOINT,
                ['headers' => self::get_header_request()]
            );

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $api_key_valid = strpos(wp_remote_retrieve_body($response), 'errors') === false;
            }
        }

        return $api_key_valid;
    }

    /**
     * Prepare product data
     *
     * @return array
     */
    private function get_products(): array
    {
        $products = [];

        foreach (WC()->cart->get_cart() as $item) {
            /** @var WC_Product_Simple $product */
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
    private static function get_header_request(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Api-Key' => self::$key,
            'User-Agent' => self::get_user_agent_header(),
        ];
    }

    /**
     * @return string
     */
    private static function get_user_agent_header(): string
    {
        global $wp_version;

        return sprintf('WP Comfino [%s], WP [%s], WC [%s], PHP [%s]', ComfinoPaymentGateway::VERSION, $wp_version, WC_VERSION, PHP_VERSION);
    }

    /**
     * @param string $jsonData
     *
     * @return bool
     */
    private function valid_signature(string $jsonData): bool
    {
        return $this->get_signature() === hash('sha3-256', self::$key.$jsonData);
    }

    /**
     * @return string
     */
    private function get_signature(): string
    {
        $signature = '';

        foreach ($_SERVER as $key => $value) {
            if ($key === 'HTTP_CR_SIGNATURE') {
                $signature = sanitize_text_field($value);

                break;
            }
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
        if (self::$show_logo) {
            $icon = '<img style="height: 18px; margin: 0 5px;" src="//widget.comfino.pl/image/comfino/ecommerce/woocommerce/comfino.png" alt="Comfino Logo" />';
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
            echo '<button type="button" class="button cancel-items" onclick="if(confirm(\''.__('Are you sure you want to cancel?', 'comfino-payment-gateway').'\')){document.getElementById(\'comfino_action\').value = \'cancel\'; document.post.submit();}">'.__('Cancel', 'comfino-payment-gateway') . wc_help_tip(__('Attention: You are cancelling a customer order. Check if you do not have to return the money to Comfino.', 'comfino-payment-gateway')).'</button>';
        }

        if ($this->is_active_resign($order)) {
            echo '<button type="button" class="button cancel-items" onclick="if(confirm(\''.__('Are you sure you want to resign?', 'comfino-payment-gateway').'\')){document.getElementById(\'comfino_action\').value = \'resign\'; document.post.submit();}">'.__('Resign', 'comfino-payment-gateway') . wc_help_tip(__('Attention: you are initiating a resignation of the Customer\'s contract. Required refund to Comfino.', 'comfino-payment-gateway')).'</button>';
        }
    }

    /**
     * @param $order
     *
     * @return bool
     */
    private function is_active_resign($order): bool
    {
        $date = new DateTime();
        $date->sub(new DateInterval('P14D'));

        if ($order->get_payment_method() === 'comfino' && $order->has_status(['processing', 'completed'])) {
            $notes = $this->get_status_note($order->ID, [self::ACCEPTED_STATUS]);

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
                $action = sanitize_text_field($_POST['comfino_action']);

                if ($action === 'cancel') {
                    $this->cancel_order($order->ID);
                } elseif ($action === 'resign') {
                    $this->resign_order($order->ID);
                }
            }
        }
    }
}
