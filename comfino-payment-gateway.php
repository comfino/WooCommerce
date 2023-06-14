<?php
/*
 * Plugin Name: Comfino Payment Gateway
 * Plugin URI: https://github.com/comfino/WooCommerce.git
 * Description: Comfino (Comperia) - Comfino Payment Gateway for WooCommerce.
 * Version: 2.4.0
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

defined('ABSPATH') or exit;

class Comfino_Payment_Gateway
{
    const VERSION = '2.4.0';

    /**
     * @var Comfino_Payment_Gateway
     */
    private static $instance;

    public static function get_instance(): Comfino_Payment_Gateway
    {
        if (null === self::$instance) {
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
                ],
            ]);

            register_rest_route('comfino', '/configuration', [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [Core::class, 'get_configuration'],
                ],
                [
                    'methods' => WP_REST_Server::EDITABLE,
                    'callback' => [Core::class, 'update_configuration'],
                ]
            ]);
        });

        load_plugin_textdomain('comfino-payment-gateway', false, basename(__DIR__) . '/languages');

        \Comfino\Error_Logger::init();
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
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=comfino') . '">'.__('Settings', 'comfino-payment-gateway') . '</a>',
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

            if ($order->get_payment_method() === 'comfino' && $order->has_status('completed')) {
                if (isset($statuses['wc-cancelled'])) {
                    unset($statuses['wc-cancelled']);
                }
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
            $cg = new Comfino_Gateway();

            if ($cg->get_option('widget_enabled') === 'yes' && $cg->get_option('widget_key') !== '') {
                $code = $cg->get_option('widget_js_code');
                $sandbox_mode = 'yes' === $cg->get_option('sandbox_mode');

                if ($sandbox_mode) {
                    $code = str_replace('{WIDGET_SCRIPT_URL}', Comfino_Gateway::COMFINO_WIDGET_JS_SANDBOX, $code);
                } else {
                    $code = str_replace('{WIDGET_SCRIPT_URL}', Comfino_Gateway::COMFINO_WIDGET_JS_PRODUCTION, $code);
                }

                $code = str_replace(
                    [
                        '{WIDGET_KEY}',
                        '{WIDGET_PRICE_SELECTOR}',
                        '{WIDGET_TARGET_SELECTOR}',
                        '{WIDGET_TYPE}',
                        '{OFFER_TYPE}',
                        '{EMBED_METHOD}',
                        '{WIDGET_PRICE_OBSERVER_LEVEL}',
                        '{WIDGET_PRICE_OBSERVER_SELECTOR}',
                    ],
                    [
                        $cg->get_option('widget_key'),
                        html_entity_decode($cg->get_option('widget_price_selector')),
                        html_entity_decode($cg->get_option('widget_target_selector')),
                        $cg->get_option('widget_type'),
                        $cg->get_option('widget_offer_type'),
                        $cg->get_option('widget_embed_method'),
                        $cg->get_option('widget_price_observer_level'),
                        $cg->get_option('widget_price_observer_selector'),
                    ],
                    $code
                );

                echo '<script>' . str_replace(['&#039;', '&gt;', '&amp;'], ["'", '>', '&'], esc_html($code)) . '</script>';
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
