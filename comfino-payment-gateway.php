<?php
/*
 * Plugin Name: Comfino Payment Gateway
 * Plugin URI: https://github.com/comfino/WooCommerce.git
 * Description: Comfino Payment Gateway for WooCommerce.
 * Version: 4.2.3
 * Author: Comfino
 * Author URI: https://github.com/comfino
 * Domain Path: /languages
 * Text Domain: comfino-payment-gateway
 * WC tested up to: 9.9.5
 * WC requires at least: 3.0
 * Tested up to: 6.8
 * Requires at least: 5.0
 * Requires PHP: 7.1
 * License: GPLv3
 *
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Configuration\ConfigManager;
use Comfino\PaymentGateway;
use Comfino\PluginShared\CacheManager;

if (!defined('ABSPATH')) {
    exit;
}

class Comfino_Payment_Gateway
{
    /** @var array */
    public $notices = [];

    /** @var Comfino_Payment_Gateway */
    private static $instance;

    public static function get_instance(): Comfino_Payment_Gateway
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

        // Basic hooks
        add_action('init', [$this, 'init']);
        add_action('admin_init', [$this, 'check_environment']);
        add_action('admin_notices', [$this, 'admin_notices'], 15);
        add_action('plugins_loaded', function (): void {
            if (get_transient('comfino_plugin_updated')) {
                $this->upgrade_plugin();
            }
        });

        // Upgrade hook
        add_action('upgrader_process_complete', static function (WP_Upgrader $upgrader, array $options): void {
            $comfinoPluginPathName = plugin_basename(__FILE__);

            if ($options['action'] === 'update' && $options['type'] === 'plugin') {
                // Plugin updated.
                if (isset($options['plugins'])) {
                    // Bulk plugins update (update page)
                    foreach($options['plugins'] as $pluginPathName) {
                        if ($pluginPathName === $comfinoPluginPathName) {
                            // Comfino plugin updated.
                            set_transient('comfino_plugin_updated', 1);
                            set_transient('comfino_plugin_prev_version', PaymentGateway::VERSION);
                            set_transient('comfino_plugin_updated_at', time());

                            break;
                        }
                    }
                } elseif (isset($options['plugin'])) {
                    // Normal plugin update or via auto update
                    if ($options['plugin'] === $comfinoPluginPathName) {
                        // Comfino plugin updated.
                        set_transient('comfino_plugin_updated', 1);
                        set_transient('comfino_plugin_prev_version', PaymentGateway::VERSION);
                        set_transient('comfino_plugin_updated_at', time());
                    }
                }
            }
        }, 10, 2);

        // Overwrite hook
        add_action('upgrader_overwrote_package', static function (string $package, array $data, string $package_type): void {
            if ($package_type === 'plugin' && $data['Name'] === 'Comfino Payment Gateway') {
                // Comfino plugin updated.
                set_transient('comfino_plugin_updated', 1);
                set_transient('comfino_plugin_prev_version', PaymentGateway::VERSION);
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
            if (strpos($handle, 'comfino') !== 0) {
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
            if (isset($attributes['id']) && strpos($attributes['id'], 'comfino') === 0) {
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

        Comfino\Main::setPluginDirectory(__DIR__);
        Comfino\Main::setPluginFile(__FILE__);
    }

    /**
     * Automatically disables the plugin on activation if it doesn't meet minimum requirements.
     */
    public function activation_check(): void
    {
        $environmentWarning = Comfino\Main::getEnvironmentWarning(true);

        if ($environmentWarning) {
            deactivate_plugins(plugin_basename(__FILE__));
            /** @noinspection ForgottenDebugOutputInspection */
            wp_die(wp_kses_post($environmentWarning));
        }
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
        Comfino\Main::init();
    }

    /**
     * @return string|bool
     */
    public function check_environment()
    {
        $environmentWarning = Comfino\Main::getEnvironmentWarning();

        if ($environmentWarning && is_plugin_active(plugin_basename(__FILE__))) {
            deactivate_plugins(plugin_basename(__FILE__));
            $this->add_admin_notice('bad_environment', 'error', $environmentWarning);

            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
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

        foreach ($this->notices as $noticeKey => $notice) {
            echo '<div class="' . esc_attr(sanitize_html_class($notice['class'])) . '"><p>';
            echo wp_kses($notice['message'], ['a' => ['href' => []]]);
            echo "</p></div>";
        }
    }

    public function upgrade_plugin(): void
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

        ConfigManager::updateConfigurationValue('COMFINO_WIDGET_TYPE', 'extended-modal');
        ConfigManager::updateConfigurationValue('COMFINO_API_CONNECT_TIMEOUT', 3);
        ConfigManager::updateConfigurationValue('COMFINO_API_TIMEOUT', 5);

        /* 4.2.3 */
        ConfigManager::updateWidgetCode();

        ConfigManager::updateConfigurationValue('COMFINO_WIDGET_TYPE', 'standard');
        ConfigManager::updateConfigurationValue('COMFINO_WIDGET_SHOW_PROVIDER_LOGOS', false);
        ConfigManager::updateConfigurationValue('COMFINO_NEW_WIDGET_ACTIVE', true);

        if (is_array($catFilterAvailProdTypes = ConfigManager::getConfigurationValue('COMFINO_CAT_FILTER_AVAIL_PROD_TYPES'))
            && (!in_array('COMPANY_BNPL', $catFilterAvailProdTypes, true) || !in_array('COMPANY_INSTALLMENTS', $catFilterAvailProdTypes, true))
        ) {
            ConfigManager::updateConfigurationValue(
                'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES',
                ['INSTALLMENTS_ZERO_PERCENT', 'PAY_LATER', 'COMPANY_BNPL', 'COMPANY_INSTALLMENTS', 'LEASING']
            );
        }

        CacheManager::getCachePool()->clear();

        set_transient('comfino_plugin_updated', 0);
    }

    public function get_plugin_update_details(): array
    {
        static $updateDetails = null;

        if ($updateDetails === null) {
            $updateDetails = [
                'comfino_plugin_updated' => get_transient('comfino_plugin_updated'),
                'comfino_plugin_prev_version' => get_transient('comfino_plugin_prev_version'),
                'comfino_plugin_updated_at' => get_transient('comfino_plugin_updated_at'),
            ];
        }

        return $updateDetails;
    }
}

global $comfino_payment_gateway;

$comfino_payment_gateway = Comfino_Payment_Gateway::get_instance();

register_activation_hook(__FILE__, [$comfino_payment_gateway, 'activation_check']);
