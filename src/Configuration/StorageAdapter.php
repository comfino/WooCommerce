<?php

namespace Comfino\Configuration;

use Comfino\Common\Backend\Configuration\StorageAdapterInterface;
use Comfino\Common\Backend\ConfigurationManager;

if (!defined('ABSPATH')) {
    exit;
}

class StorageAdapter extends \WC_Settings_API implements StorageAdapterInterface
{
    private const CONFIG_OPTIONS_MAP = [
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
        'COMFINO_WIDGET_PROD_SCRIPT_VERSION' => 'widget_prod_script_version',
        'COMFINO_WIDGET_DEV_SCRIPT_VERSION' => 'widget_dev_script_version',
        'COMFINO_ABANDONED_CART_ENABLED' => 'abandoned_cart_enabled',
        'COMFINO_ABANDONED_PAYMENTS' => 'abandoned_payments',
        'COMFINO_IGNORED_STATUSES' => 'ignored_statuses',
        'COMFINO_FORBIDDEN_STATUSES' => 'forbidden_statuses',
        'COMFINO_STATUS_MAP' => 'status_map',
        'COMFINO_API_CONNECT_TIMEOUT' => 'api_connect_timeout',
        'COMFINO_API_TIMEOUT' => 'api_timeout',
    ];

    public function load(): array
    {
        $configuration = [];
        $initialConfigValues = ConfigManager::getDefaultConfigurationValues();

        foreach (array_merge(array_merge(...array_values(ConfigManager::CONFIG_OPTIONS))) as $optName => $optTypeFlags) {
            $configuration[$optName] = $this->get_option(self::CONFIG_OPTIONS_MAP[$optName], $initialConfigValues[$optName]);

            if ($optTypeFlags & ConfigurationManager::OPT_VALUE_TYPE_BOOL) {
                $configuration[$optName] = ($configuration[$optName] === 'yes');
            }
        }

        return $configuration;
    }

    public function save($configurationOptions): void
    {
        $this->init_settings();

        foreach ($configurationOptions as $optName => $optValue) {
            if (is_bool($optValue)) {
                $optValue = ($optValue === true ? 'yes' : 'no');
            }

            $this->settings[self::CONFIG_OPTIONS_MAP[$optName]] = $optValue;
        }

        update_option(
            $this->get_option_key(),
            apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings),
            'yes'
        );
    }
}
