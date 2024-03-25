<?php

use Comfino\Api_Client;
use Comfino\Config_Manager;
use Comfino\Core;
use Comfino\Error_Logger;

class Comfino_Gateway extends WC_Payment_Gateway
{
    /**
     * @var Config_Manager
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
    public $abandoned_cart_enabled;
    public $abandoned_payments;

    private static $show_logo;

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

        $this->config_manager = new Config_Manager();

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');
        $this->abandoned_cart_enabled = $this->get_option('abandoned_cart_enabled');
        $this->abandoned_payments = $this->get_option('abandoned_payments');

        self::$show_logo = ($this->get_option('show_logo') === 'yes');

        Api_Client::init($this->config_manager);

        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_comfino_gateway', [$this, 'webhook']);
        add_action('woocommerce_order_status_cancelled', [$this, 'cancel_order']);
        add_action('woocommerce_order_status_changed', [$this, 'change_order'], 10, 3);
        add_filter('woocommerce_available_payment_gateways', [$this, 'filter_gateways'], 1);
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
        return $this->config_manager->update_configuration($this->get_subsection(), $this->get_post_data(), false);
    }

    public function admin_options()
    {
        global $wp, $wp_version, $wpdb;

        $errors_log = Error_Logger::get_error_log(Core::ERROR_LOG_NUM_LINES);
        $subsection = $this->get_subsection();

        echo '<h2>' . esc_html($this->method_title) . '</h2>';
        echo '<p>' . esc_html($this->method_description) . '</p>';

        echo '<img style="width: 300px" src="' . esc_url(Api_Client::get_logo_url()) . '" alt="Comfino logo"> <span style="font-weight: bold; font-size: 16px; vertical-align: bottom">' . Comfino_Payment_Gateway::VERSION . '</span>';

        echo '<p>' . sprintf(
                __('Do you want to ask about something? Write to us at %s or contact us by phone. We are waiting on the number: %s. We will answer all your questions!', 'comfino-payment-gateway'),
                '<a href="mailto:pomoc@comfino.pl?subject=' . sprintf(__('WordPress %s WooCommerce %s Comfino %s - question', 'comfino-payment-gateway'), $wp_version, WC_VERSION, Comfino_Payment_Gateway::VERSION) .
                '&body=' . str_replace(',', '%2C', sprintf(__('WordPress %s WooCommerce %s Comfino %s, PHP %s', 'comfino-payment-gateway'), $wp_version, WC_VERSION, Comfino_Payment_Gateway::VERSION, PHP_VERSION)) . '">pomoc@comfino.pl</a>', '887-106-027'
            ) . '</p>';

        echo '<nav class="nav-tab-wrapper woo-nav-tab-wrapper">';
        echo '<a href="' . home_url(add_query_arg($wp->request, ['subsection' => 'payment_settings'])) . '" class="nav-tab' . ($subsection === 'payment_settings' ? ' nav-tab-active' : '') . '">' . __('Payment settings', 'comfino-payment-gateway') . '</a>';
        echo '<a href="' . home_url(add_query_arg($wp->request, ['subsection' => 'sale_settings'])) . '" class="nav-tab' . ($subsection === 'sale_settings' ? ' nav-tab-active' : '') . '">' . __('Sale settings', 'comfino-payment-gateway') . '</a>';
        echo '<a href="' . home_url(add_query_arg($wp->request, ['subsection' => 'widget_settings'])) . '" class="nav-tab' . ($subsection === 'widget_settings' ? ' nav-tab-active' : '') . '">' . __('Widget settings', 'comfino-payment-gateway') . '</a>';
        echo '<a href="' . home_url(add_query_arg($wp->request, ['subsection' => 'abandoned_cart_settings'])) . '" class="nav-tab' . ($subsection === 'abandoned_cart_settings' ? ' nav-tab-active' : '') . '">' . __('Abandoned cart settings', 'comfino-payment-gateway') . '</a>';
        echo '<a href="' . home_url(add_query_arg($wp->request, ['subsection' => 'developer_settings'])) . '" class="nav-tab' . ($subsection === 'developer_settings' ? ' nav-tab-active' : '') . '">' . __('Developer settings', 'comfino-payment-gateway') . '</a>';
        echo '<a href="' . home_url(add_query_arg($wp->request, ['subsection' => 'plugin_diagnostics'])) . '" class="nav-tab' . ($subsection === 'plugin_diagnostics' ? ' nav-tab-active' : '') . '">' . __('Plugin diagnostics', 'comfino-payment-gateway') . '</a>';
        echo '</nav>';

        echo '<table class="form-table">';

        switch ($subsection) {
            case 'payment_settings':
            case 'sale_settings':
            case 'widget_settings':
            case 'abandoned_cart_settings':
            case 'developer_settings':
                echo $this->generate_settings_html($this->config_manager->get_form_fields($subsection));
                break;

            case 'plugin_diagnostics':
                $shop_info = sprintf(
                    'WooCommerce Comfino %s, WordPress %s, WooCommerce %s, PHP %s, web server %s, database %s',
                    \Comfino_Payment_Gateway::VERSION,
                    $wp_version,
                    WC_VERSION,
                    PHP_VERSION,
                    $_SERVER['SERVER_SOFTWARE'],
                    $wpdb->db_version()
                );

                echo '<tr valign="top"><th scope="row" class="titledesc"></th><td>' . $shop_info . '</td></tr>';
                echo '<tr valign="top"><th scope="row" class="titledesc"><label>' . __('Errors log', 'comfino-payment-gateway') . '</label></th>';
                echo '<td><textarea cols="20" rows="3" class="input-text wide-input" style="width: 800px; height: 400px">' . esc_textarea($errors_log) . '</textarea></td></tr>';
                break;
        }

        echo '</table>';
    }

    /**
     * Show offers.
     */
    public function payment_fields()
    {
        \Comfino\Core::init();

        $cart = WC()->cart;
        $total = $cart->get_total('');

        if (is_wc_endpoint_url('order-pay')) {
            $order = wc_get_order(absint(get_query_var('order-pay')));
            $total = $order->get_total('');
        }

        echo $this->prepare_paywall_iframe(
            (float) $total,
            $this->get_product_types_filter($cart, new Config_Manager()),
            Api_Client::$widget_key
        );
    }

    /**
     * Include JavaScript.
     */
    public function payment_scripts()
    {
        if ($this->enabled === 'no') {
            return;
        }

        wp_enqueue_script('comfino-paywall-frontend-script', Api_Client::get_paywall_frontend_script_url(), [], null);
        wp_enqueue_style('comfino-paywall-frontend-style', Api_Client::get_paywall_frontend_style_url(), [], null);
    }

    /**
     * Include JavaScript.
     */
    public function admin_scripts($hook)
    {
        if ($this->enabled === 'no') {
            return;
        }

        if ($hook === 'woocommerce_page_wc-settings') {
            wp_enqueue_script('prod_cat_tree', plugins_url('assets/js/tree.min.js', __FILE__), [], null);
        }
    }

    public function process_payment($order_id): array
    {
        \Comfino\Core::init();

        Api_Client::$api_language = substr(get_locale(), 0, 2);

        return Api_Client::process_payment(
            $order = wc_get_order($order_id),
            $this->get_return_url($order),
            Core::get_notify_url()
        );
    }

    public function cancel_order(string $order_id)
    {
        if (!$this->get_status_note($order_id, [Core::CANCELLED_BY_SHOP_STATUS, Core::RESIGN_STATUS])) {
            $order = wc_get_order($order_id);

            if (stripos($order->get_payment_method(), 'comfino') !== false) {
                // Process orders paid by Comfino only.
                Api_Client::cancel_order($order);

                $order->add_order_note(__("Send to Comfino canceled order", 'comfino-payment-gateway'));
            }
        }
    }

    /**
     * Webhook notifications - replaced with \Comfino\Core::process_notification(), left for backwards compatibility.
     */
    public function webhook()
    {
        $body = file_get_contents('php://input');

        $request = new WP_REST_Request('POST');
        $request->set_body($body);

        $response = Core::process_notification($request);

        if ($response->status === 400) {
            echo json_encode(['status' => $response->data, 'body' => $body, 'signature' => Core::get_signature()]);

            exit;
        }
    }

    /**
     * Show logo
     *
     * @return string
     */
    public function get_icon(): string
    {
        if (self::$show_logo) {
            $icon = '<img style="height: 18px; margin: 0 5px;" src="' . Api_Client::get_paywall_logo_url() . '" alt="Comfino Logo" />';
        } else {
            $icon = '';
        }

        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }

    public function generate_hidden_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $defaults = [
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => [],
        ];

        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?>
        <input class="input-text regular-input <?php echo esc_attr($data['class']); ?>" type="<?php echo esc_attr($data['type']); ?>" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr($this->get_option($key)); ?>" placeholder="<?php echo esc_attr($data['placeholder']); ?>" <?php disabled($data['disabled'], true); ?> <?php echo $this->get_custom_attribute_html($data); // WPCS: XSS ok. ?> />
        <?php

        return ob_get_clean();
    }

    public function generate_product_category_tree_html($key, $data)
    {
        $defaults = [
            'title' => '',
            'disabled' => false,
            'class' => '',
            'css' => '',
            'placeholder' => '',
            'type' => 'text',
            'desc_tip' => false,
            'description' => '',
            'custom_attributes' => [],
            'id' => '',
            'product_type' => '',
            'selected_categories' => [],
        ];

        $data = wp_parse_args($data, $defaults);

        ob_start();
        ?>
        <tr valign="top">
            <td class="forminp" colspan="2">
                <h3><?php echo esc_html($data['title']); ?></h3>
                <?php echo $this->render_category_tree($data['id'], $data['product_type'], $data['selected_categories']); ?>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    private function is_active_resign(\WC_Abstract_Order $order): bool
    {
        $date = new DateTime();
        $date->sub(new DateInterval('P14D'));

        if ($order->get_payment_method() === 'comfino' && $order->has_status(['processing', 'completed'])) {
            $notes = $this->get_status_note($order->ID, [Core::ACCEPTED_STATUS]);

            return !(isset($notes[Core::ACCEPTED_STATUS]) && $notes[Core::ACCEPTED_STATUS]->date_created->getTimestamp() < $date->getTimestamp());
        }

        return false;
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

    private function get_subsection(): string
    {
        $subsection = $_GET['subsection'] ?? 'payment_settings';

        if (!in_array($subsection, ['payment_settings', 'sale_settings', 'widget_settings', 'abandoned_cart_settings', 'developer_settings', 'plugin_diagnostics'], true)) {
            $subsection = 'payment_settings';
        }

        return $subsection;
    }

    /**
     * @param int[] $selected_categories
     */
    private function render_category_tree(string $tree_id, string $product_type, array $selected_categories): string
    {
        $tree_nodes = json_encode($this->config_manager->build_categories_tree($selected_categories));
        $close_depth = 3;

        return trim('
<div id="' . $tree_id . '_' . $product_type . '"></div>
<input id="' . $tree_id . '_' . $product_type . '_input" name="' . $tree_id . '[' . $product_type . ']" type="hidden" />
<script>
    new Tree(
        \'#' . $tree_id . '_' . $product_type . '\',
        {
            data: ' . $tree_nodes . ',
            closeDepth: ' . $close_depth . ',
            onChange: function () {
                document.getElementById(\'' . $tree_id . '_' . $product_type . '_input\').value = this.values.join();
            }
        }
    );
</script>');
    }

    public function change_order($order_id, $status_old, $status_new)
    {
        $order = wc_get_order($order_id);

        if ($this->enabled === 'yes' && $this->abandoned_cart_enabled === 'yes' && $order->get_payment_method() !== 'comfino') {
            if ($status_new == 'failed' && in_array($status_old, ['on-hold', 'pending'])) {

                $this->send_email($order);
                Api_Client::abandoned_cart('send-mail');
            }
        }
    }

    function send_email($order)
    {
        $mailer = WC()->mailer();

        $recipient = $order->get_billing_email();

        $subject = __("Order reminder", 'comfino-payment-gateway');
        $content = $this->get_email_html($order, $recipient);
        $headers = "Content-Type: text/html\r\n";

        $mailer->send($recipient, $subject, $content, $headers);
    }

    function get_email_html($order, $email, $heading = false)
    {
        $path = explode('/', dirname(__DIR__));

        $template = '../../'. $path[count($path) - 1]. '/includes/templates/emails/failed-order.php';

        return wc_get_template_html($template, [
            'order' => $order,
            'email_heading' => $heading,
            'sent_to_admin' => false,
            'plain_text' => false,
            'email' => $email,
            'additional_content' => false,
        ]);
    }

    function filter_gateways($gateways)
    {
        if (is_wc_endpoint_url('order-pay')) {
            $order = wc_get_order(absint(get_query_var('order-pay')));

            if (is_a($order, 'WC_Order') && $order->has_status('failed')) {
                if ($this->abandoned_payments == 'comfino') {
                    foreach ($gateways as $name => $gateway) {
                        if ($name != 'comfino') {
                            unset($gateways[$name]);
                        }
                    }
                } else {
                    foreach ($gateways as $name => $gateway) {
                        if ($name != 'comfino') {
                            $gateways[$name]->chosen = false;
                        } else {
                            $gateways[$name]->chosen = true;
                        }
                    }
                }
            }
        }

        return $gateways;
    }

    /**
     * @param WC_Cart $cart
     * @param Config_Manager $config_manager
     * @return array|null
     */
    private function get_product_types_filter($cart, $config_manager)
    {
        if (empty($config_manager->get_product_category_filters())) {
            // Product category filters not set.
            return null;
        }

        $available_product_types = array_keys($config_manager->get_offer_types('paywall'));
        $filtered_product_types = [];

        // Check product category filters.
        foreach ($available_product_types as $product_type) {
            if ($config_manager->is_financial_product_available($product_type, $cart->get_cart())) {
                $filtered_product_types[] = $product_type;
            }
        }

        return $filtered_product_types;
    }

    /**
     * @param float $total
     * @return array
     */
    private function get_paywall_options($total)
    {
        return [
            'platform' => 'woocommerce',
            'platformName' => 'WooCommerce',
            'platformVersion' => WC_VERSION,
            'platformDomain' => Core::get_shop_domain(),
            'pluginVersion' => \Comfino_Payment_Gateway::VERSION,
            'offersURL' => Core::get_offers_url() . "/$total",
            'language' => substr(get_locale(), 0, 2),
            'currency' => get_woocommerce_currency(),
            'cartTotal' => (float)$total,
            'cartTotalFormatted' => wc_price($total, ['currency' => get_woocommerce_currency()]),
        ];
    }

    /**
     * @param float $total
     * @param array|null $product_types_filter
     * @param string $widget_key
     * @return string
     */
    private function prepare_paywall_iframe($total, $product_types_filter, $widget_key): string
    {
        if (is_array($product_types_filter)) {
            if (count($product_types_filter)) {
                $product_types = implode(',', $product_types_filter);
                $product_types_length = strlen($product_types);
            } else {
                $product_types = "\0";
                $product_types_length = 1;
            }
        } else {
            $product_types = '';
            $product_types_length = 0;
        }

        $loan_amount = (int) ($total * 100);

        $request_data = $loan_amount . $product_types . $widget_key;
        $request_params = pack('V', $loan_amount) . pack('v', $product_types_length) . $product_types . $widget_key;

        $hash = hash_hmac('sha3-256', $request_data, Api_Client::get_api_key(), true);
        $auth = urlencode(base64_encode($request_params . $hash));

        $paywall_options = $this->get_paywall_options($total);
        $paywall_api_url = Api_Client::get_paywall_api_host() . '/v1/paywall?auth=' . $auth;

        $iframe_template = '
<iframe id="comfino-paywall-container" src="' . $paywall_api_url . '" referrerpolicy="strict-origin" loading="lazy" class="comfino-paywall" scrolling="no" onload="ComfinoPaywallFrontend.onload(this, \'' . $paywall_options['platformName'] . '\', \'' . $paywall_options['platformVersion'] . '\')"></iframe>
<input id="comfino-loan-term" name="comfino_loan_term" type="hidden" />
<input id="comfino-type" name="comfino_type" type="hidden" />
<script>
    if (ComfinoPaywallFrontend.isInitialized()) {
        Comfino.init();
    } else {
        window.Comfino = {
            paywallOptions: ' . json_encode($paywall_options) . ',
            init: () => {
                ComfinoPaywallFrontend.init(
                    document.getElementById(\'payment_method_comfino\'),
                    document.getElementById(\'comfino-paywall-container\'),
                    Comfino.paywallOptions
                );
            }
        }

        Comfino.paywallOptions.onUpdateOrderPaymentState = (loanParams) => {
            ComfinoPaywallFrontend.logEvent(\'updateOrderPaymentState WooCommerce\', \'debug\', loanParams);

            if (loanParams.loanTerm !== 0) {
                document.getElementById(\'comfino-type\').value = loanParams.loanType;
                document.getElementById(\'comfino-loan-term\').value = loanParams.loanTerm;
            }
        }

        if (document.readyState === \'complete\') {
            Comfino.init();
        } else {
            document.addEventListener(\'readystatechange\', () => {
                if (document.readyState === \'complete\') {
                    Comfino.init();
                }
            });
        }
    }
</script>';

        return trim($iframe_template);
    }
}
