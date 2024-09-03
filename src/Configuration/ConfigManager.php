<?php

namespace Comfino\Configuration;

use Comfino\Api\ApiClient;
use Comfino\Api\ApiService;
use Comfino\CategoryTree\BuildStrategy;
use Comfino\Common\Backend\Configuration\StorageAdapterInterface;
use Comfino\Common\Backend\ConfigurationManager;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Common\Shop\Product\CategoryTree;
use Comfino\ErrorLogger;
use Comfino\Extended\Api\Serializer\Json as JsonSerializer;
use Comfino\Main;
use Comfino\Order\ShopStatusManager;
use Comfino\PaymentGateway;

if (!defined('ABSPATH')) {
    exit;
}

final class ConfigManager
{
    public const CONFIG_OPTIONS_MAP = [
        'COMFINO_ENABLED' => 'enabled',
        'COMFINO_API_KEY' => 'production_key',
        'COMFINO_PAYMENT_TEXT' => 'title',
        'COMFINO_MINIMAL_CART_AMOUNT' => 'min_cart_amount',
        'COMFINO_SHOW_LOGO' => 'show_logo',
        'COMFINO_IS_SANDBOX' => 'sandbox_mode',
        'COMFINO_DEBUG' => 'debug_mode',
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

    public const CONFIG_OPTIONS = [
        'payment_settings' => [
            'COMFINO_ENABLED' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
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
        'abandoned_cart_settings' => [
            'COMFINO_ABANDONED_CART_ENABLED' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
            'COMFINO_ABANDONED_PAYMENTS' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
        ],
        'developer_settings' => [
            'COMFINO_IS_SANDBOX' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
            'COMFINO_SANDBOX_API_KEY' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_DEBUG' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
        ],
        'hidden_settings' => [
            'COMFINO_WIDGET_PROD_SCRIPT_VERSION' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_DEV_SCRIPT_VERSION' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES' => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY,
            'COMFINO_IGNORED_STATUSES' => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY,
            'COMFINO_FORBIDDEN_STATUSES' => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY,
            'COMFINO_STATUS_MAP' => ConfigurationManager::OPT_VALUE_TYPE_JSON,
            'COMFINO_API_CONNECT_TIMEOUT' => ConfigurationManager::OPT_VALUE_TYPE_INT,
            'COMFINO_API_TIMEOUT' => ConfigurationManager::OPT_VALUE_TYPE_INT,
        ],
    ];

    public const ACCESSIBLE_CONFIG_OPTIONS = [
        'COMFINO_ENABLED',
        'COMFINO_PAYMENT_TEXT',
        'COMFINO_SHOW_LOGO',
        'COMFINO_MINIMAL_CART_AMOUNT',
        'COMFINO_IS_SANDBOX',
        'COMFINO_DEBUG',
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
        'COMFINO_ABANDONED_CART_ENABLED',
        'COMFINO_ABANDONED_PAYMENTS',
        'COMFINO_IGNORED_STATUSES',
        'COMFINO_FORBIDDEN_STATUSES',
        'COMFINO_STATUS_MAP',
        'COMFINO_API_CONNECT_TIMEOUT',
        'COMFINO_API_TIMEOUT',
    ];

    /** @var ConfigurationManager */
    private static $configurationManager;
    /** @var StorageAdapterInterface */
    private static $storageAdapter;
    /** @var int[] */
    private static $availConfigOptions;

    public static function getInstance(): ConfigurationManager
    {
        if (self::$configurationManager === null) {
            self::$storageAdapter = new StorageAdapter();
            self::$availConfigOptions = array_merge(array_merge(...array_values(self::CONFIG_OPTIONS)));

            self::$configurationManager = ConfigurationManager::getInstance(
                self::$availConfigOptions,
                self::ACCESSIBLE_CONFIG_OPTIONS,
                self::$storageAdapter,
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
            'plugin_version' => PaymentGateway::VERSION,
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
            $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name']);

            foreach ($terms as $term) {
                /** @var \WP_Term $term */
                $categories[$term->term_id] = $term->name;
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

    public static function getConfigurationValue(string $optionName, $defaultValue = null)
    {
        if ($defaultValue === null && array_key_exists($optionName, self::CONFIG_OPTIONS_MAP) &&
            ($defaultValue = self::getDefaultValue(self::CONFIG_OPTIONS_MAP[$optionName])) !== null &&
            !is_array($defaultValue) && (self::getConfigurationValueType($optionName) & ConfigurationManager::OPT_VALUE_TYPE_ARRAY)
        ) {
            $defaultValue = array_map('trim', explode(',', $defaultValue));
        }

        return self::getInstance()->getConfigurationValue($optionName) ?? $defaultValue;
    }

    public static function getConfigurationValueType(string $optionName): int
    {
        return self::$availConfigOptions[$optionName] ?? ConfigurationManager::OPT_VALUE_TYPE_STRING;
    }

    public static function isEnabled(): bool
    {
        return self::getInstance()->getConfigurationValue('COMFINO_ENABLED');
    }

    public static function isSandboxMode(): bool
    {
        return self::getInstance()->getConfigurationValue('COMFINO_IS_SANDBOX');
    }

    public static function isWidgetEnabled(): bool
    {
        return self::getInstance()->getConfigurationValue('COMFINO_WIDGET_ENABLED');
    }

    public static function isDebugMode(): bool
    {
        return self::getInstance()->getConfigurationValue('COMFINO_DEBUG') ?? false;
    }

    public static function isAbandonedCartEnabled(): bool
    {
        return self::getInstance()->getConfigurationValue('COMFINO_ABANDONED_CART_ENABLED');
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

    public static function updateConfigurationValue(string $optionName, $optionValue): void
    {
        self::getInstance()->setConfigurationValue($optionName, $optionValue);
        self::getInstance()->persist();
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
        return delete_option(self::$storageAdapter->get_option_key());
    }

    public static function updateWidgetCode(string $lastWidgetCodeHash): void
    {
        ErrorLogger::init(Main::getPluginDirectory(), Main::getPluginFile());

        try {
            $initialWidgetCode = self::getInitialWidgetCode();
            $currentWidgetCode = self::getCurrentWidgetCode();

            if (md5($currentWidgetCode) === $lastWidgetCodeHash) {
                // Widget code not changed since last installed version - safely replace with new one.
                self::updateConfigurationValue('COMFINO_WIDGET_CODE', $initialWidgetCode);
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

    public static function getCurrentWidgetCode(?int $productId = null): string
    {
        $widgetCode = trim(str_replace("\r", '', self::getConfigurationValue('COMFINO_WIDGET_CODE')));
        $productData = self::getProductData($productId);

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

    public static function getWidgetVariables(int $productId = null): array
    {
        $productData = self::getProductData($productId);

        return [
            '{WIDGET_SCRIPT_URL}' => ApiClient::getWidgetScriptUrl(),
            '{PRODUCT_ID}' => $productData['product_id'],
            '{PRODUCT_PRICE}' => $productData['price'],
            '{PLATFORM}' => 'woocommerce',
            '{PLATFORM_VERSION}' => WC_VERSION,
            '{PLATFORM_DOMAIN}' => Main::getShopDomain(),
            '{PLUGIN_VERSION}' => PaymentGateway::VERSION,
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

    public static function getDefaultValue(string $optionName)
    {
        static $optionsMap = null;

        if ($optionsMap === null) {
            $optionsMap = array_flip(self::CONFIG_OPTIONS_MAP);
        }

        if (!isset($optionsMap[$optionName])) {
            return null;
        }

        static $defaultValues = null;

        if ($defaultValues === null) {
            $defaultValues = self::getDefaultConfigurationValues();
        }

        return $defaultValues[$optionsMap[$optionName]] ?? null;
    }

    public static function getDefaultConfigurationValues(): array
    {
        return [
            'COMFINO_ENABLED' => false,
            'COMFINO_PAYMENT_TEXT' => 'Comfino',
            'COMFINO_SHOW_LOGO' => true,
            'COMFINO_MINIMAL_CART_AMOUNT' => 30,
            'COMFINO_IS_SANDBOX' => false,
            'COMFINO_DEBUG' => false,
            'COMFINO_PRODUCT_CATEGORY_FILTERS' => '',
            'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES' => 'INSTALLMENTS_ZERO_PERCENT,PAY_LATER',
            'COMFINO_WIDGET_ENABLED' => false,
            'COMFINO_WIDGET_KEY' => '',
            'COMFINO_WIDGET_PRICE_SELECTOR' => '.price .woocommerce-Price-amount bdi',
            'COMFINO_WIDGET_TARGET_SELECTOR' => '.summary .product_meta',
            'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR' => '',
            'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL' => 0,
            'COMFINO_WIDGET_TYPE' => 'with-modal',
            'COMFINO_WIDGET_OFFER_TYPE' => 'CONVENIENT_INSTALLMENTS',
            'COMFINO_WIDGET_EMBED_METHOD' => 'INSERT_INTO_LAST',
            'COMFINO_WIDGET_CODE' => self::getInitialWidgetCode(),
            'COMFINO_WIDGET_PROD_SCRIPT_VERSION' => '',
            'COMFINO_WIDGET_DEV_SCRIPT_VERSION' => '',
            'COMFINO_ABANDONED_CART_ENABLED' => false,
            'COMFINO_ABANDONED_PAYMENTS' => 'comfino',
            'COMFINO_IGNORED_STATUSES' => implode(',', StatusManager::DEFAULT_IGNORED_STATUSES),
            'COMFINO_FORBIDDEN_STATUSES' => implode(',', StatusManager::DEFAULT_FORBIDDEN_STATUSES),
            'COMFINO_STATUS_MAP' => json_encode(ShopStatusManager::DEFAULT_STATUS_MAP),
            'COMFINO_API_CONNECT_TIMEOUT' => 1,
            'COMFINO_API_TIMEOUT' => 3,
        ];
    }

    private static function getProductData(?int $productId): array
    {
        $availOffersUrl = ApiService::getEndpointUrl('availableOfferTypes');

        $price = 'null';

        if ($productId !== null) {
            $availOffersUrl .= "/$productId";

            if (($product = wc_get_product($productId)) instanceof \WC_Product) {
                $price = (float) preg_replace([
                    '/[^\d,.]/',
                    '/(?<=\d),(?=\d{3}(?:[^\d]|$))/',
                    '/,00$/',
                    '/,/'
                ], [
                    '',
                    '',
                    '',
                    '.'
                ], $product->get_price());
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
