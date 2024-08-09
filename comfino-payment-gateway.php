<?php
/*
 * Plugin Name: Comfino Payment Gateway
 * Plugin URI: https://github.com/comfino/WooCommerce.git
 * Description: Comfino Payment Gateway for WooCommerce.
 * Version: 4.0.0
 * Author: Comfino
 * Author URI: https://github.com/comfino
 * Domain Path: /languages
 * Text Domain: comfino-payment-gateway
 * WC tested up to: 9.1.4
 * WC requires at least: 3.0
 * Tested up to: 6.6.1
 * Requires at least: 5.0
 * Requires PHP: 7.1
 *
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Comfino_Payment_Gateway
{
    public const VERSION = '4.0.0';

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
        add_action('admin_init', [$this, 'check_environment']);
        add_action('admin_notices', [$this, 'admin_notices'], 15);
        add_action('plugins_loaded', [$this, 'init']);
    }

    /**
     * Automatically disables the plugin on activation if it doesn't meet minimum requirements.
     */
    public static function activation_check(): void
    {
        if (!class_exists('\Comfino\Main')) {
            require_once __DIR__ . '/src/Main.php';
        }

        $environmentWarning = Comfino\Main::getEnvironmentWarning(true);

        if ($environmentWarning) {
            deactivate_plugins(plugin_basename(__FILE__));
            /** @noinspection ForgottenDebugOutputInspection */
            wp_die($environmentWarning);
        }
    }

    /**
     * Plugin URL.
     */
    public static function plugin_url(): string
    {
        return untrailingslashit(plugins_url('/', __FILE__));
    }

    /**
     * Plugin absolute path.
     */
    public static function plugin_abspath(): string
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

        if (is_readable(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
        }

        // Initialize Comfino plugin.
        Comfino\Main::init($this, __DIR__, __FILE__);
    }

    /**
     * @return string|bool
     */
    public function check_environment()
    {
        if (!class_exists('\Comfino\Main')) {
            require_once __DIR__ . '/src/Main.php';
        }

        $environment_warning = Comfino\Main::getEnvironmentWarning();

        if ($environment_warning && is_plugin_active(plugin_basename( __FILE__))) {
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

    /**
     * Render widget.
     *
     * @return void
     */
    public function render_widget(): void
    {
        global $product;

        if (is_single() && is_product()) {
            $comfino = new Comfino_Gateway();

            if ($product instanceof WC_Product) {
                $product_id = $product->get_id();
            } else {
                $product_id = get_the_ID();
            }

            if ($comfino->get_option('widget_enabled') === 'yes' && $comfino->get_option('widget_key') !== '') {
                echo Core::get_widget_init_code($comfino, !empty($product_id) ? $product_id : null);
            }
        }
    }
}

global $comfino_payment_gateway;

$comfino_payment_gateway = Comfino_Payment_Gateway::get_instance();

register_activation_hook(__FILE__, ['Comfino_Payment_Gateway', 'activation_check']);
