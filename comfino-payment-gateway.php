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
 * WC tested up to: 9.0.2
 * WC requires at least: 3.0
 * Tested up to: 6.3.1
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
            wp_die($environmentWarning);
        }
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
     * @return string[]
     */
    public function plugin_action_links(array $links): array
    {
        $plugin_links = [
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=comfino') . '">' .
            __('Settings', 'comfino-payment-gateway') . '</a>',
        ];

        return array_merge($plugin_links, $links);
    }

    /**
     * @return string[]
     */
    public function filter_order_status(array $statuses): array
    {
        global $post;

        if (isset($post) && 'shop_order' === $post->post_type) {
            $order = wc_get_order($post->ID);

            if (isset($statuses['wc-cancelled']) && $order->get_payment_method() === 'comfino' && $order->has_status('completed')) {
                unset($statuses['wc-cancelled']);
            }
        }

        return $statuses;
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

    /**
     * Adds a Comfino gateway to the WooCommerce payment methods available for customer.
     *
     * @param array $methods
     *
     * @return array
     */
    public function add_gateway(array $methods): array
    {
        $methods[] = 'Comfino_Gateway';

        return $methods;
    }

    /**
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
    public function comfino_rest_load_cart(): void
    {
        if (version_compare(WC_VERSION, '3.6.0', '>=') && WC()->is_rest_api_request()) {
            if (empty($_SERVER['REQUEST_URI'])) {
                return;
            }

            $rest_prefix = 'comfino/offers';
            $req_uri = esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']));

            if (strpos($req_uri, $rest_prefix) === false) {
                return;
            }

            require_once WC_ABSPATH . 'includes/wc-cart-functions.php';
            require_once WC_ABSPATH . 'includes/wc-notice-functions.php';

            if (WC()->session === null) {
                $session_class = apply_filters('woocommerce_session_handler', 'WC_Session_Handler'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

                // Prefix session class with global namespace if not already namespaced.
                if (strpos($session_class, '\\') === false) {
                    $session_class = '\\' . $session_class;
                }

                WC()->session = new $session_class();
                WC()->session->init();
            }

            // For logged in customers, pull data from their account rather than the session which may contain incomplete data.
            if (WC()->customer === null) {
                if (is_user_logged_in()) {
                    WC()->customer = new WC_Customer(get_current_user_id());
                } else {
                    WC()->customer = new WC_Customer(get_current_user_id(), true);
                }

                // Customer should be saved during shutdown.
                add_action('shutdown', [WC()->customer, 'save'], 10);
            }

            // Load cart.
            if (WC()->cart === null) {
                WC()->cart = new WC_Cart();
            }
        }
    }
}

global $comfino_payment_gateway;

$comfino_payment_gateway = Comfino_Payment_Gateway::get_instance();

register_activation_hook(__FILE__, ['Comfino_Payment_Gateway', 'activation_check']);
