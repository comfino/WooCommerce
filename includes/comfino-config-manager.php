<?php

namespace Comfino;

class Config_Manager extends \WC_Settings_API
{
    const CONFIG_OPTIONS_MAP = [
        'COMFINO_API_KEY' => 'production_key',
        'COMFINO_SHOW_LOGO' => 'show_logo',
        'COMFINO_PAYMENT_TEXT' => 'title',
        'COMFINO_IS_SANDBOX' => 'sandbox_mode',
        'COMFINO_SANDBOX_API_KEY' => 'sandbox_key',
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
        'COMFINO_SHOW_LOGO',
        'COMFINO_PAYMENT_TEXT',
        'COMFINO_IS_SANDBOX',
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
        'COMFINO_SHOW_LOGO' => 'bool',
        'COMFINO_IS_SANDBOX' => 'bool',
        'COMFINO_WIDGET_ENABLED' => 'bool',
        'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL' => 'int',
    ];

    public function __construct()
    {
        $this->id = 'comfino';
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Comfino Payment Module.', 'comfino-payment-gateway'),
                'default' => 'no',
                'description' => __('Show in the Payment List as a payment option.', 'comfino-payment-gateway')
            ],
            'title' => [
                'title' => __('Title', 'comfino-payment-gateway'),
                'type' => 'text',
                'default' => 'Comfino',
            ],
            'production_key' => [
                'title' => __('Production key', 'comfino-payment-gateway'),
                'type' => 'text'
            ],
            'show_logo' => [
                'title' => __('Show logo', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Show logo on payment method', 'comfino-payment-gateway'),
                'default' => 'yes',
            ],
            'sandbox_mode' => [
                'title' => __('Sandbox mode', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable sandbox mode', 'comfino-payment-gateway'),
                'default' => 'no',
            ],
            'sandbox_key' => [
                'title' => __('Sandbox key', 'comfino-payment-gateway'),
                'type' => 'text'
            ],
            'widget_enabled' => [
                'title' => __('Widget enable', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Comfino widget', 'comfino-payment-gateway'),
                'default' => 'no',
                'description' => __('Show Comfino widget in the product.', 'comfino-payment-gateway')
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
                'options' => [
                    'INSTALLMENTS_ZERO_PERCENT' => __('Zero percent installments', 'comfino-payment-gateway'),
                    'CONVENIENT_INSTALLMENTS' => __('Convenient installments', 'comfino-payment-gateway'),
                    'PAY_LATER' => __('Pay later', 'comfino-payment-gateway'),
                ]
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
                'title' => __('Widget price observer selector', 'comfino-payment-gateway'),
                'type' => 'text',
                'default' => '',
            ],
            'widget_price_observer_level' => [
                'title' => __('Price change detection level', 'comfino-payment-gateway'),
                'type' => 'number',
                'default' => 0,
                'description' => __(
                    'Hierarchy level of observed parent element relative to the price element.',
                    'comfino-payment-gateway'
                )
            ],
            'widget_key' => [
                'title' => __('Widget key', 'comfino-payment-gateway'),
                'type' => 'text',
            ],
            'widget_js_code' => [
                'title' => __('Widget code', 'comfino-payment-gateway'),
                'type' => 'textarea',
                'css' => 'width: 800px; height: 400px',
                'default' => $this->get_initial_widget_code(),
            ],
        ];
    }

    public function get_form_fields(): array
    {
        return $this->form_fields;
    }

    public function return_configuration_options(): array
    {
        $configuration_options = [];

        foreach (self::ACCESSIBLE_CONFIG_OPTIONS as $opt_name) {
            $configuration_options[$opt_name] = $this->get_option(self::CONFIG_OPTIONS_MAP[$opt_name]);

            if (array_key_exists($opt_name, self::CONFIG_OPTIONS_TYPES)) {
                switch (self::CONFIG_OPTIONS_TYPES[$opt_name]) {
                    case 'bool':
                        $configuration_options[$opt_name] = ($configuration_options[$opt_name] === 'yes');
                        break;

                    case 'int':
                        $configuration_options[$opt_name] = (int)$configuration_options[$opt_name];
                        break;

                    case 'float':
                        $configuration_options[$opt_name] = (float)$configuration_options[$opt_name];
                        break;
                }
            }
        }

        return $configuration_options;
    }

    public function update_configuration(array $configuration_options, bool $remote_request): bool
    {
        $this->init_settings();

        $is_error = false;

        foreach ($this->get_form_fields() as $key => $field) {
            if (array_key_exists($this->get_field_key($key), $configuration_options) && $this->get_field_type($field) !== 'title') {
                try {
                    $this->settings[$key] = $this->get_field_value($key, $field, $configuration_options);
                } catch (\Exception $e) {
                    $this->add_error($e->getMessage());

                    $is_error = true;
                }
            }
        }

        if ($remote_request) {
            if ($is_error) {
                return false;
            }
        } else {
            $is_sandbox = $this->settings['sandbox_mode'] === 'yes';
            $api_host = $is_sandbox ? Core::COMFINO_SANDBOX_HOST : Core::COMFINO_PRODUCTION_HOST;
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

        return update_option(
            $this->get_option_key(),
            apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings),
            'yes'
        );
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
            } elseif ($opt_value === false) {
                $opt_value = 'no';
            }

            $prepared_config_options[$this->get_field_key(self::CONFIG_OPTIONS_MAP[$opt_name])] = $opt_value;
        }

        return $prepared_config_options;
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
