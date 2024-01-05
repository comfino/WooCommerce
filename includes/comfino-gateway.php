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

        if ($this->get_option('sandbox_mode') === 'yes') {
            Api_Client::$host = Core::COMFINO_SANDBOX_HOST;
            Api_Client::$key = $this->get_option('sandbox_key');
        } else {
            Api_Client::$host = Core::COMFINO_PRODUCTION_HOST;
            Api_Client::$key = $this->get_option('production_key');
        }

        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_api_comfino_gateway', [$this, 'webhook']);
        add_action('woocommerce_order_status_cancelled', [$this, 'cancel_order']);
        add_action('woocommerce_order_item_add_action_buttons', [$this, 'order_buttons'], 10, 1);
        add_action('save_post', [$this, 'update_order'], 10, 3);
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
        echo '<a href="' . home_url(add_query_arg($wp->request, ['subsection' => 'widget_settings'])) . '" class="nav-tab' . ($subsection === 'widget_settings' ? ' nav-tab-active' : '') . '">' . __('Widget settings', 'comfino-payment-gateway') . '</a>';
        echo '<a href="' . home_url(add_query_arg($wp->request, ['subsection' => 'abandoned_cart_settings'])) . '" class="nav-tab' . ($subsection === 'abandoned_cart_settings' ? ' nav-tab-active' : '') . '">' . __('Abandoned Cart settings', 'comfino-payment-gateway') . '</a>';
        echo '<a href="' . home_url(add_query_arg($wp->request, ['subsection' => 'developer_settings'])) . '" class="nav-tab' . ($subsection === 'developer_settings' ? ' nav-tab-active' : '') . '">' . __('Developer settings', 'comfino-payment-gateway') . '</a>';
        echo '<a href="' . home_url(add_query_arg($wp->request, ['subsection' => 'plugin_diagnostics'])) . '" class="nav-tab' . ($subsection === 'plugin_diagnostics' ? ' nav-tab-active' : '') . '">' . __('Plugin diagnostics', 'comfino-payment-gateway') . '</a>';
        echo '</nav>';

        echo '<table class="form-table">';

        switch ($subsection) {
            case 'payment_settings':
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
        global $woocommerce;

        \Comfino\Core::init();

        $total = $woocommerce->cart->get_total('');

        if (is_wc_endpoint_url('order-pay')) {
            $order = wc_get_order(absint(get_query_var('order-pay')));
            $total = $order->get_total('');
        }

        $options = [
            'platform' => 'woocommerce',
            'platformVersion' => WC_VERSION,
            'platformDomain' => Core::get_shop_domain(),
            'pluginVersion' => \Comfino_Payment_Gateway::VERSION,
            'offersURL' => Core::get_offers_url() . '?total=' . $total,
            'language' => substr(get_locale(), 0, 2),
            'currency' => get_woocommerce_currency(),
            'cartTotal' => (float)$total,
            'cartTotalFormatted' => wc_price($total, ['currency' => get_woocommerce_currency()]),
        ];

        echo '
        <div id="comfino-payment-container">
            <input id="comfino-loan-term" name="comfino_loan_term" type="hidden" />
            <input id="comfino-type" name="comfino_type" type="hidden" />
            <div id="comfino-payment-subcontainer" style="display: none"></div>            
            <script>
                Comfino.options = ' . json_encode($options) . ';
                Comfino.options.frontendInitElement = document.getElementById(\'payment_method_comfino\');
                Comfino.options.frontendTargetElement = document.getElementById(\'comfino-payment-subcontainer\');                
                Comfino.init(\'' . Api_Client::get_frontend_script_url() . '\');
            </script>
        </div>
        ';
    }

    /**
     * Include JavaScript.
     */
    public function payment_scripts()
    {
        if ($this->enabled === 'no') {
            return;
        }

        wp_enqueue_script('comfino', plugins_url('assets/js/comfino.js', __FILE__), [], null);
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
                    Api_Client::resign_order($order->ID);
                }
            }
        }
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

        if (!in_array($subsection, ['payment_settings', 'widget_settings', 'abandoned_cart_settings', 'developer_settings', 'plugin_diagnostics'], true)) {
            $subsection = 'payment_settings';
        }

        return $subsection;
    }

    public function change_order($order_id, $status_old, $status_new): void
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
}
