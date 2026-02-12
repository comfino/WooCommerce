<?php /** @noinspection PhpExpressionResultUnusedInspection */
/*
 * Plugin Name: Comfino Payment Gateway
 * Plugin URI: https://github.com/comfino/WooCommerce.git
 * Description: Comfino Payment Gateway for WooCommerce.
 * Version: 4.2.8
 * Author: Comfino
 * Author URI: https://github.com/comfino
 * Domain Path: /languages
 * Text Domain: comfino-payment-gateway
 * WC tested up to: 10.5.0
 * WC requires at least: 3.0
 * Tested up to: 6.9
 * Requires at least: 5.0
 * Requires PHP: 7.1
 * License: GPLv3
 *
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Guard clause to prevent plugin execution in incompatible environments.
 * This MUST be placed before any code which uses PHP 7.1+ syntax and before any use statements.
 * Uses PHP 5.6+ compatible syntax.
 */
if (PHP_VERSION_ID < 70100) {
    // Display admin notice about PHP version incompatibility.
    add_action('admin_notices', static function () {
        $pluginData = get_file_data(__FILE__, ['Version' => 'Version', 'RequiresPHP' => 'Requires PHP']);

        $current_php_version = PHP_VERSION;
        $required_php_version = $pluginData['RequiresPHP'] ?: '7.1';
        $plugin_version = $pluginData['Version'] ?: 'unknown';
        $can_deactivate = current_user_can('activate_plugins');

        // Load template file.
        include __DIR__ . '/views/admin/php-compatibility-notice.php';
    });

    // Deactivate plugin if it's active.
    add_action('admin_init', static function () {
        // Ensure plugin.php functions are loaded.
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = plugin_basename(__FILE__);

        if (is_plugin_active($plugin_file)) {
            deactivate_plugins($plugin_file);

            // Clear the 'Plugin activated' notice.
            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }
        }
    });

    return;
}

/* Environment check passed - now safe to use PHP 7.1+ features. */

use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Configuration\ConfigManager;
use Comfino\DebugLogger;
use Comfino\ErrorLogger;
use Comfino\Main;
use Comfino\Order\ShopStatusManager;
use Comfino\PaymentGateway;
use Comfino\PluginShared\CacheManager;
use Comfino\View\TemplateManager;

class Comfino_Payment_Gateway
{
    /** @var array */
    public $notices = [];

    /** @var Comfino_Payment_Gateway */
    private static $instance;

    public static function get_instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        if (is_readable(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
        } else {
            $this->add_admin_notice('vendor_access_error', 'error', 'File ' . __DIR__ . '/vendor/autoload.php is not readable.');
            $this->admin_notices();

            return;
        }

        // Check for available GitHub updates (information only, no automatic updates).
        $this->check_github_version();

        // Basic hooks
        add_action('init', [$this, 'init']);
        add_action('admin_init', [$this, 'check_environment']);
        add_action('admin_init', [$this, 'check_debug_mode']);
        add_action('admin_notices', [$this, 'admin_notices'], 15);
        add_action('admin_post_comfino_plugin_reset', [$this, 'handle_plugin_reset']);
        add_action('admin_post_comfino_clear_error_log', [$this, 'handle_clear_error_log']);
        add_action('admin_post_comfino_clear_debug_log', [$this, 'handle_clear_debug_log']);
        add_action('plugins_loaded', function (): void {
            // Store current version in persistent option for upgrade tracking.
            if (get_option('comfino_plugin_current_version', '') !== PaymentGateway::VERSION) {
                update_option('comfino_plugin_current_version', PaymentGateway::VERSION, false);
            }

            if (get_transient('comfino_plugin_updated')) {
                $this->upgrade_plugin();
            }
        });

        // Upgrade hook
        add_action('upgrader_process_complete', static function (WP_Upgrader $upgrader, array $options): void {
            $comfinoPluginPathName = plugin_basename(__FILE__);

            if ($options['action'] === 'update' && $options['type'] === 'plugin') {
                $pluginUpdated = false;

                // Plugin updated.
                if (isset($options['plugins'])) {
                    // Bulk plugins update (update page)
                    foreach($options['plugins'] as $pluginPathName) {
                        if ($pluginPathName === $comfinoPluginPathName) {
                            $pluginUpdated = true;

                            break;
                        }
                    }
                } elseif (isset($options['plugin'])) {
                    // Normal plugin update or via auto update
                    if ($options['plugin'] === $comfinoPluginPathName) {
                        $pluginUpdated = true;
                    }
                }

                if ($pluginUpdated && ($previousVersion = get_option('comfino_plugin_current_version', 'unknown')) !== PaymentGateway::VERSION) {
                    // Only set transient if version actually changed.
                    set_transient('comfino_plugin_updated', 1);
                    set_transient('comfino_plugin_prev_version', $previousVersion);
                    set_transient('comfino_plugin_updated_at', time());
                }
            }
        }, 10, 2);

        // Overwrite hook
        add_action('upgrader_overwrote_package', static function (string $package, array $data, string $package_type): void {
            if ($package_type === 'plugin' && $data['Name'] === 'Comfino Payment Gateway' && ($previousVersion = get_option('comfino_plugin_current_version', 'unknown')) !== PaymentGateway::VERSION) {
                // Only set transient if version actually changed.
                set_transient('comfino_plugin_updated', 1);
                set_transient('comfino_plugin_prev_version', $previousVersion);
                set_transient('comfino_plugin_updated_at', time());
            }
        }, 10, 3);

        // Add a Comfino gateway to the WooCommerce payment methods available for customer.
        add_filter('woocommerce_payment_gateways', static function (array $methods): array {
            $methods[] = PaymentGateway::class;

            return $methods;
        });

        // Add loaded script tag filter for adding custom attribute which prevents blocking by Google CMP scripts.
        add_filter('script_loader_tag', static function (string $tag, string $handle): string {
            if (strpos($handle, PaymentGateway::GATEWAY_ID) !== 0) {
                return $tag;
            }

            $attributes = [];

            if (strpos($handle, 'async') !== false) {
                if (strpos($tag, 'async') === false) {
                    $attributes[] = 'async';
                }
            } elseif (strpos($tag, 'defer') !== false) {
                if (strpos($tag, 'defer') === false) {
                    $attributes[] = 'defer';
                }
            }

            $attributes[] = 'data-cmp-ab="2"';

            return str_replace('">', '" ' . implode(' ', $attributes) . '>', $tag);
        }, 10, 2);

        // Add inline script tag filter for adding custom attribute which prevents blocking by Google CMP scripts.
        add_filter('wp_inline_script_attributes', static function (array $attributes): array {
            if (isset($attributes['id']) && strpos($attributes['id'], PaymentGateway::GATEWAY_ID) === 0) {
                $attributes['data-cmp-ab'] = '2';
            }

            return $attributes;
        });

        // Add admin URL filter for adding custom nonce parameter to Comfino plugin links which redirect to the settings panel.
        add_filter('admin_url', static function (string $url): string {
            if (strpos($url, 'section=comfino') === false || strpos($url, 'comfino_nonce') !== false) {
                return $url;
            }

            return wp_nonce_url($url, 'comfino_settings', 'comfino_nonce');
        }, 10, 2);

        // Declare compatibility with WooCommerce HPOS and Payment Blocks.
        add_action('before_woocommerce_init', static function (): void {
            if (class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__);
                Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__);
            }
        });

        // Register integration hook for WooCommerce Cart and Checkout Blocks.
        add_action('woocommerce_blocks_loaded', static function (): void {
            if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
                add_action(
                    'woocommerce_blocks_payment_method_type_registration',
                    static function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $paymentMethodRegistry): void {
                        $paymentMethodRegistry->register(new Comfino\View\Block\PaymentGateway());
                    }
                );
            }
        });

        Main::setPluginDirectory(__DIR__);
        Main::setPluginFile(__FILE__);
    }

    /**
     * Automatically disables the plugin on activation if it doesn't meet minimum requirements.
     */
    public function activation_check(): void
    {
        $environmentWarning = Main::getEnvironmentWarning(true);

        if ($environmentWarning) {
            deactivate_plugins(plugin_basename(__FILE__));
            /** @noinspection ForgottenDebugOutputInspection */
            wp_die(wp_kses_post($environmentWarning));
        }

        Main::install();
    }

    /**
     * Plugin URL.
     */
    public function plugin_url(): string
    {
        return untrailingslashit(plugins_url('/', __FILE__));
    }

    /**
     * Plugin absolute path.
     */
    public function plugin_abspath(): string
    {
        return trailingslashit(plugin_dir_path(__FILE__));
    }

    /**
     * Plugin initialization.
     */
    public function init(): void
    {
        if ($this->check_environment()) {
            return;
        }

        // Initialize Comfino plugin.
        Main::init();
    }

    /**
     * @return string|bool
     */
    public function check_environment()
    {
        $environmentWarning = Main::getEnvironmentWarning();

        if ($environmentWarning) {
            // Ensure is_plugin_active() is available.
            if (!function_exists('is_plugin_active')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            if (is_plugin_active(plugin_basename(__FILE__))) {
                deactivate_plugins(plugin_basename(__FILE__));
                $this->add_admin_notice('bad_environment', 'error', $environmentWarning);

                if (isset($_GET['activate'])) {
                    unset($_GET['activate']);
                }
            }
        }

        return $environmentWarning;
    }

    public function add_admin_notice(string $slug, string $class, string $message): void
    {
        $this->notices[$slug] = ['class' => $class, 'message' => $message];
    }

    public function admin_notices(): void
    {
        if (get_transient('comfino_plugin_updated')) {
            echo '<div class="notice notice-success">' . wp_kses(sprintf(
                /* translators: 1: Previous plugin version 2: Current plugin version */
                __('Comfino plugin updated from version %1$s to %2$s.', 'comfino-payment-gateway'),
                get_transient('comfino_plugin_prev_version'),
                PaymentGateway::VERSION
            ), 'user_description') . '</div>';

            $this->upgrade_plugin();
        }

        // Check for plugin reset results.
        if ($resetResults = get_transient('comfino_plugin_reset_results')) {
            $hasErrors = ($resetResults['config_failed'] ?? 0) > 0;
            $noticeClass = $hasErrors ? 'notice notice-warning is-dismissible' : 'notice notice-success is-dismissible';
            $noticeMessage = $hasErrors
                ? __('Plugin reset completed with some errors.', 'comfino-payment-gateway')
                : __('Plugin reset completed successfully.', 'comfino-payment-gateway');
            $noticeMessage .= ' ' . sprintf(
                /* translators: 1: Number of configuration options repaired 2: Number of configuration options failed */
                __('Configuration: %1$d repaired, %2$d failed', 'comfino-payment-gateway'),
                $resetResults['config_repaired'] ?? 0,
                $resetResults['config_failed'] ?? 0
            );

            $this->add_admin_notice('plugin_reset', $noticeClass, $noticeMessage);

            delete_transient('comfino_plugin_reset_results');
        }

        // Check for error log cleared.
        if (get_transient('comfino_error_log_cleared')) {
            $this->add_admin_notice(
                'error_log_cleared',
                'notice notice-success is-dismissible',
                __('Error log cleared successfully.', 'comfino-payment-gateway')
            );

            delete_transient('comfino_error_log_cleared');
        }

        // Check for debug log cleared.
        if (get_transient('comfino_debug_log_cleared')) {
            $this->add_admin_notice(
                'debug_log_cleared',
                'notice notice-success is-dismissible',
                __('Debug log cleared successfully.', 'comfino-payment-gateway')
            );

            delete_transient('comfino_debug_log_cleared');
        }

        foreach ($this->notices as $noticeKey => $notice) {
            echo '<div class="' . esc_attr($notice['class']) . '"><p>';
            echo wp_kses($notice['message'], ['a' => ['href' => []]]);
            echo "</p></div>";
        }
    }

    public function get_plugin_update_details(): array
    {
        static $updateDetails = null;

        if ($updateDetails === null) {
            $updateDetails = [
                'comfino_plugin_current_version' => get_option('comfino_plugin_current_version'),
                'comfino_plugin_updated' => get_transient('comfino_plugin_updated'),
                'comfino_plugin_prev_version' => get_transient('comfino_plugin_prev_version'),
                'comfino_plugin_updated_at' => get_transient('comfino_plugin_updated_at'),
            ];
        }

        return $updateDetails;
    }

    /**
     * Display admin notice if a newer version is available on GitHub.
     */
    public function display_github_version_notice(): void
    {
        $versionData = get_transient('comfino_github_version_check');

        if (!$versionData || isset($versionData['error'])) {
            return;
        }

        $githubVersion = $versionData['github_version'] ?? '';
        $currentVersion = PaymentGateway::VERSION;

        if (version_compare($githubVersion, $currentVersion, '>')) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p>';
            echo wp_kses(
                sprintf(
                    /* translators: 1: Current version 2: Available version 3: GitHub releases URL */
                    __('<strong>Comfino Payment Gateway:</strong> A new version (%2$s) is available on GitHub. You are currently using version %1$s. Visit <a href="%3$s" target="_blank">GitHub Releases</a> for more information.', 'comfino-payment-gateway'),
                    esc_html($currentVersion),
                    esc_html($githubVersion),
                    'https://github.com/comfino/WooCommerce/releases'
                ),
                ['strong' => [], 'a' => ['href' => [], 'target' => []]]
            );
            echo '</p>';
            echo '</div>';
        }
    }

    /**
     * Check if debug mode is enabled and display warning notice.
     * Debug mode should not be used in production environments as it may impact performance
     * and log sensitive data (though automatically redacted by SensitiveDataProcessor).
     * Use service mode together with debug mode if you want to debug in production environment
     * to collect debug logs only from one service session.
     */
    public function check_debug_mode(): void
    {
        if (!current_user_can('manage_woocommerce') || !ConfigManager::isDebugMode()) {
            return;
        }

        // Check if user has dismissed the notice.
        if (get_user_meta(get_current_user_id(), 'comfino_debug_notice_dismissed', true)) {
            return;
        }

        // Register AJAX handler for dismissing the notice.
        add_action('wp_ajax_comfino_dismiss_debug_notice', [$this, 'dismiss_debug_mode_notice']);

        // Display the notice.
        add_action('admin_notices', [$this, 'display_debug_mode_notice']);
    }

    /**
     * Display admin notice warning that debug mode is enabled.
     * Notice is dismissible and remembers user preference.
     */
    public function display_debug_mode_notice(): void
    {
        if (!ConfigManager::isDebugMode() || get_user_meta(get_current_user_id(), 'comfino_debug_notice_dismissed', true)) {
            return;
        }

        TemplateManager::renderView(
            'debug-mode-notice',
            'admin',
            [
                'settings_url' => admin_url('admin.php?page=wc-settings&tab=checkout&section=comfino'),
                'nonce_value' => wp_create_nonce('comfino-dismiss-debug-notice'),
            ]
        );
    }

    /**
     * AJAX handler for dismissing debug mode notice.
     * Stores user preference in user meta to persist dismissal across sessions.
     */
    public function dismiss_debug_mode_notice(): void
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['nonce'])), 'comfino-dismiss-debug-notice')) {
            wp_send_json_error(['message' => __('Invalid nonce.', 'comfino-payment-gateway')]);

            return;
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'comfino-payment-gateway')]);

            return;
        }

        update_user_meta(get_current_user_id(), 'comfino_debug_notice_dismissed', true);

        wp_send_json_success(['message' => __('Notice dismissed.', 'comfino-payment-gateway')]);
    }

    /**
     * Handle plugin reset action.
     */
    public function handle_plugin_reset(): void
    {
        if (!isset($_POST['comfino_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['comfino_nonce'])), 'comfino_settings')) {
            /** @noinspection ForgottenDebugOutputInspection */
            wp_die('Security check failed.');
        }

        if (!current_user_can('manage_woocommerce')) {
            /** @noinspection ForgottenDebugOutputInspection */
            wp_die('You do not have permission to perform this action.');
        }

        set_transient('comfino_plugin_reset_results', Main::reset(), 60);

        wp_safe_redirect(wp_get_referer());

        exit;
    }

    /**
     * Handle clear error log action.
     */
    public function handle_clear_error_log(): void
    {
        if (!isset($_POST['comfino_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['comfino_nonce'])), 'comfino_settings')) {
            /** @noinspection ForgottenDebugOutputInspection */
            wp_die('Security check failed.');
        }

        if (!current_user_can('manage_woocommerce')) {
            /** @noinspection ForgottenDebugOutputInspection */
            wp_die('You do not have permission to perform this action.');
        }

        ErrorLogger::clearLogs();

        set_transient('comfino_error_log_cleared', true, 60);

        wp_safe_redirect(wp_get_referer());

        exit;
    }

    /**
     * Handle clear debug log action.
     */
    public function handle_clear_debug_log(): void
    {
        if (!isset($_POST['comfino_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['comfino_nonce'])), 'comfino_settings')) {
            /** @noinspection ForgottenDebugOutputInspection */
            wp_die('Security check failed.');
        }

        if (!current_user_can('manage_woocommerce')) {
            /** @noinspection ForgottenDebugOutputInspection */
            wp_die('You do not have permission to perform this action.');
        }

        DebugLogger::clearLogs();

        set_transient('comfino_debug_log_cleared', true, 60);

        wp_safe_redirect(wp_get_referer());

        exit;
    }

    private function upgrade_plugin(): void
    {
        if (PaymentGateway::WIDGET_INIT_SCRIPT_HASH !== PaymentGateway::WIDGET_INIT_SCRIPT_LAST_HASH) {
            // Update code of widget initialization script if changed.
            ConfigManager::updateWidgetCode(PaymentGateway::WIDGET_INIT_SCRIPT_LAST_HASH);
        }

        /* 4.2.0 */
        if (is_array($ignoredStatuses = ConfigManager::getConfigurationValue('COMFINO_IGNORED_STATUSES'))
            && in_array(StatusManager::STATUS_CANCELLED_BY_SHOP, $ignoredStatuses, true)
        ) {
            ConfigManager::updateConfigurationValue('COMFINO_IGNORED_STATUSES', StatusManager::DEFAULT_IGNORED_STATUSES);
        }

        /* 4.2.1 */
        if (!is_array(ConfigManager::getConfigurationValue('COMFINO_WIDGET_OFFER_TYPES'))) {
            ConfigManager::updateConfigurationValue(
                'COMFINO_WIDGET_OFFER_TYPES',
                [ConfigManager::getConfigurationValue('COMFINO_WIDGET_OFFER_TYPE')]
            );
        }

        if (is_array($catFilterAvailProdTypes = ConfigManager::getConfigurationValue('COMFINO_CAT_FILTER_AVAIL_PROD_TYPES'))
            && !in_array('LEASING', $catFilterAvailProdTypes, true)
        ) {
            ConfigManager::updateConfigurationValue(
                'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES',
                ['INSTALLMENTS_ZERO_PERCENT', 'PAY_LATER', 'LEASING']
            );
        }

        ConfigManager::initConfigurationValues(['COMFINO_API_CONNECT_TIMEOUT' => 3, 'COMFINO_API_TIMEOUT' => 5]);

        /* 4.2.3 */
        if (!in_array(ConfigManager::getConfigurationValue('COMFINO_WIDGET_TYPE'), ['standard', 'classic'])) {
            ConfigManager::updateConfigurationValue('COMFINO_WIDGET_TYPE', 'standard');
        }

        ConfigManager::initConfigurationValues([
            'COMFINO_WIDGET_SHOW_PROVIDER_LOGOS' => false,
            'COMFINP_NEW_WIDGET_ACTIVE' => true,
            'COMFINP_DEV_ENV_VARS' => false,
        ]);

        if (is_array($catFilterAvailProdTypes = ConfigManager::getConfigurationValue('COMFINO_CAT_FILTER_AVAIL_PROD_TYPES'))
            && (!in_array('COMPANY_BNPL', $catFilterAvailProdTypes, true) || !in_array('COMPANY_INSTALLMENTS', $catFilterAvailProdTypes, true))
        ) {
            ConfigManager::updateConfigurationValue(
                'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES',
                ['INSTALLMENTS_ZERO_PERCENT', 'PAY_LATER', 'COMPANY_BNPL', 'COMPANY_INSTALLMENTS', 'LEASING']
            );
        }

        /* 4.2.4 */
        if (is_array($catFilterAvailProdTypes = ConfigManager::getConfigurationValue('COMFINO_CAT_FILTER_AVAIL_PROD_TYPES'))
            && (!in_array('PAY_IN_PARTS', $catFilterAvailProdTypes, true))
        ) {
            ConfigManager::updateConfigurationValue(
                'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES',
                array_merge($catFilterAvailProdTypes, ['PAY_IN_PARTS'])
            );
        }

        /* 4.2.6 */
        // Ensure status map contains all default mappings, especially STATUS_CANCELLED_BY_SHOP.
        $currentStatusMap = ConfigManager::getConfigurationValue('COMFINO_STATUS_MAP');
        $defaultStatusMap = ShopStatusManager::DEFAULT_STATUS_MAP;

        if (!is_array($currentStatusMap)) {
            // Status map not set, use defaults.
            ConfigManager::updateConfigurationValue('COMFINO_STATUS_MAP', $defaultStatusMap);
        } else {
            // Status map exists, check if it contains all default statuses.
            $needsUpdate = false;

            foreach ($defaultStatusMap as $status => $wcStatus) {
                if (!array_key_exists($status, $currentStatusMap)) {
                    // Missing status mapping, add it.
                    $currentStatusMap[$status] = $wcStatus;
                    $needsUpdate = true;
                }
            }

            if ($needsUpdate) {
                ConfigManager::updateConfigurationValue('COMFINO_STATUS_MAP', $currentStatusMap);
            }
        }

        /* 4.2.7 */
        // Initialize version tracking option if not set (for existing installations).
        if (!get_option('comfino_plugin_current_version') && ($previousVersion = get_transient('comfino_plugin_prev_version')) && $previousVersion !== 'unknown') {
            // First upgrade after implementing version tracking. Use the previous version from transient if available.
            update_option('comfino_plugin_current_version', $previousVersion, false);
        }

        // Update code of widget initialization script.
        ConfigManager::updateWidgetCode();

        // Clear configuration and front cache.
        CacheManager::getCachePool()->clear();

        // Clear plugin logs.
        ErrorLogger::clearLogs();
        DebugLogger::clearLogs();

        // Enable debug mode admin notice.
        update_user_meta(get_current_user_id(), 'comfino_debug_notice_dismissed', false);

        // Log upgrade statistics.
        $upgradeStats = [
            'plugin_name' => 'Comfino Payment Gateway',
            'previous_version' => get_transient('comfino_plugin_prev_version') ?: 'unknown',
            'current_version' => PaymentGateway::VERSION,
            'upgraded_at' => get_transient('comfino_plugin_updated_at')
                ? gmdate('Y-m-d H:i:s', get_transient('comfino_plugin_updated_at'))
                : gmdate('Y-m-d H:i:s'),
            'operations' => [
                ['name' => 'widget_code_update', 'success' => true],
                ['name' => 'configuration_migration', 'success' => true],
                ['name' => 'cache_clear', 'success' => true],
                ['name' => 'logs_clear', 'success' => true],
            ],
        ];

        Main::updateUpgradeLog(print_r($upgradeStats, true));

        set_transient('comfino_plugin_updated', 0);
    }

    /**
     * Check for available updates on GitHub (information only).
     *
     * This method only displays an admin notice when a newer version is available on GitHub.
     * It does NOT perform automatic updates to comply with WordPress plugin guidelines.
     * Only runs when WordPress automatic updates are disabled for this plugin.
     */
    private function check_github_version(): void
    {
        // Only check if automatic updates are disabled for this plugin.
        if (in_array(plugin_basename(__FILE__), (array) get_site_option(implode('_', ['auto', 'update', 'plugins']), []), true)) {
            return;
        }

        // Check once per day.
        $transientKey = 'comfino_github_version_check';
        $cachedData = get_transient($transientKey);

        if ($cachedData !== false) {
            return;
        }

        // Schedule the check to run in the background.
        add_action('admin_notices', [$this, 'display_github_version_notice']);

        // Perform version check asynchronously.
        add_action('admin_init', static function () use ($transientKey): void {
            if (get_transient($transientKey) !== false) {
                return;
            }

            // Fetch latest release info from GitHub API.
            $response = wp_remote_get('https://api.github.com/repos/comfino/WooCommerce/releases/latest', [
                'timeout' => 5,
                'headers' => ['Accept' => 'application/vnd.github.v3+json']
            ]);

            if (is_wp_error($response)) {
                // Cache failure for 1 hour.
                set_transient($transientKey, ['error' => true], HOUR_IN_SECONDS);

                return;
            }

            $release = json_decode(wp_remote_retrieve_body($response), true);

            if (!isset($release['tag_name'])) {
                set_transient($transientKey, ['error' => true], HOUR_IN_SECONDS);

                return;
            }

            set_transient(
                $transientKey,
                [
                    'github_version' => ltrim($release['tag_name'], 'v'),
                    'current_version' => PaymentGateway::VERSION,
                    'checked_at' => time()
                ],
                DAY_IN_SECONDS
            );
        }, 20);
    }
}

global $comfino_payment_gateway;

$comfino_payment_gateway = Comfino_Payment_Gateway::get_instance();

register_activation_hook(__FILE__, [$comfino_payment_gateway, 'activation_check']);
