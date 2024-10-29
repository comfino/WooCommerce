<?php

namespace Comfino\View\Block;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Comfino\Api\ApiClient;
use Comfino\Configuration\ConfigManager;
use Comfino\Main;

final class PaymentGateway extends AbstractPaymentMethodType
{
    /**
     * The gateway instance.
     *
     * @var \Comfino\PaymentGateway
     */
    private $gateway;

    public function __construct()
    {
        $this->name = \Comfino\PaymentGateway::GATEWAY_ID;
    }

    /**
     * Initializes the payment method type.
     */
    public function initialize(): void
    {
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     */
    public function is_active(): bool
    {
        foreach (WC()->payment_gateways()->payment_gateways as $gateway) {
            if ($gateway instanceof \Comfino\PaymentGateway) {
                $this->gateway = $gateway;
                $this->settings = $gateway->settings;

                break;
            }
        }

        return $this->gateway !== null && $this->gateway->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     */
    public function get_payment_method_script_handles(): array
    {
        /** @var \Comfino_Payment_Gateway $comfino_payment_gateway */
        global $comfino_payment_gateway;

        wp_register_script(
            'comfino-payment-gateway-blocks',
            $comfino_payment_gateway->plugin_url() . '/resources/js/front/paywall-block.min.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
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
            'title' => ConfigManager::getConfigurationValue('COMFINO_PAYMENT_TEXT'),
            'description' => $this->gateway->get_description(),
            'icon' => ConfigManager::getConfigurationValue('COMFINO_SHOW_LOGO') ? ApiClient::getPaywallLogoUrl() : '',
            'iframe' => $this->is_active() ? $this->gateway->generate_paywall_iframe(true) : '',
            'paywallOptions' => array_merge(
                Main::getPaywallOptions($this->gateway->get_total()),
                ['wcBlocks' => true, 'attachClickHandler' => false]
            ),
            'supports' => array_filter($this->gateway->supports, [$this->gateway, 'supports']),
        ];
    }
}
