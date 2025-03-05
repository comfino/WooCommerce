<?php

namespace Comfino;

use Comfino\Api\ApiClient;
use Comfino\Api\ApiService;
use Comfino\Configuration\ConfigManager;
use Comfino\Configuration\SettingsManager;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Order\OrderManager;
use Comfino\PluginShared\CacheManager;
use Comfino\View\FrontendManager;
use Comfino\View\TemplateManager;

if (!defined('ABSPATH')) {
    exit;
}

final class Main
{
    private const MIN_PHP_VERSION_ID = 70100;
    private const MIN_PHP_VERSION = '7.1.0';
    private const MIN_WC_VERSION = '3.0.0';

    /** @var bool */
    private static $initialized = false;
    /** @var string */
    private static $pluginDirectory;
    /** @var string */
    private static $pluginFile;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        ErrorLogger::init();

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
                if (empty($requestUri = self::getCurrentUrl())) {
                    return;
                }

                if (strpos($requestUri, 'comfino/paywall') === false) {
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
                        add_action('shutdown', [WC()->customer, 'save']);
                    } catch (\Exception $e) {
                        ErrorLogger::getLoggerInstance()->logError('wp_loaded:comfino_rest_load_cart', $e->getMessage());
                    }
                }

                // Load cart.
                if (WC()->cart === null) {
                    WC()->cart = new \WC_Cart();
                }
            }
        }, 5);

        add_action('wp_head', static function (): void {
            global $product;

            if (is_single() && is_product() && ConfigManager::isWidgetEnabled() && ConfigManager::getWidgetKey() !== '') {
                // Widget initialization script
                if (!($product instanceof \WC_Product)) {
                    $product = wc_get_product(get_the_ID());
                }

                $allowedProductTypes = SettingsManager::getAllowedProductTypes(
                   ProductTypesListTypeEnum::LIST_TYPE_WIDGET,
                   OrderManager::getShopCartFromProduct($product)
                );

                if ($allowedProductTypes === []) {
                    // Filters active - all product types disabled.
                    DebugLogger::logEvent('[WIDGET]', 'Filters active - all product types disabled.');

                    return;
                }

                FrontendManager::embedInlineScript('comfino-widget-init-script', FrontendManager::renderWidgetInitCode($product->get_id()));
            }
        });

        add_filter('plugin_action_links_' . plugin_basename(self::$pluginFile), static function (array $links): array {
            return array_merge([
                '<a href="' . wp_nonce_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=comfino'), 'comfino_settings', 'comfino_nonce') . '">' .
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

        load_plugin_textdomain('comfino-payment-gateway', false, basename(self::$pluginDirectory) . '/languages');

        // Initialize cache system.
        CacheManager::init(dirname(__DIR__) . '/var');

        // Register module API endpoints.
        ApiService::registerEndpoints();

        self::$initialized = true;
    }

    public static function uninstall(string $pluginDirectory): bool
    {
        ConfigManager::deleteConfigurationValues();

        delete_transient('comfino_plugin_updated');
        delete_transient('comfino_plugin_prev_version');
        delete_transient('comfino_plugin_updated_at');

        self::$pluginDirectory = $pluginDirectory;

        ErrorLogger::init();
        ApiClient::getInstance()->notifyPluginRemoval();

        return true;
    }

    public static function renderPaywallIframe(\WC_Cart $cart, float $total, bool $isPaymentBlock): string
    {
        if (!self::paymentIsAvailable($cart)) {
            DebugLogger::logEvent(
                '[PAYWALL]',
                'renderPaywallIframe: paymentIsAvailable=FALSE or preparePaywallIframe=NULL'
            );

            return '';
        }

        if (!$isPaymentBlock) {
            $iframeRenderer = FrontendManager::getPaywallIframeRenderer();

            $styleIds = FrontendManager::includeExternalStyles($iframeRenderer->getStyles());
            $scriptIds = FrontendManager::includeExternalScripts($iframeRenderer->getScripts());

            $scriptIds = array_merge(
                $scriptIds,
                FrontendManager::includeLocalScripts(['paywall-init.js'], ['paywall-init.js' => $scriptIds])
            );

            DebugLogger::logEvent(
                '[PAYWALL]', 'renderPaywallIframe registered styles and scripts.',
                ['$styleIds' => $styleIds, '$scriptIds' => $scriptIds]
            );
        }

        $templateVariables = [
            'render_init_script' => !$isPaymentBlock,
            'paywall_url' => ApiService::getEndpointUrl('paywall'),
            'paywall_options' => self::getPaywallOptions($total),
        ];

        return TemplateManager::renderView('payment', 'front', $templateVariables, !$isPaymentBlock);
    }

    public static function paymentIsAvailable(?\WC_Cart $cart): bool
    {
        if (ConfigManager::isServiceMode()) {
            if (isset($_COOKIE['COMFINO_SERVICE_SESSION']) && $_COOKIE['COMFINO_SERVICE_SESSION'] === 'ACTIVE') {
                DebugLogger::logEvent('[PAYWALL]', 'paymentIsAvailable: service mode is active.');
            } else {
                return false;
            }
        }

        if (!ConfigManager::isEnabled() || empty(ConfigManager::getApiKey())) {
            DebugLogger::logEvent('[PAYWALL]', 'paymentIsAvailable: plugin disabled or incomplete configuration.');

            return false;
        }

        if ($cart === null || !did_action('wp_loaded')) {
            return true;
        }

        $shopCart = OrderManager::getShopCart($cart);
        $allowedProductTypes = SettingsManager::getAllowedProductTypes(
            ProductTypesListTypeEnum::LIST_TYPE_PAYWALL,
            $shopCart
        );
        $paymentIsAvailable = ($allowedProductTypes !== []);

        DebugLogger::logEvent(
            '[PAYWALL]',
            sprintf('paymentIsAvailable: (paywall iframe is %s)', $paymentIsAvailable ? 'visible' : 'invisible'),
            [
                '$paymentIsAvailable' => $paymentIsAvailable,
                '$allowedProductTypes' => $allowedProductTypes,
                '$cartTotalValue' => $shopCart->getTotalValue(),
            ]
        );

        return $paymentIsAvailable;
    }

    public static function getPluginDirectory(): string
    {
        return self::$pluginDirectory;
    }

    public static function setPluginDirectory(string $pluginDirectory): void
    {
        self::$pluginDirectory = $pluginDirectory;
    }

    public static function getPluginFile(): string
    {
        return self::$pluginFile;
    }

    public static function setPluginFile(string $pluginFile): void
    {
        self::$pluginFile = $pluginFile;
    }

    public static function getShopDomain(): string
    {
        return !empty($shopLink = self::getShopLink()) ? wp_parse_url($shopLink, PHP_URL_HOST) : '';
    }

    public static function getShopUrl(bool $withoutScheme = false): string
    {
        if (empty($shopLink = self::getShopLink())) {
            return '';
        }

        $urlParts = wp_parse_url($shopLink);

        return (!$withoutScheme ? $urlParts['scheme'] . '://' : '') . $urlParts['host'] . (isset($urlParts['port']) ? ':' . $urlParts['port'] : '');
    }

    public static function getShopLanguage(): string
    {
        return substr(get_bloginfo('language'), 0, 2);;
    }

    public static function getShopCurrency(): string
    {
        return get_woocommerce_currency();
    }

    public static function getCurrentUrl(): string
    {
        return sanitize_url(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));
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
                ? sprintf(
                    /* translators: 1: Minimum required PHP version 2: Current PHP version */
                    __('The plugin could not be activated. The minimum PHP version required for Comfino is %1$s. You are running %2$s.', 'comfino-payment-gateway'),
                    self::MIN_PHP_VERSION,
                    PHP_VERSION
                )
                : sprintf(
                    /* translators: 1: Minimum required PHP version 2: Current PHP version */
                    __('The Comfino plugin has been deactivated. The minimum PHP version required for Comfino is %1$s. You are running %2$s.', 'comfino-payment-gateway'),
                    self::MIN_PHP_VERSION,
                    PHP_VERSION
                );
        }

        if (!defined('WC_VERSION')) {
            return $duringActivation
                ? __('The plugin could not be activated. WooCommerce needs to be activated.', 'comfino-payment-gateway')
                : __('The Comfino plugin has been deactivated. WooCommerce needs to be activated.', 'comfino-payment-gateway');
        }

        if (version_compare(WC_VERSION, self::MIN_WC_VERSION, '<')) {
            return $duringActivation
                ? sprintf(
                    /* translators: 1: Minimum required WooCommerce version 2: Current WooCommerce version */
                    __('The plugin could not be activated. The minimum WooCommerce version required for Comfino is %1$s. You are running %2$s.', 'comfino-payment-gateway'),
                    self::MIN_WC_VERSION,
                    WC_VERSION
                )
                : sprintf(
                    /* translators: 1: Minimum required WooCommerce version 2: Current WooCommerce version */
                    __('The Comfino plugin has been deactivated. The minimum WooCommerce version required for Comfino is %1$s. You are running %2$s.', 'comfino-payment-gateway'),
                    self::MIN_WC_VERSION,
                    WC_VERSION
                );
        }

        if (!extension_loaded('curl')) {
            return $duringActivation
                ? __('The plugin could not be activated. It requires PHP cURL extension which is not installed. More details: https://www.php.net/manual/en/book.curl.php', 'comfino-payment-gateway')
                : __('The Comfino plugin has been deactivated. It requires PHP cURL extension which is not installed. More details: https://www.php.net/manual/en/book.curl.php', 'comfino-payment-gateway');
        }

        return false;
    }

    public static function getPaywallOptions(float $total): array
    {
        return [
            'platform' => 'woocommerce',
            'platformName' => 'WooCommerce',
            'platformVersion' => WC_VERSION,
            'platformDomain' => self::getShopDomain(),
            'pluginVersion' => PaymentGateway::VERSION,
            'language' => self::getShopLanguage(),
            'currency' => self::getShopCurrency(),
            'cartTotal' => $total,
            'cartTotalFormatted' => wc_price($total, ['currency' => self::getShopCurrency()]),
            'productDetailsApiPath' => ApiService::getEndpointPath('paywallItemDetails'),
        ];
    }

    private static function getShopLink(): string
    {
        global $wp_rewrite;

        if (isset($wp_rewrite)) {
            return wc_get_page_permalink('shop');
        }

        if (isset($_SERVER['REQUEST_SCHEME'], $_SERVER['HTTP_HOST'])) {
            return sanitize_url(wp_unslash($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']));
        }

        return sanitize_url(wp_unslash($_SERVER['HTTP_REFERER'] ?? ''));
    }
}
