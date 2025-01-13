<?php

namespace Comfino\View\Block;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Comfino\Api\ApiService;
use Comfino\Configuration\ConfigManager;
use Comfino\DebugLogger;
use Comfino\Main;
use Comfino\View\FrontendManager;

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
        static $scriptIds = [];

        if (count($scriptIds) > 0) {
            return $scriptIds;
        }

        /** @var \Comfino_Payment_Gateway $comfino_payment_gateway */
        global $comfino_payment_gateway;

        $iframeRenderer = FrontendManager::getPaywallIframeRenderer();

        $styleIds = FrontendManager::includeExternalStyles($iframeRenderer->getStyles());
        $scriptIds = FrontendManager::registerExternalScripts($iframeRenderer->getScripts());

        $scriptIds = array_merge($scriptIds, FrontendManager::registerLocalScripts(
            ['paywall-block.js'],
            [
                'paywall-block.js' => array_merge(
                    [
                        'wc-blocks-registry',
                        'wc-settings',
                        'wp-element',
                        'wp-html-entities',
                        'wp-i18n',
                    ],
                    $scriptIds
                )
            ],
            true,
            true
        ));

        DebugLogger::logEvent(
            '[PAYWALL]', 'get_payment_method_script_handles registered styles and scripts.',
            ['$styleIds' => $styleIds, '$scriptIds' => $scriptIds]
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations(
                $scriptIds[0],
                'comfino-payment-gateway',
                $comfino_payment_gateway->plugin_abspath() . 'languages/'
            );
        }

        return $scriptIds;
    }

    /**
     * Returns an array of key => value pairs of data made available to the payment methods script.
     */
    public function get_payment_method_data(): array
    {
        return [
            'title' => ConfigManager::getConfigurationValue('COMFINO_PAYMENT_TEXT'),
            'description' => $this->gateway->get_description(),
            'icon' => ConfigManager::getConfigurationValue('COMFINO_SHOW_LOGO') ? ConfigManager::getPaywallLogoUrl() : '',
            'iframeTemplate' => $this->is_active() ? $this->gateway->generatePaywallIframe(true) : '',
            'paywallUrl' => ApiService::getEndpointUrl('paywall'),
            'paywallOptions' => array_merge(
                Main::getPaywallOptions($this->gateway->getTotal()),
                ['wcBlocks' => true, 'attachClickHandler' => false]
            ),
            'supports' => array_filter($this->gateway->supports, [$this->gateway, 'supports']),
        ];
    }
}
