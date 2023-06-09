<?php

class Comfino_Gateway extends WC_Payment_Gateway
{
    /**
     * @var \Comfino\Config_Manager
     */
    private $config_manager;

    public $id;
    public $icon;
    public $has_fields;
    public $method_title;
    public $method_description;
    public $supports;
    public $title;
    public $enabled;

    private static $show_logo;

    const COMFINO_WIDGET_JS_SANDBOX = 'https://widget.craty.pl/comfino.min.js';
    const COMFINO_WIDGET_JS_PRODUCTION = 'https://widget.comfino.pl/comfino.min.js';

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

        $this->config_manager = new \Comfino\Config_Manager();

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');

        self::$show_logo = 'yes' === $this->get_option('show_logo');

        $sandbox_mode = 'yes' === $this->get_option('sandbox_mode');
        $sandbox_key = $this->get_option('sandbox_key');
        $production_key = $this->get_option('production_key');

        if ($sandbox_mode) {
            \Comfino\Api_Client::$host= \Comfino\Core::COMFINO_SANDBOX_HOST;
            \Comfino\Api_Client::$key = $sandbox_key;
        } else {
            \Comfino\Api_Client::$host= \Comfino\Core::COMFINO_PRODUCTION_HOST;
            \Comfino\Api_Client::$key = $production_key;
        }

        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_comfino_gateway', [$this, 'webhook']);
        add_action('woocommerce_order_status_cancelled', [$this, 'cancel_order']);
        add_action('woocommerce_order_item_add_action_buttons', [$this, 'order_buttons'], 10, 1);
        add_action('save_post', [$this, 'update_order'], 10, 3);
    }

    /**
     * Plugin options.
     */
    public function init_form_fields()
    {
        $this->form_fields = $this->config_manager->get_form_fields();
    }

    public function process_admin_options(): bool
    {
        return $this->config_manager->update_configuration($this->get_post_data(), false);
    }

    public function admin_options()
    {
        global $wp_version;

        $errorsLog = \Comfino\Error_Logger::get_error_log(\Comfino\Core::ERROR_LOG_NUM_LINES);

        echo '<h2>' . esc_html($this->method_title) . '</h2>';
        echo '<p>' . esc_html($this->method_description) . '</p>';

        echo '<p>' . sprintf(
                __('Do you want to ask about something? Write to us at %s or contact us by phone. We are waiting on the number: %s. We will answer all your questions!', 'comfino-payment-gateway'),
                '<a href="mailto:pomoc@comfino.pl?subject=' . sprintf(__('WordPress %s WooCommerce %s Comfino %s - question', 'comfino-payment-gateway'), $wp_version, WC_VERSION, Comfino_Payment_Gateway::VERSION) .
                '&body=' . str_replace(',', '%2C', sprintf(__('WordPress %s WooCommerce %s Comfino %s, PHP %s', 'comfino-payment-gateway'), $wp_version, WC_VERSION, Comfino_Payment_Gateway::VERSION, PHP_VERSION)) . '">pomoc@comfino.pl</a>', '887-106-027'
            ) . '</p>';

        echo '<table class="form-table">';
        echo $this->generate_settings_html();
        echo '<tr valign="top"><th scope="row" class="titledesc"><label>' . __('Errors log', 'comfino-payment-gateway') . '</label></th>';
        echo '<td><textarea cols="20" rows="3" class="input-text wide-input" style="width: 800px; height: 400px">' . esc_textarea($errorsLog) . '</textarea></td></tr>';
        echo '</table>';
    }

    /**
     * Show offers.
     */
    public function payment_fields()
    {
        global $woocommerce;

        $total = (int)round($woocommerce->cart->total) * 100;
        $offers = \Comfino\Api_Client::fetch_offers($total);
        $payment_infos = [];

        foreach ($offers as $offer) {
            $instalmentAmount = ((float)$offer['instalmentAmount']) / 100;
            $rrso = ((float)$offer['rrso']) * 100;
            $toPay = ((float)$offer['toPay']) / 100;

            $payment_infos[] = [
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
                'loanParameters' => array_map(static function ($loan_params) use ($total) {
                    return [
                        'loanTerm' => $loan_params['loanTerm'],
                        'instalmentAmount' => number_format(((float)$loan_params['instalmentAmount']) / 100, 2, ',', ' '),
                        'toPay' => number_format(((float)$loan_params['toPay']) / 100, 2, ',', ' '),
                        'sumAmount' => number_format($total / 100, 2, ',', ' '),
                        'rrso' => number_format($loan_params['rrso'] * 100, 2, ',', ' '),
                    ];
                }, $offer['loanParameters']),
            ];
        }

        echo '
            <div id="comfino-box" class="comfino">
                <div class="comfino-box">
                    <div class="header">
                        <div class="comfino-title">' . __('Choose payment method', 'comfino-payment-gateway') . '</div>
                    </div>
                    <main>
                        <section id="comfino-offer-items" class="comfino-select-payment"></section>
                        <section class="comfino-payment-box">
                            <div class="comfino-payment-title">' . __('Value of purchase', 'comfino-payment-gateway') . ':</div>
                            <div id="comfino-total-payment" class="comfino-total-payment"></div>
                        </section>
                        <section id="comfino-installments">
                            <section class="comfino-installments-box">
                                <div class="comfino-installments-title">' . __('Choose number of instalments', 'comfino-payment-gateway') . '</div>
                                <div id="comfino-quantity-select" class="comfino-quantity-select"></div>
                            </section>
                            <section class="comfino-monthly-box">
                                <div class="comfino-monthly-title">' . __('Monthly instalment', 'comfino-payment-gateway') . ':</div>
                                <div id="comfino-monthly-rate" class="comfino-monthly-rate"></div>
                            </section>
                            <section class="comfino-summary-box">
                                <div class="comfino-summary-total">' . __('Total amount to pay', 'comfino-payment-gateway') . ': <span id="comfino-summary-total"></span></div>
                                <div class="comfino-rrso">RRSO <span id="comfino-rrso"></span></div>
                                <div id="comfino-description-box" class="comfino-description-box"></div>
                            </section>
                            <footer>
                                <a id="comfino-repr-example-link" class="representative comfino-footer-link">' . __('Representative example', 'comfino-payment-gateway') . '</a>
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
                            <div class="comfino-payment-delay__title">' . __('Buy now, pay in 30 days', 'comfino-payment-gateway') . ' <span>' . __('How it\'s working?', 'comfino-payment-gateway') . '</span></div>
                            <div class="comfino-payment-delay__box">
                                <div class="comfino-helper-box">
                                    <div class="comfino-payment-delay__single-instruction">
                                        <div class="single-instruction-img__background">
                                            <img src="//widget.comfino.pl/image/comfino/ecommerce/woocommerce/icons/cart.svg" alt="" class="single-instruction-img" />
                                        </div>
                                        <div class="comfin-single-instruction__text">' . __('Put the product in the basket', 'comfino-payment-gateway') . '</div>
                                    </div>
                                    <div class="comfino-payment-delay__single-instruction">
                                        <div class="single-instruction-img__background">
                                            <img src="//widget.comfino.pl/image/comfino/ecommerce/woocommerce/icons/twisto.svg" alt="" class="single-instruction-img" />
                                        </div>
                                        <div class="comfin-single-instruction__text">' . __('Choose Twisto payment', 'comfino-payment-gateway') . '</div>
                                    </div>
                                </div>
                                <div class="comfino-helper-box">
                                    <div class="comfino-payment-delay__single-instruction">
                                        <div class="single-instruction-img__background">
                                            <img src="//widget.comfino.pl/image/comfino/ecommerce/woocommerce/icons/icons/check.svg" alt="" class="single-instruction-img" />
                                        </div>
                                        <div class="comfin-single-instruction__text">' . __('Check the products at home', 'comfino-payment-gateway') . '</div>
                                    </div>
                                    <div class="comfino-payment-delay__single-instruction">
                                        <div class="single-instruction-img__background">
                                            <img src="//widget.comfino.pl/image/comfino/ecommerce/woocommerce/icons/icons/wallet.svg" alt="" class="single-instruction-img" />
                                        </div>
                                        <div class="comfin-single-instruction__text">' . __('Pay in 30 days', 'comfino-payment-gateway') . '</div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </main>
                </div>
            </div>
            <input id="comfino-loan-term" name="comfino_loan_term" type="hidden" />
            <input id="comfino-type" name="comfino_type" type="hidden" />            
            <script>Comfino.initPayments(' . json_encode($payment_infos) . ')</script>
        ';
    }

    /**
     * Include CSS and JS
     */
    public function payment_scripts()
    {
        if ($this->enabled === 'no') {
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
        $order = wc_get_order($order_id);

        return \Comfino\Api_Client::process_payment($order, $this->get_return_url($order), \Comfino\Core::get_notify_url());
    }

    /**
     * Webhook notifications.
     */
    public function webhook()
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
            if (in_array($status, [\Comfino\Core::ACCEPTED_STATUS, \Comfino\Core::CANCELLED_STATUS, \Comfino\Core::CANCELLED_BY_SHOP_STATUS, \Comfino\Core::REJECTED_STATUS, \Comfino\Core::RESIGN_STATUS], true)) {
                $order->add_order_note(__('Comfino status', 'comfino-payment-gateway') . ": " . __($status, 'comfino-payment-gateway'));
            }

            if (in_array($status, $this->completed_state, true)) {
                $order->payment_complete();
            }

            if (in_array($status, $this->rejected_state, true)) {
                $order->cancel_order();
            }
        }
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
     * Prepare request headers.
     */
    private static function get_header_request(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Api-Key' => \Comfino\Api_Client::$key,
            'User-Agent' => self::get_user_agent_header(),
        ];
    }

    private static function get_user_agent_header(): string
    {
        global $wp_version;

        return sprintf('WP Comfino [%s], WP [%s], WC [%s], PHP [%s]', Comfino_Payment_Gateway::VERSION, $wp_version, WC_VERSION, PHP_VERSION);
    }

    /**
     * @param string $jsonData
     *
     * @return bool
     */
    private function valid_signature(string $jsonData): bool
    {
        return $this->get_signature() === hash('sha3-256', \Comfino\Api_Client::$key . $jsonData);
    }

    /**
     * @return string
     */
    private function get_signature(): string
    {
        return $this->get_header_by_name('CR_SIGNATURE');
    }

    /**
     * @param string $name
     * @return string
     */
    private function get_header_by_name(string $name): string
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
    public function order_buttons($order)
    {
        echo '<input type="hidden" id="comfino_action" value="" name="comfino_action" />';

        if ($order->get_payment_method() === 'comfino' && !($order->has_status(['cancelled', 'resigned', 'rejected']))) {
            echo '<button type="button" class="button cancel-items" onclick="if(confirm(\'' . __('Are you sure you want to cancel?', 'comfino-payment-gateway') . '\')){document.getElementById(\'comfino_action\').value = \'cancel\'; document.post.submit();}">' . __('Cancel', 'comfino-payment-gateway') . wc_help_tip(__('Attention: You are cancelling a customer order. Check if you do not have to return the money to Comfino.', 'comfino-payment-gateway')) . '</button>';
        }

        if ($this->is_active_resign($order)) {
            echo '<button type="button" class="button cancel-items" onclick="if(confirm(\'' . __('Are you sure you want to resign?', 'comfino-payment-gateway') . '\')){document.getElementById(\'comfino_action\').value = \'resign\'; document.post.submit();}">' . __('Resign', 'comfino-payment-gateway') . wc_help_tip(__('Attention: you are initiating a resignation of the Customer\'s contract. Required refund to Comfino.', 'comfino-payment-gateway')) . '</button>';
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
            $notes = $this->get_status_note($order->ID, [\Comfino\Core::ACCEPTED_STATUS]);

            return !(isset($notes[\Comfino\Core::ACCEPTED_STATUS]) && $notes[\Comfino\Core::ACCEPTED_STATUS]->date_created->getTimestamp() < $date->getTimestamp());
        }

        return false;
    }

    /**
     * @param int $order_id
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
    public function update_order($post_id, $post, $update)
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
                    \Comfino\Api_Client::resign_order($order->ID);
                }
            }
        }
    }
}
