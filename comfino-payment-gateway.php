<?php
/*
 * Plugin Name: Comfino Payment Gateway
 * Plugin URI: https://github.com/comfino/WooCommerce.git
 * Description: Comfino (Comperia) - Comfino Payment Gateway for WooCommerce.
 * Version: 2.2.1
 * Author: Comfino (Comperia)
 * Author URI: https://github.com/comfino
 * Domain Path: /languages
 * Text Domain: comfino
 * Requires at least: 5.6
 * Requires PHP: 7.0
 *
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
*/

defined('ABSPATH') or exit;

class ComfinoPaymentGateway
{
    public const VERSION = '2.2.1';

    /**
     * @var ComfinoPaymentGateway
     */
    private static $instance;

    /**
     * @return ComfinoPaymentGateway
     */
    public static function get_instance(): ComfinoPaymentGateway
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * ComfinoPaymentGateway constructor.
     */
    public function __construct()
    {
        add_action('plugins_loaded', [$this, 'init']);
    }

    /**
     * Init
     */
    public function init(): void
    {
        if ($this->check_environment()) {
            return;
        }

        require_once __DIR__ . '/includes/comfino-gateway.php';

        add_filter('woocommerce_payment_gateways', [$this, 'add_gateway']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'plugin_action_links']);
        add_filter('wc_order_statuses', [$this, 'filter_order_status']);

        add_action('woocommerce_single_product_summary', [$this, 'render_widget']);

        load_plugin_textdomain('comfino', false, basename(__DIR__) . '/languages');
    }

    /**
     * @return false|string
     */
    private function check_environment()
    {
        if (PHP_VERSION_ID < 70100) {
            $message = __(' The minimum PHP version required for Comfino is %s. You are running %s.', 'comfino');

            return sprintf($message, '7.1.0', PHP_VERSION);
        }

        if (!defined('WC_VERSION')) {
            return __('WooCommerce needs to be activated.', 'woocommerce-comfino');
        }

        if (version_compare(WC_VERSION, '3.0.0', '<')) {
            $message = __('The minimum WooCommerce version required for Comfino is %s. You are running %s.', 'comfino');

            return sprintf($message, '3.0.0', WC_VERSION);
        }

        return false;
    }

    /**
     * @param $links
     *
     * @return array|string[]
     */
    public function plugin_action_links($links): array
    {
        $plugin_links = [
            '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=comfino') . '">' . __('Settings', 'comfino') . '</a>',
        ];

        return array_merge($plugin_links, $links);
    }

    /**
     * @param array $statuses
     *
     * @return array
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
     * Render widget
     *
     * @return void
     */
    public function render_widget(): void
    {
        if (is_single()) {
            $cg = new Comfino_Gateway();

            if ($cg->get_option('widget_enabled') && $cg->get_option('widget_key') !== '') {
                $code = $cg->get_option('widget_js_code');
                $sandbox_mode = 'yes' === $cg->get_option('sandbox_mode');

                if ($sandbox_mode) {
                    $code = str_replace('{WIDGET_SCRIPT_URL}', Comfino_Gateway::COMFINO_WIDGET_JS_SANDBOX, $code);
                } else {
                    $code = str_replace('{WIDGET_SCRIPT_URL}', Comfino_Gateway::COMFINO_WIDGET_JS_PRODUCTION, $code);
                }

                $code = str_replace('{WIDGET_KEY}', $cg->get_option('widget_key'), $code);
                $code = str_replace('{WIDGET_PRICE_SELECTOR}', $cg->get_option('widget_price_selector'), $code);
                $code = str_replace('{WIDGET_TARGET_SELECTOR}', $cg->get_option('widget_target_selector'), $code);
                $code = str_replace('{WIDGET_TYPE}', $cg->get_option('widget_type'), $code);
                $code = str_replace('{OFFER_TYPE}', $cg->get_option('widget_offer_type'), $code);
                $code = str_replace('{EMBED_METHOD}', $cg->get_option('widget_embed_method'), $code);

                echo '<script>' . $code . '</script>';
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

ComfinoPaymentGateway::get_instance();
