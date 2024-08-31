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
        add_action('init', [$this, 'init']);
        add_action('admin_init', [$this, 'check_environment']);
        add_action('admin_notices', [$this, 'admin_notices'], 15);
    }

    /**
     * Automatically disables the plugin on activation if it doesn't meet minimum requirements.
     */
    public function activation_check(): void
    {
        if (!class_exists('\Comfino\Main')) {
            require_once __DIR__ . '/src/Main.php';
        }

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
}

global $comfino_payment_gateway;

$comfino_payment_gateway = Comfino_Payment_Gateway::get_instance();

register_activation_hook(__FILE__, [$comfino_payment_gateway, 'activation_check']);
