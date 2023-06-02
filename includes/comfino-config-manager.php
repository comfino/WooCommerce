<?php

class Config_Manager
{
    private const ACCESSIBLE_CONFIG_OPTIONS = [
        'COMFINO_PAYMENT_PRESENTATION',
        'COMFINO_PAYMENT_TEXT',
        'COMFINO_MINIMAL_CART_AMOUNT',
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

    private const CONFIG_OPTIONS_TYPES = [
        'COMFINO_MINIMAL_CART_AMOUNT' => 'float',
        'COMFINO_IS_SANDBOX' => 'bool',
        'COMFINO_WIDGET_ENABLED' => 'bool',
        'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL' => 'int',
    ];

    public function returnConfigurationOptions(): array
    {
        $configuration_options = [];

        foreach (self::ACCESSIBLE_CONFIG_OPTIONS as $opt_name) {
            $configuration_options[$opt_name] = Configuration::get($opt_name);

            if (array_key_exists($opt_name, self::CONFIG_OPTIONS_TYPES)) {
                switch (self::CONFIG_OPTIONS_TYPES[$opt_name]) {
                    case 'bool':
                        $configuration_options[$opt_name] = (bool)$configuration_options[$opt_name];
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

    public function updateConfiguration(array $configurationOptions, bool $only_accessible_options = true): void
    {
        foreach ($configurationOptions as $opt_name => $opt_value) {
            if ($only_accessible_options && !in_array($opt_name, self::ACCESSIBLE_CONFIG_OPTIONS, true)) {
                continue;
            }

            Configuration::updateValue($opt_name, $opt_value);
        }
    }
}
