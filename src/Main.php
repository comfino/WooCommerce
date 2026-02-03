<?php /** @noinspection PhpExpressionResultUnusedInspection */

namespace Comfino;

use Comfino\Api\ApiClient;
use Comfino\Api\ApiService;
use Comfino\Common\Backend\FileUtils;
use Comfino\Configuration\ConfigManager;
use Comfino\Configuration\SettingsManager;
use Comfino\Configuration\StorageAdapter;
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

    private const INSTALL_LOG_FILENAME = 'install.log';
    private const UPGRADE_LOG_FILENAME = 'upgrade.log';
    private const UNINSTALL_LOG_FILENAME = 'uninstall.log';

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
                    $wcProduct = wc_get_product(get_the_ID());
                } else {
                    $wcProduct = $product;
                }

                $allowedProductTypes = SettingsManager::getAllowedProductTypes(
                   ProductTypesListTypeEnum::LIST_TYPE_WIDGET,
                   OrderManager::getShopCartFromProduct($wcProduct)
                );

                if ($allowedProductTypes === []) {
                    // Filters active - all product types disabled.
                    DebugLogger::logEvent('[WIDGET]', 'Filters active - all product types disabled.');

                    return;
                }

                FrontendManager::embedInlineScript('comfino-widget-init-script', FrontendManager::renderWidgetInitCode($wcProduct->get_id()));
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

                if (isset($statuses['wc-cancelled']) && $order->get_payment_method() === PaymentGateway::GATEWAY_ID && $order->has_status('completed')) {
                    unset($statuses['wc-cancelled']);
                }
            }

            return $statuses;
        });

        // Initialize cache system.
        CacheManager::init(self::getCacheRootPath());

        // Register module API endpoints.
        ApiService::registerEndpoints();

        self::$initialized = true;
    }

    public static function install(): void
    {
        ErrorLogger::init();

        // Initialize version tracking option for upgrade detection.
        update_option('comfino_plugin_current_version', PaymentGateway::VERSION, false);

        // Initialize default configuration values on first activation.
        $resultStats = self::initDefaultConfiguration();

        self::createInstallLog(print_r($resultStats, true));
    }

    public static function uninstall(string $pluginDirectory): void
    {
        self::$pluginDirectory = $pluginDirectory;

        ErrorLogger::init();

        $resultStats = ['operations' => []];

        // 1. Delete configuration values.
        if (ConfigManager::deleteConfigurationValues()) {
            $resultStats['operations'][] = ['name' => 'configuration_options_delete', 'success' => true];
        } else {
            $resultStats['operations'][] = ['name' => 'configuration_options_delete', 'success' => false];
        }

        // 2. Delete options and transients.
        delete_option('comfino_plugin_current_version');
        delete_transient('comfino_plugin_updated');
        delete_transient('comfino_plugin_prev_version');
        delete_transient('comfino_plugin_updated_at');

        $resultStats['operations'][] = ['name' => 'transients_delete', 'success' => true];

        // 3. Notify Comfino API about plugin removal.
        if (ApiClient::getInstance()->notifyPluginRemoval()) {
            $resultStats['operations'][] = ['name' => 'uninstall_notification_sent', 'success' => true];
        } else {
            $resultStats['operations'][] = ['name' => 'uninstall_notification_sent', 'success' => false];
        }

        self::createUninstallLog(print_r($resultStats, true));
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

        try {
            $shopCart = OrderManager::getShopCart($cart);
        } catch (\Exception $e) {
            FrontendManager::processError('Shop cart creation error', $e);

            return false;
        }

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

    public static function getVarPath(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'var';
    }

    public static function getCacheRootPath(): string
    {
        return self::getVarPath();
    }

    public static function getCachePath(): string
    {
        return CacheManager::getCacheFullPath();
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
        $shopDomain = !empty($shopLink = self::getShopLink()) ? wp_parse_url($shopLink, PHP_URL_HOST) : '';

        if (!empty($shopDomain)) {
            $shopPort = wp_parse_url($shopLink, PHP_URL_PORT);

            if (!empty($shopPort) && $shopPort !== 80) {
                $shopDomain .= ":$shopPort";
            }
        }

        return $shopDomain;
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
        return substr(get_bloginfo('language'), 0, 2);
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

    /**
     * Resets plugin to initial state without uninstalling.
     *
     * This method:
     * - Repairs missing configuration options.
     * - Clears configuration and frontend cache.
     *
     * @return array Reset operation statistics
     */
    public static function reset(): array
    {
        ErrorLogger::init();

        $resultStats = [
            'config_repaired' => 0,
            'config_failed' => 0,
            'operations' => [],
        ];

        // 1. Repair missing configuration options.
        try {
            $repairStats = ConfigManager::repairMissingConfigurationOptions();

            $resultStats['config_repaired'] = $repairStats['repaired'];
            $resultStats['config_failed'] = $repairStats['failed'];
            $resultStats['operations'][] = [
                'name' => 'configuration_repair',
                'success' => $repairStats['failed'] === 0,
                'details' => $repairStats,
            ];
        } catch (\Throwable $e) {
            $resultStats['operations'][] = [
                'name' => 'configuration_repair',
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        // 2. Clear configuration and frontend cache.
        CacheManager::getCachePool()->clear();

        return $resultStats;
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

    public static function updateUpgradeLog(string $logContents): void
    {
        self::appendLog(self::UPGRADE_LOG_FILENAME, $logContents);
    }

    public static function readUpgradeLog(): string
    {
        return self::readLog(self::UPGRADE_LOG_FILENAME);
    }

    public static function readInstallLog(): string
    {
        return self::readLog(self::INSTALL_LOG_FILENAME);
    }

    public static function readUninstallLog(): string
    {
        return self::readLog(self::UNINSTALL_LOG_FILENAME);
    }

    private static function createInstallLog(string $logContents): void
    {
        self::writeLog(self::INSTALL_LOG_FILENAME, $logContents);
    }

    private static function createUninstallLog(string $logContents): void
    {
        self::writeLog(self::UNINSTALL_LOG_FILENAME, $logContents);
    }

    private static function readLog(string $fileName): string
    {
        $logPath = FileUtils::buildPathFromComponents([self::getVarPath(), 'log', $fileName]);

        if (FileUtils::isReadable($logPath)) {
            return FileUtils::read($logPath);
        }

        return '';
    }

    private static function writeLog(string $fileName, string $logContents): void
    {
        $logPath = FileUtils::buildPathFromComponents([self::getVarPath(), 'log', $fileName]);

        if (FileUtils::isWritable(dirname($logPath))) {
            FileUtils::write($logPath, gmdate('Y-m-d H:i:s') . "\n$logContents");
        }
    }

    private static function appendLog(string $fileName, string $logContents): void
    {
        $logPath = FileUtils::buildPathFromComponents([self::getVarPath(), 'log', $fileName]);

        // Check if file is writable (if exists) or if directory is writable (to create new file).
        if (FileUtils::isWritable($logPath) || (!FileUtils::exists($logPath) && FileUtils::isWritable(dirname($logPath)))) {
            FileUtils::append($logPath, gmdate('Y-m-d H:i:s') . "\n$logContents");
        }
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

    /**
     * Initializes default configuration values on first plugin activation.
     */
    private static function initDefaultConfiguration(): array
    {
        $resultStats = ['operations' => []];

        $storageAdapter = new StorageAdapter();
        $optionKey = $storageAdapter->get_option_key();

        // Check if configuration already exists.
        if (get_option($optionKey) !== false) {
            $resultStats['operations'][] = [
                'name' => 'configuration_exists',
                'success' => true,
                'note' => 'Configuration already exists.',
            ];

            return $resultStats;
        }

        // Persist default configuration values to database.
        $defaultValues = ConfigManager::getDefaultConfigurationValues();
        $configurationData = [];

        foreach ($defaultValues as $optionName => $defaultValue) {
            if (array_key_exists($optionName, ConfigManager::CONFIG_OPTIONS_MAP)) {
                $internalName = ConfigManager::CONFIG_OPTIONS_MAP[$optionName];

                // Convert boolean values to WooCommerce format ('yes'/'no').
                if (is_bool($defaultValue)) {
                    $defaultValue = $defaultValue ? 'yes' : 'no';
                }

                $configurationData[$internalName] = $defaultValue;
            }
        }

        if (update_option($optionKey, $configurationData)) {
            $resultStats['operations'][] = [
                'name' => 'init_configuration_options',
                'success' => true,
                'options_count' => count($configurationData),
            ];
        } else {
            $resultStats['operations'][] = [
                'name' => 'init_configuration_options',
                'success' => false,
            ];
        }

        return $resultStats;
    }
}
