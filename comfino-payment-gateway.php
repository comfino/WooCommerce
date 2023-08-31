<?php
/*
 * Plugin Name: Comfino Payment Gateway
 * Plugin URI: https://github.com/comfino/WooCommerce.git
 * Description: Comfino (Comperia) - Comfino Payment Gateway for WooCommerce.
 * Version: 3.1.1
 * Author: Comfino (Comperia)
 * Author URI: https://github.com/comfino
 * Domain Path: /languages
 * Text Domain: comfino-payment-gateway
 * Requires at least: 5.4
 * Requires PHP: 7.0
 *
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

use Comfino\Core;
use Comfino\Error_Logger;

defined('ABSPATH') or exit;

class Comfino_Payment_Gateway
{
    const VERSION = '3.1.1';

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
        require_once __DIR__ . '/includes/comfino-error-logger.php';
        require_once __DIR__ . '/includes/comfino-core.php';
        require_once __DIR__ . '/includes/comfino-gateway.php';

        add_filter('woocommerce_payment_gateways', [$this, 'add_gateway']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_action_links']);
        add_filter('wc_order_statuses', [$this, 'filter_order_status']);

        add_action('wp_head', [$this, 'render_widget']);

        add_action('rest_api_init', static function () {
            register_rest_route('comfino', '/notification', [
                [
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => [Core::class, 'process_notification'],
                    'permission_callback' => '__return_true',
                ],
            ]);

            register_rest_route('comfino', '/offers', [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [Core::class, 'get_offers'],
                    'permission_callback' => '__return_true',
                ],
            ]);

            register_rest_route('comfino', '/configuration', [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [Core::class, 'get_configuration'],
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
        if (is_single()) {
            $comfino = new Comfino_Gateway();

            if ($comfino->get_option('widget_enabled') === 'yes' && $comfino->get_option('widget_key') !== '') {
                echo Core::get_widget_init_code($comfino);
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
}

Comfino_Payment_Gateway::get_instance();
