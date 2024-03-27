<?php
/*
 * Plugin Name: Comfino Payment Gateway
 * Plugin URI: https://github.com/comfino/WooCommerce.git
 * Description: Comfino (Comperia) - Comfino Payment Gateway for WooCommerce.
 * Version: 3.4.0
 * Author: Comfino (Comperia)
 * Author URI: https://github.com/comfino
 * Domain Path: /languages
 * Text Domain: comfino-payment-gateway
 * WC tested up to: 8.1.1
 * WC requires at least: 3.0
 * Tested up to: 6.3.1
 * Requires at least: 5.0
 * Requires PHP: 7.0
 *
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

use Comfino\Core;
use Comfino\Error_Logger;

defined('ABSPATH') or exit;

class Comfino_Payment_Gateway
{
    const VERSION = '3.4.0';

    /**
     * @var Comfino_Payment_Gateway
     */
    private static $instance;

    public static function get_instance(): Comfino_Payment_Gateway
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'init']);
    }

    /**
     * Plugin initialization.
     */
    public function init()
    {
        if ($this->check_environment()) {
            return;
        }

        require_once __DIR__ . '/includes/comfino-config-manager.php';
        require_once __DIR__ . '/includes/comfino-core.php';
        require_once __DIR__ . '/includes/comfino-api-client.php';
        require_once __DIR__ . '/includes/comfino-gateway.php';
        require_once __DIR__ . '/includes/comfino-error-logger.php';

        add_filter('woocommerce_payment_gateways', [$this, 'add_gateway']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_action_links']);
        add_filter('wc_order_statuses', [$this, 'filter_order_status']);

        add_action('wp_loaded', [$this, 'comfino_rest_load_cart'], 5);
        add_action('wp_head', [$this, 'render_widget']);

        add_action('rest_api_init', static function () {
            register_rest_route('comfino', '/notification', [
                [
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => [Core::class, 'process_notification'],
                    'permission_callback' => '__return_true',
                ],
            ]);

            register_rest_route('comfino', '/availableoffertypes(?:/(?P<product_id>\d+))?', [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [Core::class, 'get_available_offer_types'],
                    'args' => ['product_id' => ['sanitize_callback' => 'absint']],
                    'permission_callback' => '__return_true',
                ],
            ]);

            register_rest_route('comfino', '/configuration(?:/(?P<vkey>[a-f0-9]+))?', [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [Core::class, 'get_configuration'],
                    'args' => ['vkey' => ['sanitize_callback' => 'sanitize_key']],
                    'permission_callback' => '__return_true',
                ],
                [
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => [Core::class, 'update_configuration'],
                    'permission_callback' => '__return_true',
                ]
            ]);
        });

        load_plugin_textdomain('comfino-payment-gateway', false, basename(__DIR__) . '/languages');

        Error_Logger::init();
    }

    /**
     * @return false|string
     */
    private function check_environment()
    {
        if (PHP_VERSION_ID < 70000) {
            $message = __('The minimum PHP version required for Comfino is %s. You are running %s.', 'comfino-payment-gateway');

            return sprintf($message, '7.0.0', PHP_VERSION);
        }

        if (!defined('WC_VERSION')) {
            return __('WooCommerce needs to be activated.', 'comfino-payment-gateway');
        }

        if (version_compare(WC_VERSION, '3.0.0', '<')) {
            $message = __('The minimum WooCommerce version required for Comfino is %s. You are running %s.', 'comfino-payment-gateway');

            return sprintf($message, '3.0.0', WC_VERSION);
        }

        return false;
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
    public function render_widget()
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
     * Add the Comfino Gateway to WooCommerce
     *
     * @param $methods
     *
     * @return array
     */
    public function add_gateway($methods): array
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
    public function comfino_rest_load_cart()
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

Comfino_Payment_Gateway::get_instance();
