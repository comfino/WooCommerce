<?php

namespace Comfino\Configuration;

use Comfino\Api\ApiClient;
use Comfino\Api\ApiService;
use Comfino\CategoryTree\BuildStrategy;
use Comfino\Common\Backend\ConfigurationManager;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Common\Shop\Product\CategoryTree;
use Comfino\ErrorLogger;
use Comfino\Extended\Api\Serializer\Json as JsonSerializer;
use Comfino\Order\ShopStatusManager;
use Comfino\Tools;

if (!defined('ABSPATH')) {
    exit;
}

final class ConfigManager
{
    public const CONFIG_OPTIONS = [
        'payment_settings' => [
            'COMFINO_API_KEY' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_PAYMENT_TEXT' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_MINIMAL_CART_AMOUNT' => ConfigurationManager::OPT_VALUE_TYPE_FLOAT,
        ],
        'sale_settings' => [
            'COMFINO_PRODUCT_CATEGORY_FILTERS' => ConfigurationManager::OPT_VALUE_TYPE_JSON,
        ],
        'widget_settings' => [
            'COMFINO_WIDGET_ENABLED' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
            'COMFINO_WIDGET_KEY' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_PRICE_SELECTOR' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_TARGET_SELECTOR' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL' => ConfigurationManager::OPT_VALUE_TYPE_INT,
            'COMFINO_WIDGET_TYPE' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_OFFER_TYPE' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_EMBED_METHOD' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_CODE' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
        ],
        'developer_settings' => [
            'COMFINO_IS_SANDBOX' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
            'COMFINO_SANDBOX_API_KEY' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
        ],
        'hidden_settings' => [
            'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES' => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY,
            'COMFINO_IGNORED_STATUSES' => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY,
            'COMFINO_FORBIDDEN_STATUSES' => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY,
            'COMFINO_STATUS_MAP' => ConfigurationManager::OPT_VALUE_TYPE_JSON,
        ],
    ];

    public const ACCESSIBLE_CONFIG_OPTIONS = [
        'COMFINO_ENABLED',
        'COMFINO_PAYMENT_TEXT',
        'COMFINO_MINIMAL_CART_AMOUNT',
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
        'COMFINO_WIDGET_PROD_SCRIPT_VERSION',
        'COMFINO_WIDGET_DEV_SCRIPT_VERSION',
        'COMFINO_IGNORED_STATUSES',
        'COMFINO_FORBIDDEN_STATUSES',
        'COMFINO_STATUS_MAP',
        'COMFINO_API_CONNECT_TIMEOUT',
        'COMFINO_API_TIMEOUT',
    ];

    /** @var ConfigurationManager */
    private static $configurationManager;

    public static function getInstance(): ConfigurationManager
    {
        if (self::$configurationManager === null) {
            self::$configurationManager = ConfigurationManager::getInstance(
                array_merge(array_merge(...array_values(self::CONFIG_OPTIONS))),
                self::ACCESSIBLE_CONFIG_OPTIONS,
                new StorageAdapter(),
                new JsonSerializer()
            );
        }

        return self::$configurationManager;
    }

    /**
     * @param string[]|null $selectedEnvFields
     * @return string[]
     */
    public static function getEnvironmentInfo(?array $selectedEnvFields = null): array
    {
        global $wp_version, $wpdb;

        $envFields = [
            'plugin_version' => \Comfino_Payment_Gateway::VERSION,
            'shop_version' => WC_VERSION,
            'wordpress_version' => $wp_version,
            'php_version' => PHP_VERSION,
            'server_software' => sanitize_text_field($_SERVER['SERVER_SOFTWARE']),
            'server_name' => sanitize_text_field($_SERVER['SERVER_NAME']),
            'server_addr' => sanitize_text_field($_SERVER['SERVER_ADDR']),
            'database_version' => $wpdb->db_version(),
        ];

        if (empty($selectedEnvFields)) {
            return $envFields;
        }

        $filteredEnvFields = [];

        foreach ($selectedEnvFields as $envField) {
            if (array_key_exists($envField, $envFields)) {
                $filteredEnvFields[$envField] = $envFields[$envField];
            }
        }

        return $filteredEnvFields;
    }

    /**
     * @return string[]
     */
    public static function getAllProductCategories(): ?array
    {
        static $categories = null;

        if ($categories === null) {
            $categories = [];

            foreach (\Category::getSimpleCategories(\Context::getContext()->language->id) as $category) {
                $categories[$category['id_category']] = $category['name'];
            }
        }

        return $categories;
    }

    public static function getCategoriesTree(): CategoryTree
    {
        /** @var CategoryTree $categoriesTree */
        static $categoriesTree = null;

        if ($categoriesTree === null) {
            $categoriesTree = new CategoryTree(new BuildStrategy());
        }

        return $categoriesTree;
    }

    public static function getConfigurationValue(string $optionName)
    {
        return self::getInstance()->getConfigurationValue($optionName);
    }

    public static function isSandboxMode(): bool
    {
        return self::getInstance()->getConfigurationValue('COMFINO_IS_SANDBOX');
    }

    public static function isWidgetEnabled(): bool
    {
        return self::getInstance()->getConfigurationValue('COMFINO_WIDGET_ENABLED');
    }

    public static function getApiKey(): ?string
    {
        return self::isSandboxMode()
            ? self::getInstance()->getConfigurationValue('COMFINO_SANDBOX_API_KEY')
            : self::getInstance()->getConfigurationValue('COMFINO_API_KEY');
    }

    public static function getWidgetKey(): ?string
    {
        return self::getInstance()->getConfigurationValue('COMFINO_WIDGET_KEY');
    }

    /**
     * @return string[]
     */
    public static function getIgnoredStatuses(): array
    {
        return self::getConfigurationValue('COMFINO_IGNORED_STATUSES') ?? StatusManager::DEFAULT_IGNORED_STATUSES;
    }

    /**
     * @return string[]
     */
    public static function getForbiddenStatuses(): array
    {
        return self::getConfigurationValue('COMFINO_FORBIDDEN_STATUSES') ?? StatusManager::DEFAULT_FORBIDDEN_STATUSES;
    }

    /**
     * @return string[]
     */
    public static function getStatusMap(): array
    {
        return self::getConfigurationValue('COMFINO_STATUS_MAP') ?? ShopStatusManager::DEFAULT_STATUS_MAP;
    }

    public static function updateConfiguration($configurationOptions, $onlyAccessibleOptions = true): void
    {
        if ($onlyAccessibleOptions) {
            self::getInstance()->updateConfigurationOptions($configurationOptions);
        } else {
            self::getInstance()->setConfigurationValues($configurationOptions);
        }

        self::getInstance()->persist();
    }

    public static function deleteConfigurationValues(): bool
    {
        $result = true;

        foreach (self::CONFIG_OPTIONS as $options) {
            foreach ($options as $optionName) {
                $result &= \Configuration::deleteByName($optionName);
            }
        }

        return $result;
    }

    public static function updateWidgetCode(\PaymentModule $module, string $lastWidgetCodeHash): void
    {
        ErrorLogger::init($module);

        try {
            $initialWidgetCode = self::getInitialWidgetCode();
            $currentWidgetCode = self::getCurrentWidgetCode($module);

            if (md5($currentWidgetCode) === $lastWidgetCodeHash) {
                // Widget code not changed since last installed version - safely replace with new one.
                \Configuration::updateValue('COMFINO_WIDGET_CODE', $initialWidgetCode);
            }
        } catch (\Throwable $e) {
            ErrorLogger::sendError(
                'Widget code update',
                $e->getCode(),
                $e->getMessage(),
                null,
                null,
                null,
                $e->getTraceAsString()
            );
        }
    }

    /**
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    public static function getCurrentWidgetCode(\PaymentModule $module, $productId = null): string
    {
        $widgetCode = trim(str_replace("\r", '', \Configuration::get('COMFINO_WIDGET_CODE')));
        $productData = self::getProductData($module, $productId);

        $optionsToInject = [];

        if (strpos($widgetCode, 'productId') === false) {
            $optionsToInject[] = "        productId: $productData[product_id]";
        }
        if (strpos($widgetCode, 'availOffersUrl') === false) {
            $optionsToInject[] = "        availOffersUrl: '$productData[avail_offers_url]'";
        }

        if (count($optionsToInject)) {
            $injectedInitOptions = implode(",\n", $optionsToInject) . ",\n";

            return preg_replace('/\{\n(.*widgetKey:)/', "{\n$injectedInitOptions\$1", $widgetCode);
        }

        return $widgetCode;
    }

    /**
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    public static function getWidgetVariables(\PaymentModule $module, $productId = null): array
    {
        $productData = self::getProductData($module, $productId);

        return [
            '{WIDGET_SCRIPT_URL}' => ApiClient::getWidgetScriptUrl(),
            '{PRODUCT_ID}' => $productData['product_id'],
            '{PRODUCT_PRICE}' => $productData['price'],
            '{PLATFORM}' => 'prestashop',
            '{PLATFORM_VERSION}' => _PS_VERSION_,
            '{PLATFORM_DOMAIN}' => \Tools::getShopDomain(),
            '{PLUGIN_VERSION}' => COMFINO_VERSION,
            '{AVAILABLE_OFFER_TYPES}' => $productData['avail_offers_url'],
        ];
    }

    public static function getConfigurationValues(string $optionsGroup, array $optionsToReturn = []): array
    {
        if (!array_key_exists($optionsGroup, self::CONFIG_OPTIONS)) {
            return [];
        }

        return count($optionsToReturn)
            ? self::getInstance()->getConfigurationValues($optionsToReturn)
            : self::getInstance()->getConfigurationValues(self::CONFIG_OPTIONS[$optionsGroup]);
    }

    public static function initConfigurationValues(): void
    {
        if (\Configuration::hasKey('COMFINO_API_KEY')) {
            // Avoid overwriting of existing configuration if plugin is reinstalled/upgraded.
            return;
        }

        $initialConfigValues = [
            'COMFINO_PAYMENT_TEXT' => '(Raty | Kup Teraz, Zapłać Później | Finansowanie dla Firm)',
            'COMFINO_MINIMAL_CART_AMOUNT' => 30,
            'COMFINO_PRODUCT_CATEGORY_FILTERS' => '',
            'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES' => 'INSTALLMENTS_ZERO_PERCENT,PAY_LATER',
            'COMFINO_WIDGET_ENABLED' => false,
            'COMFINO_WIDGET_KEY' => '',
            'COMFINO_WIDGET_PRICE_SELECTOR' => COMFINO_PS_17 ? 'span.current-price-value' : 'span[itemprop=price]',
            'COMFINO_WIDGET_TARGET_SELECTOR' => 'div.product-actions',
            'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR' => '',
            'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL' => 0,
            'COMFINO_WIDGET_TYPE' => 'with-modal',
            'COMFINO_WIDGET_OFFER_TYPE' => 'CONVENIENT_INSTALLMENTS',
            'COMFINO_WIDGET_EMBED_METHOD' => 'INSERT_INTO_LAST',
            'COMFINO_WIDGET_CODE' => self::getInitialWidgetCode(),
            'COMFINO_WIDGET_PROD_SCRIPT_VERSION' => '',
            'COMFINO_WIDGET_DEV_SCRIPT_VERSION' => '',
            'COMFINO_IGNORED_STATUSES' => implode(',', StatusManager::DEFAULT_IGNORED_STATUSES),
            'COMFINO_FORBIDDEN_STATUSES' => implode(',', StatusManager::DEFAULT_FORBIDDEN_STATUSES),
            'COMFINO_STATUS_MAP' => json_encode(ShopStatusManager::DEFAULT_STATUS_MAP),
        ];

        foreach ($initialConfigValues as $optName => $optValue) {
            \Configuration::updateValue($optName, $optValue);
        }
    }

    public static function getDefaultConfigurationValues(): array
    {
        return [
            'COMFINO_PAYMENT_TEXT' => '(Raty | Kup Teraz, Zapłać Później | Finansowanie dla Firm)',
            'COMFINO_MINIMAL_CART_AMOUNT' => 30,
            'COMFINO_PRODUCT_CATEGORY_FILTERS' => '',
            'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES' => 'INSTALLMENTS_ZERO_PERCENT,PAY_LATER',
            'COMFINO_WIDGET_ENABLED' => false,
            'COMFINO_WIDGET_KEY' => '',
            'COMFINO_WIDGET_PRICE_SELECTOR' => '',
            'COMFINO_WIDGET_TARGET_SELECTOR' => 'div.product-actions',
            'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR' => '',
            'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL' => 0,
            'COMFINO_WIDGET_TYPE' => 'with-modal',
            'COMFINO_WIDGET_OFFER_TYPE' => 'CONVENIENT_INSTALLMENTS',
            'COMFINO_WIDGET_EMBED_METHOD' => 'INSERT_INTO_LAST',
            'COMFINO_WIDGET_CODE' => self::getInitialWidgetCode(),
            'COMFINO_WIDGET_PROD_SCRIPT_VERSION' => '',
            'COMFINO_WIDGET_DEV_SCRIPT_VERSION' => '',
            'COMFINO_IGNORED_STATUSES' => implode(',', StatusManager::DEFAULT_IGNORED_STATUSES),
            'COMFINO_FORBIDDEN_STATUSES' => implode(',', StatusManager::DEFAULT_FORBIDDEN_STATUSES),
            'COMFINO_STATUS_MAP' => json_encode(ShopStatusManager::DEFAULT_STATUS_MAP),
            'COMFINO_API_CONNECT_TIMEOUT' => 1,
            'COMFINO_API_TIMEOUT' => 3,
        ];
    }

    /**
     * @throws \PrestaShop\PrestaShop\Core\Localization\Exception\LocalizationException
     */
    private static function getProductData(\PaymentModule $module, $productId): array
    {
        $context = \Context::getContext();
        $availOffersUrl = ApiService::getEndpointUrl('availableOfferTypes');

        $price = 'null';

        if ($productId !== null) {
            $availOffersUrl .= ((strpos($availOffersUrl, '?') === false ? '?' : '&') . "product_id=$productId");

            if (($price = \Product::getPriceStatic($productId)) === null) {
                $price = 'null';
            } else {
                $price = (new Tools($context))->getFormattedPrice($price);
            }
        } else {
            $productId = 'null';
        }

        return [
            'product_id' => $productId,
            'price' => $price,
            'avail_offers_url' => $availOffersUrl,
        ];
    }

    private static function getInitialWidgetCode(): string
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
        productId: {PRODUCT_ID},
        productPrice: {PRODUCT_PRICE},
        platform: '{PLATFORM}',
        platformVersion: '{PLATFORM_VERSION}',
        platformDomain: '{PLATFORM_DOMAIN}',
        pluginVersion: '{PLUGIN_VERSION}',
        availOffersUrl: '{AVAILABLE_OFFER_TYPES}',
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
