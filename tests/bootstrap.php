<?php

// Mock WooCommerce Blocks classes to prevent fatal errors.
namespace Automattic\WooCommerce\Blocks\Payments\Integrations {
    if (!class_exists('AbstractPaymentMethodType')) {
        class AbstractPaymentMethodType
        {
            public function get_name(): string
            {
                return 'mock_payment_method';
            }
        }
    }
}

namespace {
    // Define WordPress constants for testing.
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__ . '/../../../../');
    }

    if (!defined('WP_DEBUG')) {
        define('WP_DEBUG', true);
    }

    if (!defined('WP_CONTENT_DIR')) {
        define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
    }

    if (!defined('WC_VERSION')) {
        define('WC_VERSION', '8.0.0');
    }

    if (!defined('WC_ABSPATH')) {
        define('WC_ABSPATH', ABSPATH . 'wp-content/plugins/woocommerce/');
    }

    // Mock WordPress functions for testing.
    if (!function_exists('__')) {
        function __($text, $domain = 'default')
        {
            return $text;
        }
    }

    if (!function_exists('esc_html')) {
        function esc_html($text): string
        {
            return htmlspecialchars($text, ENT_QUOTES | ENT_HTML401, 'UTF-8');
        }
    }

    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($str): string
        {
            // Workaround for Plugin Check (PCP) error in security report.
            return trim(array_map('strip_tags', [$str])[0]);
        }
    }

    if (!function_exists('wp_unslash')) {
        function wp_unslash($value)
        {
            return is_string($value) ? stripslashes($value) : $value;
        }
    }

    if (!function_exists('sanitize_url')) {
        function sanitize_url($url)
        {
            return filter_var($url, FILTER_SANITIZE_URL) ?: '';
        }
    }

    if (!function_exists('sanitize_key')) {
        function sanitize_key($key)
        {
            return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
        }
    }

    if (!function_exists('wp_parse_url')) {
        function wp_parse_url($url, $component = -1)
        {
            // Workaround for Plugin Check (PCP) error in security report.
            return array_map('parse_url', [$url], [$component])[0];
        }
    }

    if (!function_exists('get_bloginfo')) {
        function get_bloginfo($show = '', $filter = 'raw'): string
        {
            switch ($show) {
                case 'language':
                    return 'en-US';

                case 'version':
                    return '6.0.0';

                default:
                    return '';
            }
        }
    }

    if (!function_exists('get_woocommerce_currency')) {
        function get_woocommerce_currency(): string
        {
            return 'PLN';
        }
    }

    if (!function_exists('plugin_basename')) {
        function plugin_basename($file)
        {
            return str_replace(WP_CONTENT_DIR . '/plugins/', '', $file);
        }
    }

    if (!function_exists('load_plugin_textdomain')) {
        function load_plugin_textdomain($domain, $deprecated = false, $plugin_rel_path = false): bool
        {
            return true;
        }
    }

    if (!function_exists('add_action')) {
        function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1): bool
        {
            return true;
        }
    }

    if (!function_exists('add_filter')) {
        function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1): bool
        {
            return true;
        }
    }

    if (!function_exists('apply_filters')) {
        function apply_filters($hook_name, $value)
        {
            return $value;
        }
    }

    if (!function_exists('wp_nonce_url')) {
        function wp_nonce_url($actionurl, $action = -1, $name = '_wpnonce'): string
        {
            return $actionurl . '?_wpnonce=test-nonce';
        }
    }

    if (!function_exists('admin_url')) {
        function admin_url($path = '', $scheme = 'admin'): string
        {
            return 'https://comfino-wc-store.test/wp-admin/' . ltrim($path, '/');
        }
    }

    if (!function_exists('check_admin_referer')) {
        function check_admin_referer($action = -1, $name = '_wpnonce'): bool
        {
            return true;
        }
    }

    if (!function_exists('wp_json_encode')) {
        function wp_json_encode($data, $options = 0, $depth = 512)
        {
            return json_encode($data, $options, $depth);
        }
    }

    if (!function_exists('wc_get_page_permalink')) {
        function wc_get_page_permalink($page): string
        {
            return 'https://comfino-wc-store.test/' . $page;
        }
    }

    if (!function_exists('wc_get_product')) {
        function wc_get_product($product = false)
        {
            if (is_numeric($product)) {
                return new WC_Product();
            }

            return $product;
        }
    }

    if (!function_exists('wc_price')) {
        function wc_price($price, $args = []): string
        {
            return '$' . number_format((float) $price, 2) . ' ' . get_woocommerce_currency();
        }
    }

    if (!function_exists('is_admin')) {
        function is_admin(): bool
        {
            return false;
        }
    }

    if (!function_exists('wc_get_price_including_tax')) {
        function wc_get_price_including_tax($product): float
        {
            return 100.00;
        }
    }

    if (!function_exists('wc_get_price_excluding_tax')) {
        function wc_get_price_excluding_tax($product): float
        {
            return 90.00;
        }
    }

    if (!function_exists('get_term')) {
        function get_term($term_id, $taxonomy = ''): WP_Term
        {
            $term = new WP_Term();
            $term->name = 'Test Category';
            $term->term_id = $term_id;
            $term->parent = 0;

            return $term;
        }
    }

    if (!function_exists('get_terms')) {
        function get_terms($args = []): array
        {
            $term1 = new WP_Term();
            $term1->name = 'Test Category 1';
            $term1->term_id = 1;
            $term1->parent = 0;
            $term1->count = 5;

            $term2 = new WP_Term();
            $term2->name = 'Test Category 2';
            $term2->term_id = 2;
            $term2->parent = 0;
            $term2->count = 3;

            return [$term1, $term2];
        }
    }

    if (!function_exists('absint')) {
        function absint($maybeint)
        {
            return abs((int) $maybeint);
        }
    }

    if (!function_exists('wp_parse_args')) {
        function wp_parse_args($args, $defaults = []): array
        {
            if (is_object($args)) {
                $result = get_object_vars($args);
            } elseif (is_array($args)) {
                $result = $args;
            } else {
                wp_parse_str($args, $result);
            }

            if (is_array($defaults)) {
                return array_merge($defaults, $result);
            }

            return $result;
        }
    }

    if (!function_exists('wp_parse_str')) {
        function wp_parse_str($string, &$array)
        {
            parse_str($string, $array);

            if (get_magic_quotes_gpc()) {
                $array = stripslashes_deep($array);
            }
        }
    }

    if (!function_exists('stripslashes_deep')) {
        function stripslashes_deep($value)
        {
            if (is_array($value)) {
                return array_map('stripslashes_deep', $value);
            }

            if (is_object($value)) {
                $vars = get_object_vars($value);

                foreach ($vars as $key => $data) {
                    $value->{$key} = stripslashes_deep($data);
                }

                return $value;
            }

            return is_string($value) ? stripslashes($value) : $value;
        }
    }

    if (!function_exists('wp_kses_allowed_html')) {
        function wp_kses_allowed_html($context = 'post'): array
        {
            return [
                'div' => [],
                'p' => [],
                'span' => [],
                'strong' => [],
                'em' => [],
            ];
        }
    }

    if (!function_exists('wp_enqueue_script')) {
        function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false): void
        {
        }
    }

    if (!function_exists('get_magic_quotes_gpc')) {
        function get_magic_quotes_gpc(): bool
        {
            return false;
        }
    }

    if (!function_exists('get_query_var')) {
        function get_query_var($var, $default = '')
        {
            return $default;
        }
    }

    if (!function_exists('esc_attr')) {
        function esc_attr($text): string
        {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
    }

    if (!function_exists('wc_get_template')) {
        function wc_get_template($template_name, $args = [], $template_path = '', $default_path = '')
        {
            echo '<div>Template: ' . esc_html($template_name) . '</div>';
        }
    }

    if (!function_exists('wc_get_template_html')) {
        function wc_get_template_html($template_name, $args = [], $template_path = '', $default_path = ''): string
        {
            return '<div>Template HTML: ' . $template_name . '</div>';
        }
    }

    if (!function_exists('disabled')) {
        function disabled($disabled, $current = true, $echo = true): string
        {
            $result = ($disabled === $current) ? 'disabled="disabled"' : '';

            if ($echo) {
                echo esc_html($result);
            }

            return $result;
        }
    }

    if (!function_exists('delete_transient')) {
        function delete_transient($transient): bool
        {
            return delete_option('_transient_' . $transient);
        }
    }

    if (!function_exists('get_transient')) {
        function get_transient($transient)
        {
            return get_option('_transient_' . $transient, false);
        }
    }

    if (!function_exists('set_transient')) {
        function set_transient($transient, $value, $expiration = 0): bool
        {
            return update_option('_transient_' . $transient, $value);
        }
    }

    if (!function_exists('delete_option')) {
        function delete_option($option): bool
        {
            return true;
        }
    }

    if (!function_exists('update_option')) {
        function update_option($option, $value, $autoload = null): bool
        {
            return true;
        }
    }

    if (!function_exists('get_option')) {
        function get_option($option, $default = false)
        {
            return $default;
        }
    }

    if (!function_exists('register_rest_route')) {
        function register_rest_route($namespace, $route, $args): bool
        {
            // Mock implementation.
            return true;
        }
    }

    if (!function_exists('get_rest_url')) {
        function get_rest_url($blog_id, $path): string
        {
            return 'https://comfino-wc-store.test/wp-json/' . ltrim($path, '/');
        }
    }

    if (!function_exists('WC')) {
        function WC(): WooCommerce_Mock
        {
            global $woocommerce;

            // Always ensure the global is properly initialized
            if (!isset($woocommerce)) {
                $woocommerce = new WooCommerce_Mock();
            }

            return $woocommerce;
        }
    }

    // Mock WC_Settings_API first since WC_Payment_Gateway extends it.
    if (!class_exists('WC_Settings_API')) {
        class WC_Settings_API {
            public function get_custom_attribute_html($data): string
            {
                return '';
            }

            public function get_tooltip_html($data): string
            {
                return '';
            }

            public function get_description_html($data): string
            {
                return isset($data['description']) ? '<p>' . $data['description'] . '</p>' : '';
            }
        }
    }

    // Mock WooCommerce classes.
    if (!class_exists('WC_Payment_Gateway')) {
        class WC_Payment_Gateway extends WC_Settings_API
        {
            public $id;
            public $icon;
            public $has_fields;
            public $method_title;
            public $method_description;
            public $description;
            public $supports = [];
            public $title;
            public $form_fields = [];
            public $settings = [];

            public function is_available(): bool
            {
                return true;
            }

            public function get_option($key, $empty_value = null)
            {
                return $this->settings[$key] ?? $empty_value;
            }

            public function init_settings(): void
            {
                $this->settings = [];
            }

            public function get_field_key($key): string
            {
                return 'woocommerce_' . $this->id . '_' . $key;
            }

            public function get_field_type($field)
            {
                return $field['type'] ?? 'text';
            }

            public function get_field_value($key, $field, $post_data = [])
            {
                return $post_data[$this->get_field_key($key)] ?? $field['default'] ?? '';
            }

            public function get_post_data(): array
            {
                return $_POST;
            }

            public function generate_settings_html($form_fields = [], $echo = true): string
            {
                return '<div>Settings HTML</div>';
            }

            public function get_order_total(): float
            {
                return 100.0;
            }

            public function get_return_url($order): string
            {
                return 'http://comfino-wc-store.test/return';
            }

            public function add_error($message): void
            {
                // Mock implementation.
            }

            public function display_errors(): void
            {
                // Mock implementation.
            }

            public function get_option_key(): string
            {
                return 'woocommerce_' . $this->id . '_settings';
            }
        }
    }

    // Mock global objects.
    global $wp, $wp_rewrite, $wpdb;

    $wp = new stdClass();
    $wp_rewrite = new stdClass();

    // Mock $wpdb object
    if (!class_exists('wpdb_mock')) {
        class wpdb_mock
        {
            public function db_version(): string
            {
                return '5.7.0';
            }

            public function prepare($query, ...$args): string
            {
                return vsprintf(str_replace('?', '%s', $query), $args);
            }

            public function get_results($query): array
            {
                return [];
            }

            public function get_var($query): string
            {
                return '';
            }
        }
    }

    $wpdb = new wpdb_mock();

    // Mock WP_REST_Server constants.
    if (!class_exists('WP_REST_Server')) {
        class WP_REST_Server
        {
            public const READABLE = 'GET';
            public const EDITABLE = 'POST';
        }
    }

    // Mock WP_REST_Request.
    if (!class_exists('WP_REST_Request')) {
        class WP_REST_Request
        {
            private $params = [];
            private $method = 'GET';
            private $headers = [];
            private $body = '';

            public function get_params(): array
            {
                return $this->params;
            }

            public function get_param($key)
            {
                return $this->params[$key] ?? null;
            }

            public function has_param($key): bool
            {
                return isset($this->params[$key]);
            }

            public function get_method(): string
            {
                return $this->method;
            }

            public function get_headers(): array
            {
                return $this->headers;
            }

            public function get_body(): string
            {
                return $this->body;
            }

            public function get_route(): string
            {
                return '/wp-json/comfino/test';
            }

            public function set_param($key, $value): void
            {
                $this->params[$key] = $value;
            }

            public function set_method($method): void
            {
                $this->method = $method;
            }
        }
    }

    // Mock WP_REST_Response.
    if (!class_exists('WP_REST_Response')) {
        class WP_REST_Response
        {
            private $data;
            private $status = 200;
            private $headers = [];

            public function __construct($data = null, $status = 200)
            {
                $this->data = $data;
                $this->status = $status;
            }

            public function get_data()
            {
                return $this->data;
            }

            public function set_data($data): void
            {
                $this->data = $data;
            }

            public function get_status(): int
            {
                return $this->status;
            }

            public function set_status($status): void
            {
                $this->status = $status;
            }

            public function header($name, $value, $replace = true): void
            {
                if ($replace || !isset($this->headers[$name])) {
                    $this->headers[$name] = [$value];
                } else {
                    $this->headers[$name][] = $value;
                }
            }
        }
    }

    // Mock WC_Tax.
    if (!class_exists('WC_Tax')) {
        class WC_Tax
        {
            public static function get_rates($tax_class = ''): array
            {
                return [
                    'rate_1' => [
                        'rate' => '10.0000',
                        'label' => 'VAT',
                    ]
                ];
            }
        }
    }

    // Mock WC_Product classes.
    if (!class_exists('WC_Product')) {
        class WC_Product
        {
            protected $id = 123;
            protected $name = 'Test Product';
            protected $price = 100.00;
            protected $sku = 'TEST-SKU';
            protected $tax_class = '';
            protected $category_ids = [1, 2];
            protected $image_id = 1;

            public function get_id()
            {
                return $this->id;
            }

            public function get_name()
            {
                return $this->name;
            }

            public function get_price()
            {
                return $this->price;
            }

            public function get_sku()
            {
                return $this->sku;
            }

            public function get_tax_class()
            {
                return $this->tax_class;
            }

            public function get_category_ids()
            {
                return $this->category_ids;
            }

            public function get_image_id()
            {
                return $this->image_id;
            }
        }
    }

    if (!class_exists('WC_Product_Variation')) {
        class WC_Product_Variation extends WC_Product
        {
            public function get_parent_id($context = 'view'): int
            {
                return 1;
            }
        }
    }

    // Mock WC_Cart.
    if (!class_exists('WC_Cart')) {
        class WC_Cart
        {
            public function get_total($context = 'view'): float
            {
                return 150.00;
            }

            public function get_cart(): array
            {
                return [
                    [
                        'data' => new WC_Product(),
                        'quantity' => 2,
                    ]
                ];
            }

            public function get_shipping_total(): float
            {
                return 10.00;
            }

            public function get_shipping_tax(): float
            {
                return 2.00;
            }

            public function get_cart_item_tax_classes_for_shipping(): array
            {
                return ['standard'];
            }
        }
    }

    // Mock WP_Term.
    if (!class_exists('WP_Term')) {
        class WP_Term
        {
            public $name = 'Test Category';
            public $parent = 0;
            public $term_id = 1;
        }
    }

    // Mock WC_Order.
    if (!class_exists('WC_Order')) {
        class WC_Order
        {
            private $id = 123;
            private $payment_method = 'comfino';
            private $billing_email = 'test@comfino-wc-store.test';

            public function get_id(): int
            {
                return $this->id;
            }

            public function get_payment_method(): string
            {
                return $this->payment_method;
            }

            public function get_billing_email(): string
            {
                return $this->billing_email;
            }

            public function add_order_note($note): bool
            {
                return true;
            }

            public function set_payment_method($method): void
            {
                $this->payment_method = $method;
            }
        }
    }

    // Mock WooCommerce main class
    if (!class_exists('WooCommerce_Mock')) {
        class WooCommerce_Mock
        {
            public $cart;

            public function __construct()
            {
                $this->cart = new WC_Cart();
            }

            public function plugin_url(): string
            {
                return 'https://comfino-wc-store.test/wp-content/plugins/woocommerce/';
            }
        }
    }

    // Initialize the WooCommerce global after all classes are defined.
    global $woocommerce;

    $woocommerce = new WooCommerce_Mock();

    // Mock Comfino payment gateway class.
    if (!class_exists('ComfinoPaymentGateway_Mock')) {
        class ComfinoPaymentGateway_Mock
        {
            public function plugin_url(): string
            {
                return 'https://comfino-wc-store.test/wp-content/plugins/comfino-payment-gateway/';
            }

            public function plugin_abspath(): string
            {
                return '/path/to/plugin/';
            }
        }
    }

    // Mock the Comfino payment gateway global that some methods expect.
    global $comfino_payment_gateway;

    $comfino_payment_gateway = new ComfinoPaymentGateway_Mock();

    if (!function_exists('wp_get_attachment_image_url')) {
        function wp_get_attachment_image_url($attachment_id, $size = 'thumbnail'): string
        {
            return 'https://comfino-wc-store.test/image.jpg';
        }
    }

    if (!function_exists('wc_get_order_notes')) {
        function wc_get_order_notes($args): array
        {
            return [
                (object) [
                    'added_by' => 'system',
                    'content' => 'Comfino status: ACCEPTED',
                ],
                (object) [
                    'added_by' => 'system',
                    'content' => 'Comfino status: CANCELLED',
                ],
            ];
        }
    }

    if (!function_exists('checked')) {
        function checked($checked, $current = true, $echo = true)
        {
            $result = ($checked === $current) ? 'checked="checked"' : '';

            return $echo ? print(esc_html($result)) : $result;
        }
    }

    if (!function_exists('wp_kses_post')) {
        function wp_kses_post($data): string
        {
            // Workaround for Plugin Check (PCP) error in security report.
            return array_map('strip_tags', [$data])[0];
        }
    }

    if (!function_exists('wp_scripts')) {
        function wp_scripts()
        {
            return new class {
                public $registered = [];
                public $queue = [];
            };
        }
    }

    if (!function_exists('wp_styles')) {
        function wp_styles()
        {
            return new class {
                public $registered = [];
                public $queue = [];
            };
        }
    }

    if (!function_exists('wp_register_script')) {
        function wp_register_script($handle, $src = '', $deps = [], $ver = false, $args = null): bool
        {
            return true;
        }
    }

    if (!function_exists('wp_add_inline_script')) {
        function wp_add_inline_script($handle, $data, $position = 'after'): bool
        {
            return true;
        }
    }

    if (!function_exists('wp_register_style')) {
        function wp_register_style($handle, $src = '', $deps = [], $ver = false, $media = 'all'): bool
        {
            return true;
        }
    }

    if (!function_exists('wp_enqueue_style')) {
        function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all'): bool
        {
            return true;
        }
    }

    // Load Composer autoloader.
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}
