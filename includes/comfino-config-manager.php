<?php

namespace Comfino;

class Config_Manager extends \WC_Settings_API
{
    const CONFIG_OPTIONS_MAP = [
        'COMFINO_ENABLED' => 'enabled',
        'COMFINO_API_KEY' => 'production_key',
        'COMFINO_SHOW_LOGO' => 'show_logo',
        'COMFINO_PAYMENT_TEXT' => 'title',
        'COMFINO_IS_SANDBOX' => 'sandbox_mode',
        'COMFINO_SANDBOX_API_KEY' => 'sandbox_key',
        'COMFINO_PRODUCT_CATEGORY_FILTERS' => 'product_category_filters',
        'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES' => 'cat_filter_avail_prod_types',
        'COMFINO_WIDGET_ENABLED' => 'widget_enabled',
        'COMFINO_WIDGET_KEY' => 'widget_key',
        'COMFINO_WIDGET_PRICE_SELECTOR' => 'widget_price_selector',
        'COMFINO_WIDGET_TARGET_SELECTOR' => 'widget_target_selector',
        'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR' => 'widget_price_observer_selector',
        'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL' => 'widget_price_observer_level',
        'COMFINO_WIDGET_TYPE' => 'widget_type',
        'COMFINO_WIDGET_OFFER_TYPE' => 'widget_offer_type',
        'COMFINO_WIDGET_EMBED_METHOD' => 'widget_embed_method',
        'COMFINO_WIDGET_CODE' => 'widget_js_code',
    ];

    const ACCESSIBLE_CONFIG_OPTIONS = [
        'COMFINO_ENABLED',
        'COMFINO_SHOW_LOGO',
        'COMFINO_PAYMENT_TEXT',
        'COMFINO_IS_SANDBOX',
        'COMFINO_PRODUCT_CATEGORY_FILTERS',
        'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES',
        'COMFINO_WIDGET_ENABLED',
        'COMFINO_WIDGET_KEY',
        'COMFINO_WIDGET_PRICE_SELECTOR',
        'COMFINO_WIDGET_TARGET_SELECTOR',
        'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR',
        'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL',
        'COMFINO_WIDGET_TYPE',
        'COMFINO_WIDGET_OFFER_TYPE',
        'COMFINO_WIDGET_EMBED_METHOD',
        'COMFINO_WIDGET_CODE',
    ];

    const CONFIG_OPTIONS_TYPES = [
        'COMFINO_ENABLED' => 'bool',
        'COMFINO_SHOW_LOGO' => 'bool',
        'COMFINO_IS_SANDBOX' => 'bool',
        'COMFINO_WIDGET_ENABLED' => 'bool',
        'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL' => 'int',
    ];

    private $product_types;

    public function __construct()
    {
        $this->id = 'comfino';

        Api_Client::$api_language = substr(get_bloginfo('language'), 0, 2);

        if (($this->product_types = get_transient('COMFINO_PRODUCT_TYPES')) === false) {
            Api_Client::init($this);
            $this->product_types = Api_Client::get_product_types();
            set_transient('COMFINO_PRODUCT_TYPES', $this->product_types, DAY_IN_SECONDS);
        }

        if (empty($this->product_types)) {
            $this->product_types = [
                'INSTALLMENTS_ZERO_PERCENT' => __('Zero percent installments', 'comfino-payment-gateway'),
                'CONVENIENT_INSTALLMENTS' => __('Convenient installments', 'comfino-payment-gateway'),
                'PAY_LATER' => __('Pay later', 'comfino-payment-gateway'),
            ];
        }

        $this->id = 'comfino';
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Comfino payment module', 'comfino-payment-gateway'),
                'default' => 'no',
                'description' => __('Shows Comfino payment option at the payment list.', 'comfino-payment-gateway')
            ],
            'title' => [
                'title' => __('Title', 'comfino-payment-gateway'),
                'type' => 'text',
                'default' => 'Comfino',
            ],
            'production_key' => [
                'title' => __('Production environment API key', 'comfino-payment-gateway'),
                'type' => 'text',
                'placeholder' => __('Please enter the key provided during registration', 'comfino-payment-gateway'),
            ],
            'show_logo' => [
                'title' => __('Show logo', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Show logo on payment method', 'comfino-payment-gateway'),
                'default' => 'yes',
            ],
            'sandbox_mode' => [
                'title' => __('Test environment', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Use test environment', 'comfino-payment-gateway'),
                'default' => 'no',
                'description' => __(
                    'The test environment allows the store owner to get acquainted with the ' .
                    'functionality of the Comfino module. This is a Comfino simulator, thanks ' .
                    'to which you can get to know all the advantages of this payment method. ' .
                    'The use of the test mode is free (there are also no charges for orders).',
                    'comfino-payment-gateway'
                ),
            ],
            'sandbox_key' => [
                'title' => __('Test environment API key', 'comfino-payment-gateway'),
                'type' => 'text',
                'description' => __('Ask the supervisor for access to the test environment (key, login, password, link). Remember, the test key is different from the production key.', 'comfino-payment-gateway'),
            ],
            'cat_filter_avail_prod_types' => [
                'type' => 'hidden',
                'default' => 'INSTALLMENTS_ZERO_PERCENT,PAY_LATER',
            ],
            'sale_settings_fin_prods_avail_rules' => [
                'title' => __('Rules for the availability of financial products', 'comfino-payment-gateway'),
                'type' => 'title'
            ],
            'widget_settings_basic' => [
                'title' => __('Basic settings', 'comfino-payment-gateway'),
                'type' => 'title'
            ],
            'widget_enabled' => [
                'title' => __('Widget enable', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Comfino widget', 'comfino-payment-gateway'),
                'default' => 'no',
                'description' => __('Show Comfino widget in the product.', 'comfino-payment-gateway')
            ],
            'widget_key' => [
                'title' => __('Widget key', 'comfino-payment-gateway'),
                'type' => 'hidden',
            ],
            'widget_type' => [
                'title' => __('Widget type', 'comfino-payment-gateway'),
                'type' => 'select',
                'options' => [
                    'simple' => __('Textual widget', 'comfino-payment-gateway'),
                    'mixed' => __('Graphical widget with banner', 'comfino-payment-gateway'),
                    'with-modal' => __('Graphical widget with installments calculator', 'comfino-payment-gateway'),
                ]
            ],
            'widget_offer_type' => [
                'title' => __('Widget offer type', 'comfino-payment-gateway'),
                'type' => 'select',
                'options' => $this->product_types,
                'description' => __('Other payment methods (Installments 0%, Buy now, pay later, Installments for Companies) available after consulting a Comfino advisor (kontakt@comfino.pl).', 'comfino-payment-gateway'),
            ],
            'widget_settings_advanced' => [
                'title' => __('Advanced settings', 'comfino-payment-gateway'),
                'type' => 'title'
            ],
            'widget_price_selector' => [
                'title' => __('Widget price selector', 'comfino-payment-gateway'),
                'type' => 'text',
                'default' => '.price .woocommerce-Price-amount bdi',
            ],
            'widget_target_selector' => [
                'title' => __('Widget target selector', 'comfino-payment-gateway'),
                'type' => 'text',
                'default' => '.summary .product_meta',
            ],
            'widget_price_observer_selector' => [
                'title' => __('Price change detection - container selector', 'comfino-payment-gateway'),
                'type' => 'text',
                'description' => __(
                    'Selector of observed parent element which contains price element.',
                    'comfino-payment-gateway'
                )
            ],
            'widget_price_observer_level' => [
                'title' => __('Price change detection - container hierarchy level', 'comfino-payment-gateway'),
                'type' => 'number',
                'default' => 0,
                'description' => __(
                    'Hierarchy level of observed parent element relative to the price element.',
                    'comfino-payment-gateway'
                )
            ],
            'widget_embed_method' => [
                'title' => __('Widget embed method', 'comfino-payment-gateway'),
                'type' => 'select',
                'options' => [
                    'INSERT_INTO_FIRST' => 'INSERT_INTO_FIRST',
                    'INSERT_INTO_LAST' => 'INSERT_INTO_LAST',
                    'INSERT_BEFORE' => 'INSERT_BEFORE',
                    'INSERT_AFTER' => 'INSERT_AFTER',
                ]
            ],
            'widget_js_code' => [
                'title' => __('Widget code', 'comfino-payment-gateway'),
                'type' => 'textarea',
                'css' => 'width: 800px; height: 400px',
                'default' => $this->get_initial_widget_code(),
            ],
        ];
    }

    public function get_form_fields($subsection = null): array
    {
        if (empty($subsection)) {
            return $this->form_fields;
        }

        $form_fields = [];

        switch ($subsection) {
            case 'payment_settings':
                $form_fields = array_intersect_key(
                    $this->form_fields,
                    array_flip(['enabled', 'title', 'production_key', 'show_logo'])
                );
                break;

            case 'sale_settings':
                $form_fields = array_intersect_key(
                    $this->form_fields,
                    array_flip(['cat_filter_avail_prod_types', 'sale_settings_fin_prods_avail_rules'])
                );

                $product_categories = $this->get_all_product_categories();
                $product_category_filters = $this->get_product_category_filters();
                $cat_filter_avail_prod_types = $this->get_cat_filter_avail_prod_types($this->product_types);

                foreach ($cat_filter_avail_prod_types as $product_type_code => $product_type_name) {
                    if (isset($product_category_filters[$product_type_code])) {
                        $selected_categories = array_diff(
                            array_keys($product_categories),
                            $product_category_filters[$product_type_code]
                        );
                    } else {
                        $selected_categories = array_keys($product_categories);
                    }

                    $form_fields['sale_settings_product_category_filter_' . $product_type_code] = [
                        'title' => $product_type_name,
                        'type' => 'product_category_tree',
                        'product_type' => $product_type_code,
                        'id' => 'product_categories',
                        'selected_categories' => $selected_categories,
                    ];
                }

                break;

            case 'widget_settings':
                $form_fields = array_intersect_key(
                    $this->form_fields,
                    array_flip([
                        'widget_settings_basic',
                        'widget_enabled', 'widget_key', 'widget_type', 'widget_offer_type',
                        'widget_settings_advanced',
                        'widget_price_selector', 'widget_target_selector', 'widget_price_observer_selector',
                        'widget_price_observer_level', 'widget_embed_method', 'widget_js_code',
                    ])
                );
                break;

            case 'developer_settings':
                $form_fields = array_intersect_key(
                    $this->form_fields,
                    array_flip(['sandbox_mode', 'sandbox_key'])
                );
                break;
        }

        return $form_fields;
    }

    public function return_configuration_options(bool $all_options = false): array
    {
        $configuration_options = [];

        foreach ($all_options ? array_keys(self::CONFIG_OPTIONS_MAP) : self::ACCESSIBLE_CONFIG_OPTIONS as $opt_name) {
            $configuration_options[$opt_name] = $this->get_option(self::CONFIG_OPTIONS_MAP[$opt_name]);

            switch ($this->get_option_type($opt_name)) {
                case 'bool':
                    $configuration_options[$opt_name] = ($configuration_options[$opt_name] === 'yes');
                    break;

                case 'int':
                    $configuration_options[$opt_name] = (int) $configuration_options[$opt_name];
                    break;

                case 'float':
                    $configuration_options[$opt_name] = (float) $configuration_options[$opt_name];
                    break;
            }

            if ($opt_name === 'COMFINO_WIDGET_CODE') {
                $configuration_options[$opt_name] = str_replace(
                    ['&#039;', '&gt;', '&amp;', '&quot;', '&#34;'],
                    ["'", '>', '&', '"', '"'],
                    $configuration_options[$opt_name]
                );
            }
        }

        return $configuration_options;
    }

    public function update_configuration(string $subsection, array $configuration_options, bool $remote_request): bool
    {
        $this->init_settings();

        $options_map = array_flip(self::CONFIG_OPTIONS_MAP);
        $is_error = false;

        foreach ($this->get_form_fields($subsection) as $key => $field) {
            $field_key = $this->get_field_key($key);

            if (isset($options_map[$key]) && $this->get_option_type($options_map[$key]) === 'bool') {
                if (isset($configuration_options[$field_key])) {
                    $configuration_options[$field_key] = 'yes';
                } else {
                    $configuration_options[$field_key] = 'no';
                }
            }

            if (array_key_exists($field_key, $configuration_options) && $this->get_field_type($field) !== 'title') {
                try {
                    if ($configuration_options[$field_key] === 'yes' || $configuration_options[$field_key] === 'no') {
                        $this->settings[$key] = $configuration_options[$field_key];
                    } else {
                        $this->settings[$key] = $this->get_field_value($key, $field, $configuration_options);
                    }
                } catch (\Exception $e) {
                    $this->add_error($e->getMessage());

                    $is_error = true;
                }
            } elseif ($subsection === 'sale_settings') {
                $product_categories = array_map(
                    static function (array $category) { return (int) $category['id']; },
                    $this->get_category_tree_leafs()
                );
                $product_category_filters = [];

                foreach ($configuration_options['product_categories'] as $product_type => $category_ids) {
                    $product_category_filters[$product_type] = array_values(array_diff(
                        $product_categories,
                        explode(',', $configuration_options['product_categories'][$product_type])
                    ));
                }

                $this->settings['product_category_filters'] = json_encode($product_category_filters);
            }
        }

        if ($remote_request) {
            if ($is_error) {
                return false;
            }
        } else {
            $is_sandbox = ($this->settings['sandbox_mode'] === 'yes');
            $api_host = $is_sandbox ? Api_Client::get_api_host(false, Core::COMFINO_SANDBOX_HOST) : Core::COMFINO_PRODUCTION_HOST;
            $api_key = $is_sandbox ? $this->settings['sandbox_key'] : $this->settings['production_key'];

            if (!Api_Client::is_api_key_valid($api_host, $api_key)) {
                $this->add_error(sprintf(__('API key %s is not valid.', 'comfino-payment-gateway'), $api_key));

                $is_error = true;
            }

            if ($is_error) {
                $this->display_errors();

                return false;
            }

            $this->settings['widget_key'] = Api_Client::get_widget_key($api_host, $api_key);
        }

        delete_transient('COMFINO_PRODUCT_TYPES');

        return update_option(
            $this->get_option_key(),
            apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings),
            'yes'
        );
    }

    public function filter_configuration_options(array $configuration_options): array
    {
        $filtered_config_options = [];

        foreach ($configuration_options as $opt_name => $opt_value) {
            if (in_array($opt_name, self::ACCESSIBLE_CONFIG_OPTIONS, true)) {
                $filtered_config_options[$opt_name] = $opt_value;
            }
        }

        return $filtered_config_options;
    }

    public function prepare_configuration_options(array $configuration_options): array
    {
        $prepared_config_options = [];

        foreach ($configuration_options as $opt_name => $opt_value) {
            if (!in_array($opt_name, self::ACCESSIBLE_CONFIG_OPTIONS, true)) {
                continue;
            }

            if ($opt_value === true) {
                $opt_value = 'yes';
            }

            if ($opt_value !== false) {
                $prepared_config_options[$this->get_field_key(self::CONFIG_OPTIONS_MAP[$opt_name])] = $opt_value;
            }
        }

        return $prepared_config_options;
    }

    public function get_offer_types(): array
    {
        return $this->product_types;
    }

    public function get_product_category_filters(): array
    {
        $categories = [];
        $categoriesStr = $this->get_option('product_category_filters');

        if (!empty($categoriesStr)) {
            $categories = json_decode($categoriesStr, true);
        }

        return $categories;
    }

    /**
     * @param string $product_type Financial product type (offer type)
     * @param \WC_Product_Simple[] $products Products in the cart
     */
    public function is_financial_product_available(string $product_type, array $products): bool
    {
        static $product_category_filters = null;

        if ($product_category_filters === null) {
            $product_category_filters = $this->get_product_category_filters();
        }

        if (isset($product_category_filters[$product_type]) && count($product_category_filters[$product_type])) {
            $excluded_cat_ids = $product_category_filters[$product_type];

            foreach ($products as $product) {
                foreach ($product['data']->get_category_ids() as $category_id) {
                    if (in_array($category_id, $excluded_cat_ids, true) ||
                        count(array_intersect($excluded_cat_ids, get_term_children($category_id, 'product_cat')))
                    ) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public function get_category_tree_leafs(array $cat_tree = []): array
    {
        if (!count($cat_tree)) {
            $cat_tree = $this->build_categories_tree([]);
        }

        $leaf_nodes = [];
        $child_nodes = [];

        foreach ($cat_tree as $cat_tree_node) {
            if (!isset($cat_tree_node['children'])) {
                $leaf_nodes[] = $cat_tree_node;
            } else {
                $child_nodes[] = $this->get_category_tree_leafs($cat_tree_node['children']);
            }
        }

        return array_merge($leaf_nodes, ...$child_nodes);
    }

    /**
     * @param int[] $selected_categories
     */
    public function build_categories_tree(array $selected_categories): array
    {
        return $this->process_tree_nodes(
            get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name']),
            $selected_categories,
            0
        );
    }

    /**
     * @param \WP_Term[] $tree_nodes
     */
    private function process_tree_nodes(array $tree_nodes, array $selected_nodes, int $parent_id): array
    {
        $cat_tree = [];

        foreach ($tree_nodes as $node) {
            if ($node->parent === $parent_id) {
                $cat_tree_node = ['id' => $node->term_id, 'text' => $node->name];
                $child_nodes = $this->process_tree_nodes($tree_nodes, $selected_nodes, $node->term_id);

                if (count($child_nodes)) {
                    $cat_tree_node['children'] = $child_nodes;
                } elseif (in_array($node->term_id, $selected_nodes, true)) {
                    $cat_tree_node['checked'] = true;
                }

                $cat_tree[] = $cat_tree_node;
            }
        }

        return $cat_tree;
    }

    private function get_all_product_categories()
    {
        static $categories = null;

        if ($categories === null) {
            $categories = [];
            $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name']);

            foreach ($terms as $term) {
                /** @var \WP_Term $term */
                $categories[$term->term_id] = $term->name;
            }
        }

        return $categories;
    }

    private function get_cat_filter_avail_prod_types(array $prod_types): array
    {
        $cat_filter_avail_prod_types = [];

        foreach (explode(',', $this->get_option('cat_filter_avail_prod_types', 'INSTALLMENTS_ZERO_PERCENT,PAY_LATER')) as $prod_type) {
            $cat_filter_avail_prod_types[strtoupper(trim($prod_type))] = null;
        }

        return array_intersect_key($prod_types, $cat_filter_avail_prod_types);
    }

    private function get_option_type(string $opt_name): string
    {
        return self::CONFIG_OPTIONS_TYPES[$opt_name] ?? 'string';
    }

    private function get_initial_widget_code(): string
    {
        return trim("
var script = document.createElement('script');
script.onload = function () {
    ComfinoProductWidget.init({
        widgetKey: '{WIDGET_KEY}',
        priceSelector: '{WIDGET_PRICE_SELECTOR}',
        widgetTargetSelector: '{WIDGET_TARGET_SELECTOR}',
        priceObserverSelector: '{WIDGET_PRICE_OBSERVER_SELECTOR}',
        priceObserverLevel: {WIDGET_PRICE_OBSERVER_LEVEL},
        type: '{WIDGET_TYPE}',
        offerType: '{OFFER_TYPE}',
        embedMethod: '{EMBED_METHOD}',
        numOfInstallments: 0,
        price: null,
        pluginVersion: '{PLUGIN_VERSION}',
        callbackBefore: function () {},
        callbackAfter: function () {},
        onOfferRendered: function (jsonResponse, widgetTarget, widgetNode) { },
        onGetPriceElement: function (priceSelector, priceObserverSelector) { return null; },
        debugMode: window.location.hash && window.location.hash.substring(1) === 'comfino_debug'
    });
};
script.src = '{WIDGET_SCRIPT_URL}';
script.async = true;
document.getElementsByTagName('head')[0].appendChild(script);
");
    }
}
