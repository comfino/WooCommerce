<?php

namespace Comfino;

use Comfino\Api\ApiClient;
use Comfino\Api\ApiService;
use Comfino\Configuration\ConfigManager;
use Comfino\Configuration\SettingsManager;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Order\OrderManager;
use Comfino\Order\ShopStatusManager;
use Comfino\View\FrontendManager;
use Comfino\View\SettingsForm;
use Comfino\View\TemplateManager;

if (!defined('ABSPATH')) {
    exit;
}

final class Main
{
    private const MIN_PHP_VERSION_ID = 70100;
    private const MIN_PHP_VERSION = '7.1.0';
    private const MIN_WC_VERSION = '3.0.0';

    /** @var string */
    private static $pluginDirectory;
    /** @var string */
    private static $pluginFile;
    /** @var Common\Backend\ErrorLogger */
    private static $errorLogger;
    /** @var string */
    private static $debugLogFilePath;

    public static function init(\Comfino_Payment_Gateway $module, string $pluginDirectory, string $pluginFile): void
    {
        add_filter('woocommerce_payment_gateways', [$module, 'add_gateway']);
        add_filter('plugin_action_links_' . plugin_basename($pluginFile), [$module, 'plugin_action_links']);
        add_filter('wc_order_statuses', [$module, 'filter_order_status']);

        add_action('wp_loaded', [$module, 'comfino_rest_load_cart'], 5);
        add_action('wp_head', [$module, 'render_widget']);

        // Register module API endpoints.
        add_action('rest_api_init', [ApiService::class, 'init']);

        load_plugin_textdomain('comfino-payment-gateway', false, basename($pluginDirectory) . '/languages');

        self::$pluginDirectory = $pluginDirectory;
        self::$pluginFile = $pluginFile;
        self::$errorLogger = ErrorLogger::getLoggerInstance($pluginFile);
        self::$debugLogFilePath = "$pluginDirectory/var/log/debug.log";

        // Initialize cache system.
        CacheManager::init($pluginDirectory);
    }

    public static function install(\Comfino_Payment_Gateway $module): bool
    {
        ErrorLogger::init($module);

        ConfigManager::initConfigurationValues();
        ShopStatusManager::addCustomOrderStatuses();

        if (!COMFINO_PS_17) {
            $module->registerHook('payment');
            $module->registerHook('displayPaymentEU');
        }

        $module->registerHook('paymentOptions');
        $module->registerHook('paymentReturn');
        $module->registerHook('displayBackofficeComfinoForm');
        $module->registerHook('actionOrderStatusPostUpdate');
        $module->registerHook('actionValidateCustomerAddressForm');
        $module->registerHook('header');
        $module->registerHook('actionAdminControllerSetMedia');

        return true;
    }

    public static function uninstall(\PaymentModule $module): bool
    {
        ConfigManager::deleteConfigurationValues();

        if (!COMFINO_PS_17) {
            $module->unregisterHook('payment');
            $module->unregisterHook('displayPaymentEU');
        }

        $module->unregisterHook('paymentOptions');
        $module->unregisterHook('paymentReturn');
        $module->unregisterHook('displayBackofficeComfinoForm');
        $module->unregisterHook('actionOrderStatusPostUpdate');
        $module->unregisterHook('actionValidateCustomerAddressForm');
        $module->unregisterHook('header');
        $module->unregisterHook('actionAdminControllerSetMedia');

        ErrorLogger::init($module);
        ApiClient::getInstance()->notifyPluginRemoval();

        return true;
    }

    /**
     * Renders configuration form.
     */
    public static function getContent(\PaymentModule $module): string
    {
        return TemplateManager::renderModuleView($module, 'configuration', 'admin', SettingsForm::processForm($module));
    }

    /**
     * @return \PrestaShop\PrestaShop\Core\Payment\PaymentOption[]|string|void
     */
    public static function renderPaywallIframe(\PaymentModule $module, array $params)
    {
        /** @var \Cart $cart */
        $cart = $params['cart'];

        if (!self::paymentIsAvailable($module, $cart)
            || ($paywallIframe = self::preparePaywallIframe($module, $cart)) === null
        ) {
            return;
        }

        if (COMFINO_PS_17) {
            $comfinoPaymentOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $comfinoPaymentOption->setModuleName($module->name)
                ->setAction(ApiService::getControllerUrl($module, 'payment'))
                ->setCallToActionText(ConfigManager::getConfigurationValue('COMFINO_PAYMENT_TEXT'))
                ->setLogo(ApiClient::getPaywallLogoUrl($module))
                ->setAdditionalInformation($paywallIframe);

            return [$comfinoPaymentOption];
        }

        return $paywallIframe;
    }

    public static function processFinishedPaymentTransaction(\PaymentModule $module, array $params): string
    {
        if (!COMFINO_PS_17 || !$module->active) {
            return '';
        }

        ErrorLogger::init($module);

        if (in_array($params['order']->getCurrentState(), [
            (int) \Configuration::get('COMFINO_CREATED'),
            (int) \Configuration::get('PS_OS_OUTOFSTOCK'),
            (int) \Configuration::get('PS_OS_OUTOFSTOCK_UNPAID'),
        ], true)) {
            $tplVariables = [
                'shop_name' => \Context::getContext()->shop->name,
                'status' => 'ok',
                'id_order' => $params['order']->id,
            ];

            if (isset($params['order']->reference) && !empty($params['order']->reference)) {
                $tplVariables['reference'] = $params['order']->reference;
            }
        } else {
            $tplVariables['status'] = 'failed';
        }

        return TemplateManager::renderModuleView($module, 'payment_return', 'front', $tplVariables);
    }

    private static function paymentIsAvailable(\PaymentModule $module, \WC_Cart $cart): bool
    {
        if (!$module->active || !OrderManager::checkCartCurrency($module, $cart) || empty(ConfigManager::getApiKey())) {
            return false;
        }

        ErrorLogger::init($module);

        return SettingsManager::getAllowedProductTypes(
            ProductTypesListTypeEnum::LIST_TYPE_PAYWALL,
            OrderManager::getShopCart($cart, (int) \Context::getContext()->cookie->loan_amount)
        ) !== [];
    }

    private static function preparePaywallIframe(\PaymentModule $module, \Cart $cart): ?string
    {
        $total = $cart->getOrderTotal();
        $tools = new Tools(\Context::getContext());

        try {
            return TemplateManager::renderModuleView(
                $module,
                'payment',
                'front',
                [
                    'paywall_iframe' => FrontendManager::getPaywallIframeRenderer($module)
                        ->renderPaywallIframe(ApiService::getControllerUrl($module, 'paywall')),
                    'payment_state_url' => ApiService::getControllerUrl($module, 'paymentstate', [], false),
                    'paywall_options' => [
                        'platform' => 'prestashop',
                        'platformName' => 'PrestaShop',
                        'platformVersion' => _PS_VERSION_,
                        'platformDomain' => \Tools::getShopDomain(),
                        'pluginVersion' => COMFINO_VERSION,
                        'language' => $tools->getLanguageIsoCode($cart->id_lang),
                        'currency' => $tools->getCurrencyIsoCode($cart->id_currency),
                        'cartTotal' => (float) $total,
                        'cartTotalFormatted' => $tools->formatPrice($total, $cart->id_currency),
                    ],
                    'is_ps_16' => !COMFINO_PS_17,
                    'comfino_logo_url' => ApiClient::getPaywallLogoUrl($module),
                    'comfino_label' => ConfigManager::getConfigurationValue('COMFINO_PAYMENT_TEXT'),
                    'comfino_redirect_url' => ApiService::getControllerUrl($module, 'payment'),
                ]
            );
        } catch (\Throwable $e) {
            ApiClient::processApiError('Paywall error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)', $e);
        }

        return null;
    }

//-----------------------------------------------------------
    public static function getPluginDirectory(): string
    {
        return self::$pluginDirectory;
    }

    public static function getPluginFile(): string
    {
        return self::$pluginFile;
    }

    public static function getShopDomain(): string
    {
        return parse_url(wc_get_page_permalink('shop'), PHP_URL_HOST);
    }

    public static function getShopUrl(): string
    {
        $urlParts = parse_url(wc_get_page_permalink('shop'));

        return $urlParts['host'] . (isset($urlParts['port']) ? ':' . $urlParts['port'] : '');
    }

    /**
     * Checks the environment compatibility with Comfino plugin requirements.
     * Returns a string with the first incompatibility found or false if the environment has no problems.
     *
     * @return string|bool
     */
    public static function getEnvironmentWarning(bool $duringActivation = false)
    {
        if (PHP_VERSION_ID < self::MIN_PHP_VERSION_ID) {
            return $duringActivation
                ? sprintf(__(
                    'The plugin could not be activated. The minimum PHP version required for Comfino is %s. You are running %s.',
                    'comfino-payment-gateway'
                ), self::MIN_PHP_VERSION, PHP_VERSION)
                : sprintf(__(
                    'The Comfino plugin has been deactivated. The minimum PHP version required for Comfino is %s. You are running %s.',
                    'comfino-payment-gateway'
                ), self::MIN_PHP_VERSION, PHP_VERSION);
        }

        if (!defined('WC_VERSION')) {
            return $duringActivation
                ? __('The plugin could not be activated. WooCommerce needs to be activated.', 'comfino-payment-gateway')
                : __('The Comfino plugin has been deactivated. WooCommerce needs to be activated.', 'comfino-payment-gateway');
        }

        if (version_compare(WC_VERSION, self::MIN_WC_VERSION, '<')) {
            return $duringActivation
                ? sprintf(__(
                    'The plugin could not be activated. The minimum WooCommerce version required for Comfino is %s. You are running %s.',
                    'comfino-payment-gateway'
                ), self::MIN_WC_VERSION, WC_VERSION)
                : sprintf(__(
                    'The Comfino plugin has been deactivated. The minimum WooCommerce version required for Comfino is %s. You are running %s.',
                    'comfino-payment-gateway'
                ), self::MIN_WC_VERSION, WC_VERSION);
        }

        if (!extension_loaded('curl')) {
            return $duringActivation
                ? __('The plugin could not be activated. cURL is not installed.', 'comfino-payment-gateway')
                : __('The Comfino plugin has been deactivated. cURL is not installed.', 'comfino-payment-gateway');
        }

        return false;
    }
}
