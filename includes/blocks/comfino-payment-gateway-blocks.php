<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Comfino_Payment_Gateway_Blocks_Support extends AbstractPaymentMethodType
{
    /**
     * The gateway instance.
     *
     * @var Comfino_Gateway
     */
    private $gateway;

    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'comfino';

    /**
     * Initializes the payment method type.
     */
    public function initialize(): void
    {
        $this->settings = get_option('woocommerce_comfino_settings', []);
        $gateways = WC()->payment_gateways()->payment_gateways;
        $this->gateway = $gateways[$this->name];
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     */
    public function is_active(): bool
    {
        return $this->gateway->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     */
    public function get_payment_method_script_handles(): array
    {
        $script_path = '/assets/js/frontend/blocks.js';
        $script_asset_path = Comfino_Payment_Gateway::plugin_abspath() . 'assets/js/frontend/blocks.asset.php';
        $script_asset = is_readable($script_asset_path)
            ? require $script_asset_path
            : ['dependencies' => [], 'version' => Comfino_Payment_Gateway::VERSION];
        $script_url = Comfino_Payment_Gateway::plugin_url() . $script_path;

        wp_register_script(
            'comfino-payment-gateway-blocks',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        if ( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations( 'wc-dummy-payments-blocks', 'woocommerce-gateway-dummy', WC_Dummy_Payments::plugin_abspath() . 'languages/' );
        }

        return ['comfino-payment-gateway-blocks'];
    }

    /**
     * Returns an array of key => value pairs of data made available to the payment methods script.
     */
    public function get_payment_method_data(): array
    {
        return [
            'title' => $this->get_setting('title'),
            'description' => $this->get_setting('description'),
            'supports' => array_filter($this->gateway->supports, [$this->gateway, 'supports']),
        ];
    }
}
