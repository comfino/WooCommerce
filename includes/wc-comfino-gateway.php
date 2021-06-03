<?php

/**
 * @method get_option(string $string)
 */
class WC_Comfino_Gateway extends WC_Payment_Gateway
{
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
        $this->icon = '';
        $this->has_fields = true;
        $this->method_title = __('Comfino Gateway', 'woocommerce-comfino');
        $this->method_description = __('Comfino payment gateway', 'woocommerce-comfino');

        $this->supports = array(
            'products'
        );

        $this->init_form_fields();

        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');
        $this->loan_term = (int)$this->get_option('loan_term');

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
//        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('woocommerce_api_comfino', array($this, 'webhook'));
    }

    /**
     * Plugin options
     */
    public function init_form_fields(): void
    {
        $this->form_fields = array(
            'enabled' => [
                'title' => __('Enable/Disable', 'woocommerce-comfino'),
                'type' => 'checkbox',
                'label' => __('Enable Comfino Payment Module.', 'woocommerce-comfino'),
                'default' => 'no',
                'description' => __('Show in the Payment List as a payment option', 'woocommerce-comfino')
            ],
            'sandbox_mode' => [
                'title' => __('Sandbox mode:', 'woocommerce-comfino'),
                'type' => 'checkbox',
                'label' => __('Enable Sandbox Mode', 'woocommerce-comfino'),
                'default' => 'yes',
            ],
            'title' => [
                'title' => __('Title:', 'woocommerce-comfino'),
                'type' => 'text',
                'default' => 'Comfino.pl',
            ],
            'sandbox_key' => [
                'title' => __('Sandbox Key', 'woocommerce-comfino'),
                'type' => 'text'
            ],
            'production_key' => [
                'title' => __('Production Key', 'woocommerce-comfino'),
                'type' => 'text'
            ],
            'loan_term' => [
                'title' => __('Loan Term', 'woocommerce-comfino'),
                'type' => 'int',
                'default' => '48',
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

        foreach ($offers as $offer) {
            $instalmentAmount = number_format($offer['instalmentAmount'] / 100, 2, ',', ' ');
            $toPay = number_format($offer['toPay'] / 100, 2, ',', ' ');
            $rrso = number_format($offer['rrso'] * 100, 2, ',', ' ');

            echo '<div id="offer_INSTALLMENTS_ZERO_PERCENT" style="position: relative; margin: 0; padding-bottom: 0;">
                    <div style="text-align: center">
                        <div class="icon" style="margin-bottom: 10px;">' . $offer['icon'] . '</div>
                        <div class="name" style="margin-bottom: 10px;"><strong>' . $offer['name'] . '</strong></div>
                        <div class="offer" style="margin-bottom: 10px;">
                            <div><strong> ' . $loanTerm . ' rat x ' . $instalmentAmount . ' zł</strong></div>
                            <div>Całkowita kwota do spłaty: <b>' . $toPay . ' zł</b>, RRSO: ' . $rrso . ' %</div>
                        </div>
                        <div class="description" style="margin-bottom: 10px;">' . $offer['description'] . '</div>
                        <div><a id="representativeExample_INSTALLMENTS_ZERO_PERCENT" href="#">Przykład reprezentatywny</a>
                        </div>
                        <div style="display: none" id="representativeExample_modal_INSTALLMENTS_ZERO_PERCENT">
                            <div class="modal-inner-content">
                                <p id="representativeExample_modal_textINSTALLMENTS_ZERO_PERCENT" class="alertbar">' . $offer['representativeExample'] . '</p>
                            </div>
                        </div>
                    </div>
                </div>';
        }
    }

    public function payment_scripts()
    {

    }

    /**
     * @param $order_id
     *
     * @return array
     */
    public function process_payment($order_id): array
    {
        global $woocommerce;

        $order = wc_get_order($order_id);
        $body = wp_json_encode([
            'returnUrl' => $this->get_return_url($order),
            'orderId' => $order->get_id() . '__1',
            'notifyUrl' => $this->get_return_url($order),
            'loanParameters' => [
                'amount' => $order->get_total() * 100,
                'term' => $this->loan_term,
            ],
            'cart' => [
                'totalAmount' => $order->get_total() * 100,
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

            $order->add_order_note(__("Comfino create order", 'woocommerce-comfino'));
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
        // $order->payment_complete();
    }

    /**
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
                'price' => $data['total'] * 100,
            ];
        }

        return $products;
    }

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
     * @return array
     */
    private function get_header_request(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Api-Key' => $this->key,
            'user-agent' => sprintf('WP Woocommerce Comfino [%s]', WC_ComfinoPaymentGateway::VERSION),
        ];
    }
}
