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
        self::$pluginDirectory = $pluginDirectory;
        self::$pluginFile = $pluginFile;
        self::$errorLogger = ErrorLogger::getLoggerInstance($pluginFile);
        self::$debugLogFilePath = "$pluginDirectory/var/log/debug.log";

        ErrorLogger::init($pluginDirectory, $pluginFile);

        /*
         * Loads the cart, session and notices should it be required.
         *
         * Workaround for WC bug:
         * https://github.com/woocommerce/woocommerce/issues/27160
         * https://github.com/woocommerce/woocommerce/issues/27157
         * https://github.com/woocommerce/woocommerce/issues/23792
         *
         * Note: Only needed should the site be running WooCommerce 3.6 or higher as they are not included during a REST request.
         *
         * @see https://plugins.trac.wordpress.org/browser/cart-rest-api-for-woocommerce/trunk/includes/class-cocart-init.php#L145
         * @since 2.0.0
         * @version 2.0.3
         */
        add_action('wp_loaded', static function (): void {
            if (version_compare(WC_VERSION, '3.6.0', '>=') && WC()->is_rest_api_request()) {
                if (empty($_SERVER['REQUEST_URI'])) {
                    return;
                }

                $restPrefix = 'comfino/offers';
                $requestUri = esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']));

                if (strpos($requestUri, $restPrefix) === false) {
                    return;
                }

                require_once WC_ABSPATH . 'includes/wc-cart-functions.php';
                require_once WC_ABSPATH . 'includes/wc-notice-functions.php';

                if (WC()->session === null) {
                    $sessionClass = apply_filters('woocommerce_session_handler', 'WC_Session_Handler'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

                    // Prefix session class with global namespace if not already namespaced.
                    if (strpos($sessionClass, '\\') === false) {
                        $sessionClass = '\\' . $sessionClass;
                    }

                    WC()->session = new $sessionClass();
                    WC()->session->init();
                }

                // For logged in customers, pull data from their account rather than the session which may contain incomplete data.
                if (WC()->customer === null) {
                    try {
                        if (is_user_logged_in()) {
                            WC()->customer = new \WC_Customer(get_current_user_id());
                        } else {
                            WC()->customer = new \WC_Customer(get_current_user_id(), true);
                        }

                        // Customer should be saved during shutdown.
                        add_action('shutdown', [WC()->customer, 'save'], 10);
                    } catch (\Exception $e) {
                        ErrorLogger::logError('wp_loaded:comfino_rest_load_cart', $e->getMessage());
                    }
                }

                // Load cart.
                if (WC()->cart === null) {
                    WC()->cart = new \WC_Cart();
                }
            }
        }, 5);

        add_action('wp_head', [$module, 'render_widget']);

        // Register module API endpoints.
        add_action('rest_api_init', [ApiService::class, 'init']);

        // Declare compatibility with WooCommerce HPOS.
        add_action('before_woocommerce_init', static function () {
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__);
            }
        });

        // Register WooCommerce Blocks integration.
        add_action('woocommerce_blocks_loaded', static function () {
            if (class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
                add_action(
                    'woocommerce_blocks_payment_method_type_registration',
                    static function (\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $paymentMethodRegistry) {
                        $paymentMethodRegistry->register(new View\Block\PaymentGateway());
                    }
                );
            }
        });

        // Add a Comfino gateway to the WooCommerce payment methods available for customer.
        add_filter('woocommerce_payment_gateways', static function (array $methods): array {
            $methods[] = 'Comfino_Gateway';

            return $methods;
        });

        add_filter('plugin_action_links_' . plugin_basename($pluginFile), static function (array $links): array {
            return array_merge([
                '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=comfino') . '">' .
                __('Settings', 'comfino-payment-gateway') . '</a>',
            ], $links);
        });

        add_filter('wc_order_statuses', static function (array $statuses): array {
            global $post;

            if (isset($post) && 'shop_order' === $post->post_type) {
                $order = wc_get_order($post->ID);

                if (isset($statuses['wc-cancelled']) && $order->get_payment_method() === 'comfino' && $order->has_status('completed')) {
                    unset($statuses['wc-cancelled']);
                }
            }

            return $statuses;
        });

        load_plugin_textdomain('comfino-payment-gateway', false, basename($pluginDirectory) . '/languages');

        // Initialize cache system.
        CacheManager::init($pluginDirectory);
    }

    public static function uninstall(string $pluginDirectory, string $pluginFile): bool
    {
        /*$config_manager = new Config_Manager();

        Api_Client::init($config_manager);
        Api_Client::notify_plugin_removal();

        $config_manager->remove_configuration_options();*/

        ConfigManager::deleteConfigurationValues();

        ErrorLogger::init($pluginDirectory, $pluginFile);
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
