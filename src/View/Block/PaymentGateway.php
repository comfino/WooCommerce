<?php

namespace Comfino\View\Block;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Comfino\Configuration\ConfigManager;
use Comfino\Configuration\SettingsManager;
use Comfino\DebugLogger;
use Comfino\ErrorLogger;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Main;
use Comfino\Order\OrderManager;
use Comfino\View\FrontendManager;
use Comfino\View\PaywallCartSerializer;

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

        if (count($scriptIds) > 0 || !is_checkout()) {
            return $scriptIds;
        }

        /** @var \Comfino_Payment_Gateway $comfino_payment_gateway */
        global $comfino_payment_gateway;

        FrontendManager::includeLocalStyles(['comfino-item-gate.css']);

        $scriptIds = FrontendManager::registerLocalScripts(
            ['comfino-blocks.js'],
            [
                'comfino-blocks.js' => [
                    'wc-blocks-registry',
                    'wc-settings',
                    'wp-element',
                    'wp-html-entities',
                    'wp-i18n',
                ],
            ]
        );

        DebugLogger::logEvent(
            '[PAYWALL]', 'get_payment_method_script_handles registered scripts.',
            ['$scriptIds' => $scriptIds]
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
        $wcCart = WC()->cart;
        $authToken = FrontendManager::getAuthToken();

        $allowedProductTypes = null;
        $shopCart = null;

        if ($wcCart !== null) {
            try {
                $shopCart = OrderManager::getShopCart($wcCart);
                $allowedProductTypes = SettingsManager::getAllowedProductTypes(
                    ProductTypesListTypeEnum::LIST_TYPE_PAYWALL,
                    $shopCart
                );
            } catch (\Throwable $e) {
                ErrorLogger::sendError($e, 'getAllowedProductTypes', (string) $e->getCode(), $e->getMessage());
            }
        }

        $loanAmount = $shopCart !== null ? $shopCart->getTotalAmount() : ($wcCart !== null ? (int) round($wcCart->get_total('edit') * 100) : 0);

        $cartPayload = null;

        if ($shopCart !== null) {
            try {
                $cartPayload = PaywallCartSerializer::toArray($shopCart);
            } catch (\Throwable $e) {
                ErrorLogger::sendError($e, 'serializeShopCart', (string) $e->getCode(), $e->getMessage());
            }
        }

        return [
            'authToken' => $authToken,
            'loanAmount' => $loanAmount,
            'environment' => ConfigManager::isSandboxMode() ? 'sandbox' : 'production',
            'sdkScriptUrl' => ConfigManager::getSdkScriptUrl(),
            'paymentMethodAuth' => ConfigManager::getPaywallLogoAuthHash(),
            'supports' => $this->gateway ? array_filter($this->gateway->supports, [$this->gateway, 'supports']) : ['products'],
            'productTypes' => $allowedProductTypes !== null ? array_map('strval', $allowedProductTypes) : null,
            'productTypeNames' => SettingsManager::getProductTypes(ProductTypesListTypeEnum::LIST_TYPE_PAYWALL) ?: null,
            'cart' => $cartPayload,
            'paywallSettings' => [
                'language' => Main::getShopLanguage(),
                'currency' => Main::getShopCurrency(),
                'customPaywallCss' => ConfigManager::getConfigurationValue('COMFINO_PAYWALL_CUSTOM_CSS_URL') ?: null,
            ],
            /* Browser-safe shop environment payload — mirrors the shape produced by
               AbstractShopEnvironmentBuilder::buildForFrontend() in php-sdk. Replaces the deprecated
               `shopInfo` field. Kept in sync with the legacy-checkout payload built in Main::renderPaywallIframe();
               candidate for refactor into a shared WooCommerceShopEnvironmentBuilder. */
            'shopEnvironment' => [
                'platform' => 'woocommerce',
                'platformName' => 'WooCommerce',
                'platformDomain' => Main::getShopDomain(),
                'theme' => ['family' => 'woocommerce'],
                'language' => Main::getShopLanguage(),
                'currency' => Main::getShopCurrency(),
                'pageContext' => ['type' => 'checkout'],
            ],
            'directRedirect' => (bool) ConfigManager::getConfigurationValue('COMFINO_PAYWALL_DIRECT_REDIRECT'),
            'creditors' => SettingsManager::getCreditors() ?: null,
            'allowedProductsConfig' => SettingsManager::getAllowedProductsConfigForFrontend(),
            'scriptNonce' => (string) apply_filters('comfino_csp_script_nonce', ''),
        ];
    }
}
