<?php

namespace Comfino\Configuration;

use Comfino\Common\Backend\Configuration\StorageAdapterInterface;
use Comfino\Common\Backend\ConfigurationManager;

if (!defined('ABSPATH')) {
    exit;
}

class StorageAdapter extends \WC_Payment_Gateway implements StorageAdapterInterface
{
    /** @var int[] */
    private $optTypeFlags;

    public function __construct()
    {
        $this->id = 'comfino';
        $this->optTypeFlags = array_merge(array_merge(...array_values(ConfigManager::CONFIG_OPTIONS)));
    }

    public function load(): array
    {
        $configuration = [];
        $initialConfigValues = ConfigManager::getDefaultConfigurationValues();

        foreach ($this->optTypeFlags as $optName => $optTypeFlags) {
            $configuration[$optName] = $this->get_option(ConfigManager::CONFIG_OPTIONS_MAP[$optName], $initialConfigValues[$optName] ?? null);

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
            if (is_bool($optValue) || (isset($this->optTypeFlags[$optName]) && $this->optTypeFlags[$optName] & ConfigurationManager::OPT_VALUE_TYPE_BOOL)) {
                $optValue = ((bool) $optValue === true ? 'yes' : 'no');
            }

            $this->settings[ConfigManager::CONFIG_OPTIONS_MAP[$optName]] = $optValue;
        }

        update_option(
            $this->get_option_key(),
            apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings),
            'yes'
        );
    }
}
