<?php

namespace Comfino;

use Comfino\Configuration\ConfigManager;
use Comfino\Order\ShopStatusManager;

class PaymentGateway extends \WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'comfino';
        $this->icon = $this->get_icon();
        $this->has_fields = true;
        $this->method_title = __('Comfino payments', 'comfino-payment-gateway');
        $this->method_description = __(
            'Comfino is an innovative payment method for customers of e-commerce stores! ' .
            'These are installment payments, deferred (buy now, pay later) and corporate ' .
            'payments available on one platform with the help of quick integration. Grow your business with Comfino!',
            'comfino-payment-gateway'
        );

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
        //add_action('woocommerce_api_comfino_gateway', [$this, 'webhook']);
        add_action('woocommerce_order_status_cancelled', [ShopStatusManager::class, 'orderStatusCancelEventHandler']);
        add_action('woocommerce_order_status_changed', [$this, 'order_status_changed'], 10, 3);

        add_filter(
            'woocommerce_available_payment_gateways',
            static function (array $gateways): array {
                if (is_wc_endpoint_url('order-pay')) {
                    $order = wc_get_order(absint(get_query_var('order-pay')));

                    if ($order instanceof \WC_Order && $order->has_status('failed')) {
                        if (ConfigManager::getConfigurationValue('COMFINO_ABANDONED_PAYMENTS') === 'comfino') {
                            foreach ($gateways as $name => $gateway) {
                                if ($name !== 'comfino') {
                                    unset($gateways[$name]);
                                }
                            }
                        } else {
                            foreach ($gateways as $name => $gateway) {
                                if ($name !== 'comfino') {
                                    $gateway->chosen = false;
                                } else {
                                    $gateway->chosen = true;
                                }
                            }
                        }
                    }
                }

                return $gateways;
            },
            1
        );
    }
}
