<?php

namespace Comfino\View\Block;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class PaymentGateway extends AbstractPaymentMethodType
{
    /**
     * The gateway instance.
     *
     * @var \Comfino\PaymentGateway
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
        /** @var \Comfino_Payment_Gateway $comfino_payment_gateway */
        global $comfino_payment_gateway;

        $scriptPath = '/assets/js/frontend/blocks.js';
        $scriptAssetPath = $comfino_payment_gateway->plugin_abspath() . 'assets/js/frontend/blocks.asset.php';
        $scriptAsset = is_readable($scriptAssetPath)
            ? require $scriptAssetPath
            : ['dependencies' => [], 'version' => \Comfino\PaymentGateway::VERSION];
        $scriptUrl = $comfino_payment_gateway->plugin_url() . $scriptPath;

        wp_register_script(
            'comfino-payment-gateway-blocks',
            $scriptUrl,
            $scriptAsset['dependencies'],
            $scriptAsset['version'],
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(
                'comfino-payment-gateway-blocks',
                'comfino-payment-gateway',
                $comfino_payment_gateway->plugin_abspath() . 'languages/'
            );
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
