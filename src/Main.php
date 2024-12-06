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
use ComfinoExternal\Psr\Cache\InvalidArgumentException;

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
    /** @var Common\Backend\ErrorLogger */
    private static $errorLogger;
    /** @var string */
    private static $debugLogFilePath;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$errorLogger = ErrorLogger::getLoggerInstance();
        self::$debugLogFilePath = self::$pluginDirectory . '/var/log/debug.log';

        ErrorLogger::init(self::$pluginDirectory);

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

                $restPrefix = 'comfino/paywall';
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

        add_action('wp_head', static function () {
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
                    self::debugLog('[WIDGET]', 'Filters active - all product types disabled.');

                    return;
                }

                echo FrontendManager::renderWidgetInitCode($product->get_id());
            }
        });

        add_filter('plugin_action_links_' . plugin_basename(self::$pluginFile), static function (array $links): array {
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

        ErrorLogger::init($pluginDirectory);
        ApiClient::getInstance()->notifyPluginRemoval();

        return true;
    }

    public static function renderPaywallIframe(\WC_Cart $cart, float $total, bool $isPaymentBlock): string
    {
        if (!self::paymentIsAvailable($cart, (int) ($total * 100)) || ($paywallIframe = self::preparePaywallIframe($total, $isPaymentBlock)) === null) {
            self::debugLog('[PAYWALL]', 'renderPaywallIframe: paymentIsAvailable=FALSE or preparePaywallIframe=NULL');

            return '';
        }

        return $paywallIframe;
    }

    public static function debugLog(string $debugPrefix, string $debugMessage, ?array $parameters = null): void
    {
        if ((!isset($_COOKIE['COMFINO_SERVICE_SESSION']) || $_COOKIE['COMFINO_SERVICE_SESSION'] !== 'ACTIVE') && ConfigManager::isServiceMode()) {
            return;
        }

        if (ConfigManager::isDebugMode()) {
            if (!empty($parameters)) {
                $preparedParameters = [];

                foreach ($parameters as $name => $value) {
                    if (is_array($value)) {
                        $value = json_encode($value);
                    } elseif (is_bool($value)) {
                        $value = ($value ? 'true' : 'false');
                    }

                    $preparedParameters[] = "$name=$value";
                }

                $debugMessage .= (($debugMessage !== '' ? ': ' : '') . implode(', ', $preparedParameters));
            }

            global $wp_filesystem;

            $wp_filesystem->put_contents(
                self::$debugLogFilePath,
                '[' . gmdate('Y-m-d H:i:s') . "] $debugPrefix: $debugMessage\n",
                FS_CHMOD_FILE
            );
        }
    }

    public static function getDebugLog(int $numLines): string
    {
        return self::$errorLogger->getErrorLog(self::$debugLogFilePath, $numLines);
    }

    public static function paymentIsAvailable(?\WC_Cart $cart, int $loanAmount): bool
    {
        if (ConfigManager::isServiceMode()) {
            if (isset($_COOKIE['COMFINO_SERVICE_SESSION']) && $_COOKIE['COMFINO_SERVICE_SESSION'] === 'ACTIVE') {
                self::debugLog('[PAYWALL]', 'paymentIsAvailable: service mode is active.');
            } else {
                return false;
            }
        }

        if (!ConfigManager::isEnabled() || empty(ConfigManager::getApiKey())) {
            self::debugLog('[PAYWALL]', 'paymentIsAvailable: plugin disabled or incomplete configuration.');

            return false;
        }

        if ($cart === null || !did_action('wp_loaded')) {
            return true;
        }

        return SettingsManager::getAllowedProductTypes(
            ProductTypesListTypeEnum::LIST_TYPE_PAYWALL,
            OrderManager::getShopCart($cart, $loanAmount)
        ) !== [];
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
        return parse_url(wc_get_page_permalink('shop'), PHP_URL_HOST);
    }

    public static function getShopUrl(): string
    {
        $urlParts = parse_url(wc_get_page_permalink('shop'));

        return $urlParts['host'] . (isset($urlParts['port']) ? ':' . $urlParts['port'] : '');
    }

    public static function getShopLanguage(): string
    {
        return substr(get_locale(), 0, 2);
    }

    public static function getShopCurrency(): string
    {
        return get_woocommerce_currency();
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
                ? __('The plugin could not be activated. It requires PHP cURL extension which is not installed. ' .
                     'More details: https://www.php.net/manual/en/book.curl.php', 'comfino-payment-gateway')
                : __('The Comfino plugin has been deactivated. It requires PHP cURL extension which is not installed. ' .
                     'More details: https://www.php.net/manual/en/book.curl.php', 'comfino-payment-gateway');
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

    private static function preparePaywallIframe(float $total, bool $isPaymentBlock): ?string
    {
        /** @var \Comfino_Payment_Gateway $comfino_payment_gateway */
        global $comfino_payment_gateway;

        try {
            $renderer = FrontendManager::getPaywallIframeRenderer();

            $paywallElements = $renderer->getPaywallElements(ApiService::getEndpointUrl('paywall'));

            $styleTimestamp = $renderer->getPaywallFrontendStyleTimestamp();
            $scriptTimestamp = $renderer->getPaywallFrontendScriptTimestamp();
            $paywallFrontendStyleUrl = ApiService::getEndpointUrl('paywallFrontendStyle') . ($styleTimestamp !== 0 ? "/$styleTimestamp" : '');
            $paywallFrontendScriptUrl = ApiService::getEndpointUrl('paywallFrontendScript') . ($scriptTimestamp !== 0 ? "/$scriptTimestamp" : '');

            wp_enqueue_style('comfino-frontend-style', $paywallFrontendStyleUrl, [], null);
            wp_enqueue_script('comfino-frontend-script', $paywallFrontendScriptUrl, [], null);

            if (!$isPaymentBlock) {
                wp_enqueue_script(
                    'comfino-payment-gateway-script',
                    $comfino_payment_gateway->plugin_url() . '/resources/js/front/paywall.min.js',
                    [],
                    null
                );
            }

            return TemplateManager::renderView(
                'payment',
                'front',
                [
                    'paywall_iframe' => $paywallElements['iframe'],
                    'render_init_script' => !$isPaymentBlock,
                    'paywall_options' => self::getPaywallOptions($total),
                    'comfino_logo_url' => ApiClient::getPaywallLogoUrl(),
                    'comfino_label' => ConfigManager::getConfigurationValue('COMFINO_PAYMENT_TEXT'),
                    'comfino_redirect_url' => ApiService::getEndpointUrl('payment'),
                ]
            );
        } catch (\Throwable|InvalidArgumentException $e) {
            ApiClient::processApiError('Paywall error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)', $e);
        }

        return null;
    }
}
