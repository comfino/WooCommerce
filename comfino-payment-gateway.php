<?php
/*
 * Plugin Name: Comfino payment gateway
 * Plugin URI: https://github.com/comfino/WooCommerce.git
 * Description: Comfino payment gateway for WooCommerce.
 * Version: 4.1.2
 * Author: Comfino
 * Author URI: https://github.com/comfino
 * Domain Path: /languages
 * Text Domain: comfino-payment-gateway
 * WC tested up to: 9.3.3
 * WC requires at least: 3.0
 * Tested up to: 6.6.2
 * Requires at least: 5.0
 * Requires PHP: 7.1
 *
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

use Comfino\PaymentGateway;

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

        add_action('init', [$this, 'init']);
        add_action('admin_init', [$this, 'check_environment']);
        add_action('admin_notices', [$this, 'admin_notices'], 15);

        // Add a Comfino gateway to the WooCommerce payment methods available for customer.
        add_filter('woocommerce_payment_gateways', static function (array $methods): array {
            $methods[] = PaymentGateway::class;

            return $methods;
        });

        // Declare compatibility with WooCommerce HPOS and Payment Blocks.
        add_action('before_woocommerce_init', static function () {
            if (class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__);
                Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__);
            }
        });

        // Register integration hook for WooCommerce Cart and Checkout Blocks.
        add_action('woocommerce_blocks_loaded', static function () {
            if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
                add_action(
                    'woocommerce_blocks_payment_method_type_registration',
                    static function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $paymentMethodRegistry) {
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
        $environment_warning = Comfino\Main::getEnvironmentWarning(true);

        if ($environment_warning) {
            deactivate_plugins(plugin_basename(__FILE__));
            /** @noinspection ForgottenDebugOutputInspection */
            wp_die($environment_warning);
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
        $environment_warning = Comfino\Main::getEnvironmentWarning();

        if ($environment_warning && is_plugin_active(plugin_basename(__FILE__))) {
            deactivate_plugins(plugin_basename(__FILE__));
            $this->add_admin_notice('bad_environment', 'error', $environment_warning);

            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }
        }

        return $environment_warning;
    }

    public function add_admin_notice(string $slug, string $class, string $message): void
    {
        $this->notices[$slug] = ['class' => $class, 'message' => $message];
    }

    public function admin_notices(): void
    {
        foreach ($this->notices as $notice_key => $notice) {
            echo '<div class="' . esc_attr(sanitize_html_class($notice['class'])) . '"><p>';
            echo wp_kses($notice['message'], ['a' => ['href' => []]]);
            echo "</p></div>";
        }
    }
}

global $comfino_payment_gateway;

$comfino_payment_gateway = Comfino_Payment_Gateway::get_instance();

register_activation_hook(__FILE__, [$comfino_payment_gateway, 'activation_check']);
