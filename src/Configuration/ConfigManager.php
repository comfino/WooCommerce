<?php

namespace Comfino\Configuration;

use Comfino\Api\ApiClient;
use Comfino\CategoryTree\BuildStrategy;
use Comfino\Common\Backend\Configuration\StorageAdapterInterface;
use Comfino\Common\Backend\ConfigurationManager;
use Comfino\Common\Frontend\FrontendHelper;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Common\Shop\Product\CategoryTree;
use Comfino\Extended\Api\Serializer\Json as JsonSerializer;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Main;
use Comfino\Order\OrderManager;
use Comfino\Order\ShopStatusManager;
use Comfino\PaymentGateway;
use Comfino\View\FrontendManager;

if (!defined('ABSPATH')) {
    exit;
}

final class ConfigManager
{
    public const CONFIG_OPTIONS_MAP = [
        'COMFINO_ENABLED' => 'enabled',
        'COMFINO_API_KEY' => 'production_key',
        'COMFINO_PAYMENT_TEXT_ENABLED' => 'payment_text_enabled',
        'COMFINO_PAYMENT_TEXT' => 'payment_text',
        'COMFINO_CHECKOUT_PRODUCT_TYPES' => 'checkout_product_types',
        'COMFINO_CHECKOUT_PRODUCT_TYPES_ORDER' => 'checkout_product_types_order',
        'COMFINO_MINIMAL_CART_AMOUNT' => 'min_cart_amount',
        'COMFINO_CART_VALUE_LIMITS_CONFIG' => 'cart_value_limits_config',
        'COMFINO_USE_ORDER_REFERENCE' => 'use_order_reference',
        'COMFINO_IS_SANDBOX' => 'sandbox_mode',
        'COMFINO_DEBUG' => 'debug_mode',
        'COMFINO_SERVICE_MODE' => 'service_mode',
        'COMFINO_DEV_ENV_VARS' => 'dev_env_vars',
        'COMFINO_SANDBOX_API_KEY' => 'sandbox_key',
        'COMFINO_PRODUCT_CATEGORY_FILTERS' => 'product_category_filters',
        'COMFINO_PRODUCT_ID_FILTER' => 'product_id_filter',
        'COMFINO_ALLOWED_PRODUCTS_CONFIG' => 'allowed_products_config',
        'COMFINO_ALLOWED_PRODUCTS_CONFIG_ENABLED' => 'allowed_products_config_enabled',
        'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES' => 'cat_filter_avail_prod_types',
        'COMFINO_ALLOWED_PRODUCTS_CONFIG_FORBIDDEN_PROD_TYPES' => 'allowed_products_config_forbidden_prod_types',
        'COMFINO_PAYWALL_DIRECT_REDIRECT' => 'paywall_direct_redirect',
        'COMFINO_PAYWALL_CUSTOM_CSS_URL' => 'paywall_custom_css_url',
        'COMFINO_WIDGET_ENABLED' => 'widget_enabled',
        'COMFINO_WIDGET_KEY' => 'widget_key',
        'COMFINO_WIDGET_PRICE_SELECTOR' => 'widget_price_selector',
        'COMFINO_WIDGET_PRICE_ATTRIBUTE' => 'widget_price_attribute',
        'COMFINO_WIDGET_TARGET_SELECTOR' => 'widget_target_selector',
        'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR' => 'widget_price_observer_selector',
        'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL' => 'widget_price_observer_level',
        'COMFINO_WIDGET_TYPE' => 'widget_type',
        'COMFINO_WIDGET_OFFER_TYPES' => 'widget_offer_types',
        'COMFINO_WIDGET_EMBED_METHOD' => 'widget_embed_method',
        'COMFINO_WIDGET_SHOW_PROVIDER_LOGOS' => 'widget_show_provider_logos',
        'COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL' => 'widget_custom_banner_css_url',
        'COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL' => 'widget_custom_calculator_css_url',
        'COMFINO_WIDGET_DISABLE_BANNER' => 'widget_disable_banner',
        'COMFINO_WIDGET_CALCULATOR_TRIGGER_SELECTOR' => 'widget_calculator_trigger_selector',
        'COMFINO_ABANDONED_CART_ENABLED' => 'abandoned_cart_enabled',
        'COMFINO_ABANDONED_PAYMENTS' => 'abandoned_payments',
        'COMFINO_IGNORED_STATUSES' => 'ignored_statuses',
        'COMFINO_FORBIDDEN_STATUSES' => 'forbidden_statuses',
        'COMFINO_STATUS_MAP' => 'status_map',
        'COMFINO_API_CONNECT_TIMEOUT' => 'api_connect_timeout',
        'COMFINO_API_TIMEOUT' => 'api_timeout',
        'COMFINO_API_CONNECT_NUM_ATTEMPTS' => 'api_connect_num_attempts',
        'COMFINO_ERROR_LOGGING_ACCESS_TOKEN' => 'error_logging_access_token',
        'COMFINO_ERROR_LOGGING_ACCESS_TOKEN_EXPIRES_AT' => 'error_logging_access_token_expires_at',
        'COMFINO_REMOTE_FLAGS' => 'remote_flags',
        'COMFINO_REMOTE_FLAG_ATTRIBUTES' => 'remote_flag_attributes',
    ];

    public const CONFIG_OPTIONS = [
        'payment_settings' => [
            'COMFINO_ENABLED' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
            'COMFINO_API_KEY' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_PAYMENT_TEXT_ENABLED' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
            'COMFINO_PAYMENT_TEXT' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_CHECKOUT_PRODUCT_TYPES' => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY,
            'COMFINO_CHECKOUT_PRODUCT_TYPES_ORDER' => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY,
            'COMFINO_MINIMAL_CART_AMOUNT' => ConfigurationManager::OPT_VALUE_TYPE_FLOAT,
            'COMFINO_CART_VALUE_LIMITS_CONFIG' => ConfigurationManager::OPT_VALUE_TYPE_JSON,
            'COMFINO_USE_ORDER_REFERENCE' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
            'COMFINO_PAYWALL_DIRECT_REDIRECT' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
            'COMFINO_PAYWALL_CUSTOM_CSS_URL' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
        ],
        'sale_settings' => [
            'COMFINO_ALLOWED_PRODUCTS_CONFIG'  => ConfigurationManager::OPT_VALUE_TYPE_JSON,
            'COMFINO_PRODUCT_CATEGORY_FILTERS' => ConfigurationManager::OPT_VALUE_TYPE_JSON,
            'COMFINO_PRODUCT_ID_FILTER'        => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY,
        ],
        'widget_settings' => [
            'COMFINO_WIDGET_ENABLED' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
            'COMFINO_WIDGET_KEY' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_PRICE_SELECTOR' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_PRICE_ATTRIBUTE' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_TARGET_SELECTOR' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL' => ConfigurationManager::OPT_VALUE_TYPE_INT,
            'COMFINO_WIDGET_TYPE' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_OFFER_TYPES' => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY,
            'COMFINO_WIDGET_EMBED_METHOD' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_SHOW_PROVIDER_LOGOS' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
            'COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_WIDGET_DISABLE_BANNER' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
            'COMFINO_WIDGET_CALCULATOR_TRIGGER_SELECTOR' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
        ],
        'abandoned_cart_settings' => [
            'COMFINO_ABANDONED_CART_ENABLED' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
            'COMFINO_ABANDONED_PAYMENTS' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
        ],
        'developer_settings' => [
            'COMFINO_IS_SANDBOX' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
            'COMFINO_SANDBOX_API_KEY' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_DEBUG' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
            'COMFINO_SERVICE_MODE' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
            'COMFINO_DEV_ENV_VARS' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
        ],
        'hidden_settings' => [
            'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES' => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY,
            'COMFINO_ALLOWED_PRODUCTS_CONFIG_FORBIDDEN_PROD_TYPES' => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY,
            'COMFINO_ALLOWED_PRODUCTS_CONFIG_ENABLED' => ConfigurationManager::OPT_VALUE_TYPE_BOOL,
            'COMFINO_IGNORED_STATUSES' => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY,
            'COMFINO_FORBIDDEN_STATUSES' => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY,
            'COMFINO_STATUS_MAP' => ConfigurationManager::OPT_VALUE_TYPE_JSON,
            'COMFINO_API_CONNECT_TIMEOUT' => ConfigurationManager::OPT_VALUE_TYPE_INT,
            'COMFINO_API_TIMEOUT' => ConfigurationManager::OPT_VALUE_TYPE_INT,
            'COMFINO_API_CONNECT_NUM_ATTEMPTS' => ConfigurationManager::OPT_VALUE_TYPE_INT,
            'COMFINO_ERROR_LOGGING_ACCESS_TOKEN' => ConfigurationManager::OPT_VALUE_TYPE_STRING,
            'COMFINO_ERROR_LOGGING_ACCESS_TOKEN_EXPIRES_AT' => ConfigurationManager::OPT_VALUE_TYPE_INT,
            'COMFINO_REMOTE_FLAGS' => ConfigurationManager::OPT_VALUE_TYPE_STRING_ARRAY,
            'COMFINO_REMOTE_FLAG_ATTRIBUTES' => ConfigurationManager::OPT_VALUE_TYPE_JSON,
        ],
    ];

    public const ACCESSIBLE_CONFIG_OPTIONS = [
        'COMFINO_ENABLED',
        'COMFINO_PAYMENT_TEXT_ENABLED',
        'COMFINO_PAYMENT_TEXT',
        'COMFINO_CHECKOUT_PRODUCT_TYPES',
        'COMFINO_CHECKOUT_PRODUCT_TYPES_ORDER',
        'COMFINO_MINIMAL_CART_AMOUNT',
        'COMFINO_CART_VALUE_LIMITS_CONFIG',
        'COMFINO_USE_ORDER_REFERENCE',
        'COMFINO_PAYWALL_DIRECT_REDIRECT',
        'COMFINO_PAYWALL_CUSTOM_CSS_URL',
        'COMFINO_IS_SANDBOX',
        'COMFINO_DEBUG',
        'COMFINO_SERVICE_MODE',
        'COMFINO_ALLOWED_PRODUCTS_CONFIG',
        'COMFINO_ALLOWED_PRODUCTS_CONFIG_ENABLED',
        'COMFINO_PRODUCT_CATEGORY_FILTERS',
        'COMFINO_PRODUCT_ID_FILTER',
        'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES',
        'COMFINO_ALLOWED_PRODUCTS_CONFIG_FORBIDDEN_PROD_TYPES',
        'COMFINO_WIDGET_ENABLED',
        'COMFINO_WIDGET_KEY',
        'COMFINO_WIDGET_PRICE_SELECTOR',
        'COMFINO_WIDGET_PRICE_ATTRIBUTE',
        'COMFINO_WIDGET_TARGET_SELECTOR',
        'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR',
        'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL',
        'COMFINO_WIDGET_TYPE',
        'COMFINO_WIDGET_OFFER_TYPES',
        'COMFINO_WIDGET_EMBED_METHOD',
        'COMFINO_WIDGET_SHOW_PROVIDER_LOGOS',
        'COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL',
        'COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL',
        'COMFINO_WIDGET_DISABLE_BANNER',
        'COMFINO_WIDGET_CALCULATOR_TRIGGER_SELECTOR',
        'COMFINO_ABANDONED_CART_ENABLED',
        'COMFINO_ABANDONED_PAYMENTS',
        'COMFINO_IGNORED_STATUSES',
        'COMFINO_FORBIDDEN_STATUSES',
        'COMFINO_STATUS_MAP',
        'COMFINO_API_CONNECT_TIMEOUT',
        'COMFINO_API_TIMEOUT',
        'COMFINO_API_CONNECT_NUM_ATTEMPTS',
        'COMFINO_DEV_ENV_VARS',
        'COMFINO_REMOTE_FLAGS',
        'COMFINO_REMOTE_FLAG_ATTRIBUTES',
    ];

    private const CONFIG_MANAGER_OPTIONS = 0;

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
            self::$configurationManager = ConfigurationManager::getInstance(
                self::getAvailableConfigOptions(),
                self::ACCESSIBLE_CONFIG_OPTIONS,
                self::CONFIG_MANAGER_OPTIONS,
                self::$storageAdapter,
                new JsonSerializer()
            );
        }

        return self::$configurationManager;
    }

    /**
     * @param string[]|null $selectedEnvFields
     *
     * @return string[]
     */
    public static function getEnvironmentInfo(?array $selectedEnvFields = null): array
    {
        global $wp_version, $wpdb;

        $envFields = [
            'plugin_version' => PaymentGateway::VERSION,
            'plugin_build_ts' => PaymentGateway::BUILD_TS,
            'shop_version' => WC_VERSION,
            'wordpress_version' => $wp_version,
            'php_version' => PHP_VERSION,
            'server_software' => sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'] ?? 'n/a')),
            'server_name' => sanitize_text_field(wp_unslash($_SERVER['SERVER_NAME'] ?? 'n/a')),
            'server_addr' => sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR'] ?? 'n/a')),
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

    public static function getConfigurationValueByInternalName(string $optionName, $defaultValue = null)
    {
        if (($externalOptionName = self::getExternalOptionName($optionName)) === null) {
            return $defaultValue;
        }

        return self::getConfigurationValue($externalOptionName, $defaultValue);
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
        return self::getAvailableConfigOptions()[$optionName] ?? ConfigurationManager::OPT_VALUE_TYPE_STRING;
    }

    public static function isEnabled(): bool
    {
        return self::getInstance()->getConfigurationValue('COMFINO_ENABLED') ?? false;
    }

    public static function isSandboxMode(): bool
    {
        return self::getInstance()->getConfigurationValue('COMFINO_IS_SANDBOX') ?? false;
    }

    public static function isWidgetEnabled(): bool
    {
        return self::getInstance()->getConfigurationValue('COMFINO_WIDGET_ENABLED') ?? false;
    }

    public static function isDebugMode(): bool
    {
        return self::getInstance()->getConfigurationValue('COMFINO_DEBUG') ?? false;
    }

    public static function isServiceMode(): bool
    {
        return self::getInstance()->getConfigurationValue('COMFINO_SERVICE_MODE') ?? false;
    }

    public static function useDevEnvVars(): bool
    {
        return getenv('COMFINO_DEV_ENV') === 'TRUE' && self::getInstance()->getConfigurationValue('COMFINO_DEV_ENV_VARS') ?? false;
    }

    public static function useUnminifiedScripts(): bool
    {
        return getenv('COMFINO_DEV_USE_UNMINIFIED_SCRIPTS') === 'TRUE';
    }

    public static function isAbandonedCartEnabled(): bool
    {
        return self::getInstance()->getConfigurationValue('COMFINO_ABANDONED_CART_ENABLED') ?? false;
    }

    public static function isOrderReferenceEnabled(): bool
    {
        return self::getInstance()->getConfigurationValue('COMFINO_USE_ORDER_REFERENCE') ?? false;
    }

    /**
     * @return string[]
     */
    public static function getWidgetOfferTypes(): array
    {
        if (is_array($offerTypes = self::getConfigurationValue('COMFINO_WIDGET_OFFER_TYPES'))) {
            return $offerTypes;
        }

        $offerType = self::getConfigurationValue('COMFINO_WIDGET_OFFER_TYPE');

        return !empty($offerType) ? [$offerType] : [];
    }

    public static function getLogoApiHost(): string
    {
        return self::getApiHost(ApiClient::getInstance()->getApiHost());
    }

    /**
     * Returns the paywall logo auth hash as raw base64 (no URL-encoding). The value flows through
     * `comfinoSettings` / `comfino_data` into the JS SDK, which builds the logo URL via
     * URLSearchParams and percent-encodes the auth there. Returning a pre-encoded string would
     * double-encode `+` / `/` / `=` and invalidate the signature.
     */
    public static function getPaywallLogoAuthHash(): string
    {
        return FrontendHelper::getPaywallLogoAuthHashRaw(
            'WC',
            WC_VERSION,
            PaymentGateway::VERSION,
            ApiClient::getInstance()->getApiKey(),
            self::getWidgetKey(),
            PaymentGateway::BUILD_TS
        );
    }

    /**
     * Returns the checkout payment method item label sent to the frontend SDK: either the merchant's custom text
     * (COMFINO_PAYMENT_TEXT) when COMFINO_PAYMENT_TEXT_ENABLED is on, or the list of selected financial product
     * type codes (COMFINO_CHECKOUT_PRODUCT_TYPES) so the SDK renders its own localized label/names for them.
     *
     * @return string|string[]|null
     */
    public static function getPaymentMethodLabel()
    {
        if (self::getConfigurationValue('COMFINO_PAYMENT_TEXT_ENABLED')) {
            return self::getConfigurationValue('COMFINO_PAYMENT_TEXT') ?: null;
        }

        $productTypes = self::getConfigurationValue('COMFINO_CHECKOUT_PRODUCT_TYPES');

        return !empty($productTypes) ? array_values($productTypes) : null;
    }

    public static function getApiHost(?string $apiHost = null): ?string
    {
        if (self::useDevEnvVars() && getenv('COMFINO_DEV_API_HOST')) {
            return getenv('COMFINO_DEV_API_HOST');
        }

        return $apiHost;
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

    public static function getErrorLoggingAccessToken(): string
    {
        return (string) (self::getInstance()->getConfigurationValue('COMFINO_ERROR_LOGGING_ACCESS_TOKEN') ?? '');
    }

    public static function getErrorLoggingAccessTokenExpiresAt(): int
    {
        return (int) (self::getInstance()->getConfigurationValue('COMFINO_ERROR_LOGGING_ACCESS_TOKEN_EXPIRES_AT') ?? 0);
    }

    public static function refreshErrorLoggingTokenIfNeeded(): void
    {
        if (empty(self::getApiKey())) {
            return;
        }

        if (self::getErrorLoggingAccessToken() !== '' && self::getErrorLoggingAccessTokenExpiresAt() > time() + 3600) {
            return;
        }

        try {
            $response = ApiClient::getInstance()->claimErrorLoggingToken();

            if ($response !== null) {
                self::updateConfigurationValue('COMFINO_ERROR_LOGGING_ACCESS_TOKEN', $response->accessToken);
                self::updateConfigurationValue('COMFINO_ERROR_LOGGING_ACCESS_TOKEN_EXPIRES_AT', strtotime($response->expiresAt));

                if (self::updateRemoteFlagsIfChanged($response->getHeader('Comfino-Flags', ''))) {
                    /* Attributes are only ever set/changed together with their flag, so it's enough to re-fetch them
                       when the flag list itself changed - saves an extra API call on every other refresh. */
                    self::updateConfigurationValue(
                        'COMFINO_REMOTE_FLAG_ATTRIBUTES',
                        ApiClient::getInstance()->getUserSettings()->flags
                    );
                }
            }
        } catch (\Throwable) {
            // Silently ignore — CETS token claim is best-effort.
        }
    }

    /**
     * @return string[]
     */
    public static function getRemoteFlags(): array
    {
        if (!is_array($remoteFlags = self::getConfigurationValue('COMFINO_REMOTE_FLAGS'))) {
            return [];
        }

        return $remoteFlags;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getRemoteFlagAttributes(): array
    {
        if (!is_array($flagAttributes = self::getConfigurationValue('COMFINO_REMOTE_FLAG_ATTRIBUTES'))) {
            return [];
        }

        return $flagAttributes;
    }

    /**
     * Priority order of financial product type codes for the checkout payment label: preselection and the checkboxes
     * list both follow it. Stored as a plain configuration value (not hardcoded) so Comfino can update it remotely via
     * the /configuration endpoint without a plugin code change or upgrade.
     *
     * @return string[]
     */
    public static function getCheckoutProductTypesOrder(): array
    {
        if (!is_array($order = self::getConfigurationValue('COMFINO_CHECKOUT_PRODUCT_TYPES_ORDER'))) {
            return [];
        }

        return $order;
    }

    private static function updateRemoteFlagsIfChanged(string $flagsHeaderValue): bool
    {
        $remoteFlags = array_values(array_unique(array_filter(array_map('trim', explode(',', $flagsHeaderValue)))));

        sort($remoteFlags);

        $storedFlags = self::getRemoteFlags();

        sort($storedFlags);

        if ($remoteFlags === $storedFlags) {
            return false;
        }

        self::updateConfigurationValue('COMFINO_REMOTE_FLAGS', $remoteFlags);

        return true;
    }

    /**
     * @return string[]
     */
    public static function getIgnoredStatuses(): array
    {
        if (!is_array($ignoredStatuses = self::getConfigurationValue('COMFINO_IGNORED_STATUSES'))) {
            $ignoredStatuses = null;
        }

        return $ignoredStatuses ?? StatusManager::DEFAULT_IGNORED_STATUSES;
    }

    /**
     * @return string[]
     */
    public static function getForbiddenStatuses(): array
    {
        if (!is_array($forbiddenStatuses = self::getConfigurationValue('COMFINO_FORBIDDEN_STATUSES'))) {
            $forbiddenStatuses = null;
        }

        return $forbiddenStatuses ?? StatusManager::DEFAULT_FORBIDDEN_STATUSES;
    }

    /**
     * @return string[]
     */
    public static function getStatusMap(): array
    {
        if (!is_array($statusMap = self::getConfigurationValue('COMFINO_STATUS_MAP'))) {
            $statusMap = null;
        }

        return $statusMap ?? ShopStatusManager::DEFAULT_STATUS_MAP;
    }

    public static function initConfigurationValues(array $configurationOptions): void
    {
        self::updateConfiguration(
            array_filter(
                $configurationOptions,
                static function ($optionName) { return self::getConfigurationValue($optionName) === null; },
                ARRAY_FILTER_USE_KEY
            ),
            false
        );
    }

    /**
     * Repairs missing configuration options by adding them with default values.
     * Does not overwrite existing options - only creates missing ones.
     *
     * @return array Statistics about the repair operation with keys:
     *               - 'checked': Total number of options checked.
     *               - 'missing': Number of missing options found.
     *               - 'repaired': Number of options successfully repaired.
     *               - 'failed': Number of options that failed to repair.
     *               - 'options_repaired': Array of option names that were repaired.
     *               - 'options_failed': Array of option names that failed to repair
     */
    public static function repairMissingConfigurationOptions(): array
    {
        $resultStats = [
            'checked' => 0,
            'missing' => 0,
            'repaired' => 0,
            'failed' => 0,
            'options_repaired' => [],
            'options_failed' => [],
        ];

        $defaultValues = self::getDefaultConfigurationValues();
        $optionsToInit = [];

        foreach ($defaultValues as $optName => $optValue) {
            $resultStats['checked']++;

            if (self::getConfigurationValue($optName) === null) {
                $resultStats['missing']++;
                $optionsToInit[$optName] = $optValue;
            }
        }

        try {
            self::updateConfiguration($optionsToInit, false);

            $resultStats['repaired'] = count($optionsToInit);
            $resultStats['options_repaired'] = array_merge($resultStats['options_repaired'], array_keys($optionsToInit));
        } catch (\Throwable $e) {
            $resultStats['failed'] = count($optionsToInit);
            $resultStats['options_failed'] = array_merge($resultStats['options_failed'], array_keys($optionsToInit));
        }

        return $resultStats;
    }

    public static function updateConfigurationValue(string $optionName, $optionValue): void
    {
        self::getInstance()->setConfigurationValue($optionName, $optionValue);
        self::getInstance()->persist();
    }

    public static function updateConfiguration(array $configurationOptions, $onlyAccessibleOptions = true): void
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
        return delete_option(self::getStorageAdapter()->get_option_key());
    }

    public static function getSdkScriptUrl(): string
    {
        return self::resolveSdkScriptUrl('comfino-sdk.min.js', 'COMFINO_DEV_SDK_SCRIPT_URL');
    }

    public static function getCheckoutScriptUrl(): string
    {
        if (self::useDevEnvVars() && getenv('COMFINO_DEV_CHECKOUT_SCRIPT_URL')) {
            return sanitize_url(wp_unslash(getenv('COMFINO_DEV_CHECKOUT_SCRIPT_URL')));
        }

        $fileName = (self::useDevEnvVars() && self::useUnminifiedScripts())
            ? 'comfino-woocommerce.js'
            : 'comfino-woocommerce.min.js';

        return sanitize_url(wp_unslash(FrontendManager::getSdkCdnBaseUrl() . "/checkout/v1/$fileName"));
    }

    /**
     * CDN URL of the WooCommerce product-page widget script served from the SDK host at /product/v1/.
     * The product-page sibling of getCheckoutScriptUrl(): the classic-IIFE script reads the
     * `#comfino-widget-config` JSON block and calls sdk.bootstrapWidget().
     */
    public static function getProductWidgetScriptUrl(): string
    {
        $fileName = (self::useDevEnvVars() && self::useUnminifiedScripts())
            ? 'comfino-woocommerce-widget.js'
            : 'comfino-woocommerce-widget.min.js';

        return sanitize_url(wp_unslash(FrontendManager::getSdkCdnBaseUrl() . "/product/v1/$fileName"));
    }

    public static function getBlocksCheckoutScriptUrl(): string
    {
        if (self::useDevEnvVars() && getenv('COMFINO_DEV_BLOCKS_SCRIPT_URL')) {
            return sanitize_url(wp_unslash(getenv('COMFINO_DEV_BLOCKS_SCRIPT_URL')));
        }

        $fileName = (self::useDevEnvVars() && self::useUnminifiedScripts())
            ? 'comfino-woocommerce-blocks.js'
            : 'comfino-woocommerce-blocks.min.js';

        return sanitize_url(wp_unslash(FrontendManager::getSdkCdnBaseUrl() . "/checkout/v1/$fileName"));
    }

    public static function getCheckoutCssUrl(): string
    {
        if (self::useDevEnvVars() && getenv('COMFINO_DEV_CHECKOUT_CSS_URL')) {
            return sanitize_url(wp_unslash(getenv('COMFINO_DEV_CHECKOUT_CSS_URL')));
        }

        return sanitize_url(wp_unslash(FrontendManager::getSdkCdnBaseUrl() . '/checkout/v1/css/comfino-item-gate-woocommerce.css'));
    }

    /**
     * CDN URL of the single, SDK-hosted Comfino brand logo used as the default payment-tile placeholder across all
     * shop plugins/platforms. Rendered by the plugin as the tile's initial logo (get_icon() override for classic
     * checkout, the registered `label` node for Blocks); the SDK renderer adopts it and swaps its `src` at runtime
     * (to the auth-gated API Comfino logo). Hosting it centrally on the SDK CDN keeps the asset controllable without
     * plugin updates.
     */
    public static function getDefaultLogoUrl(): string
    {
        if (self::useDevEnvVars() && getenv('COMFINO_DEV_DEFAULT_LOGO_URL')) {
            return sanitize_url(wp_unslash(getenv('COMFINO_DEV_DEFAULT_LOGO_URL')));
        }

        return sanitize_url(wp_unslash(FrontendManager::getSdkCdnBaseUrl() . '/images/comfino/comfino_logo.svg'));
    }

    /**
     * Compose the CDN URL of an SDK bundle served from /sdk/v1/ on sdk.comfino.pl. Resolution order:
     *   1. An explicit full-URL dev override ($devUrlEnvVar) wins outright.
     *   2. Otherwise, the host comes from FrontendManager::getSdkCdnBaseUrl() — so COMFINO_DEV_SDK_CDN_BASE_URL points
     *      the SDK at the local dev server.
     *
     * In both branches the .min suffix is dropped when COMFINO_DEV_USE_UNMINIFIED_SCRIPTS is on.
     */
    private static function resolveSdkScriptUrl(string $scriptFileName, string $devUrlEnvVar): string
    {
        $unminified = self::useDevEnvVars() && self::useUnminifiedScripts();

        if (self::useDevEnvVars() && getenv($devUrlEnvVar)) {
            $sdkScriptUrl = sanitize_url(wp_unslash(getenv($devUrlEnvVar)));

            if ($unminified) {
                $sdkScriptUrl = str_replace('.min.js', '.js', $sdkScriptUrl);
            }

            if ($sdkScriptUrl !== '') {
                return $sdkScriptUrl;
            }
        }

        if ($unminified) {
            $scriptFileName = str_replace('.min.js', '.js', $scriptFileName);
        }

        return sanitize_url(wp_unslash(FrontendManager::getSdkCdnBaseUrl() . "/sdk/v1/$scriptFileName"));
    }

    public static function getWidgetVariables(?int $productId = null): array
    {
        $productData = self::getProductData($productId);

        return [
            'PRODUCT_ID' => $productData['product_id'],
            'PRODUCT_PRICE' => $productData['price'],
            'PLATFORM' => 'woocommerce',
            'PLATFORM_NAME' => 'WooCommerce',
            'PLATFORM_VERSION' => WC_VERSION,
            'PLATFORM_DOMAIN' => Main::getShopDomain(),
            'PLUGIN_VERSION' => PaymentGateway::VERSION,
            'AVAILABLE_PRODUCT_TYPES' => $productData['available_product_types'],
            'PRODUCT_CART_DETAILS' => $productData['product_cart_details'],
            'LANGUAGE' => Main::getShopLanguage(),
            'CURRENCY' => Main::getShopCurrency(),
            'LOGGING_TOKEN' => FrontendManager::getLoggingToken(),
            'TRACK_ID' => FrontendManager::getTrackId(),
        ];
    }

    public static function getConfigurationValues(string $optionsGroup, array $optionsToReturn = []): array
    {
        if (!array_key_exists($optionsGroup, self::CONFIG_OPTIONS)) {
            return [];
        }

        return count($optionsToReturn)
            ? self::getInstance()->getConfigurationValues($optionsToReturn)
            : self::getInstance()->getConfigurationValues(array_keys(self::CONFIG_OPTIONS[$optionsGroup]));
    }

    public static function getDefaultValue(string $optionName)
    {
        static $defaultValues = null;

        if ($defaultValues === null) {
            $defaultValues = self::getDefaultConfigurationValues();
        }

        if (($externalOptionName = self::getExternalOptionName($optionName)) === null) {
            return null;
        }

        return $defaultValues[$externalOptionName] ?? null;
    }

    public static function getDefaultConfigurationValues(): array
    {
        return [
            'COMFINO_ENABLED' => false,
            'COMFINO_PAYMENT_TEXT_ENABLED' => false,
            'COMFINO_PAYMENT_TEXT' => 'Comfino',
            'COMFINO_CHECKOUT_PRODUCT_TYPES' => ['INSTALLMENTS_ZERO_PERCENT', 'PAY_LATER'],
            'COMFINO_CHECKOUT_PRODUCT_TYPES_ORDER' => [
                'INSTALLMENTS_ZERO_PERCENT',
                'PAY_LATER',
                'CONVENIENT_INSTALLMENTS',
                'COMPANY_INSTALLMENTS',
                'COMPANY_BNPL',
                'PAY_IN_PARTS',
                'INSTANT_PAYMENTS',
                'LEASING',
                'BLIK',
            ],
            'COMFINO_MINIMAL_CART_AMOUNT' => 30,
            'COMFINO_CART_VALUE_LIMITS_CONFIG' => null,
            'COMFINO_USE_ORDER_REFERENCE' => false,
            'COMFINO_IS_SANDBOX' => false,
            'COMFINO_DEBUG' => false,
            'COMFINO_SERVICE_MODE' => false,
            'COMFINO_PRODUCT_CATEGORY_FILTERS' => '',
            'COMFINO_PRODUCT_ID_FILTER' => '',
            'COMFINO_ALLOWED_PRODUCTS_CONFIG' => null,
            'COMFINO_ALLOWED_PRODUCTS_CONFIG_ENABLED' => false,
            'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES' => 'INSTALLMENTS_ZERO_PERCENT,PAY_LATER,COMPANY_BNPL,COMPANY_INSTALLMENTS,LEASING,PAY_IN_PARTS',
            'COMFINO_ALLOWED_PRODUCTS_CONFIG_FORBIDDEN_PROD_TYPES' => 'BLIK,PAY_LATER,PAY_IN_PARTS,INSTANT_PAYMENTS',
            'COMFINO_PAYWALL_DIRECT_REDIRECT' => false,
            'COMFINO_PAYWALL_CUSTOM_CSS_URL' => '',
            'COMFINO_WIDGET_ENABLED' => false,
            'COMFINO_WIDGET_KEY' => '',
            'COMFINO_WIDGET_PRICE_SELECTOR' => '.price .woocommerce-Price-amount bdi',
            'COMFINO_WIDGET_PRICE_ATTRIBUTE' => '',
            'COMFINO_WIDGET_TARGET_SELECTOR' => '.summary .product_meta',
            'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR' => '',
            'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL' => 0,
            'COMFINO_WIDGET_TYPE' => 'standard',
            'COMFINO_WIDGET_OFFER_TYPES' => ['CONVENIENT_INSTALLMENTS'],
            'COMFINO_WIDGET_EMBED_METHOD' => 'INSERT_INTO_LAST',
            'COMFINO_ABANDONED_CART_ENABLED' => false,
            'COMFINO_ABANDONED_PAYMENTS' => 'comfino',
            'COMFINO_WIDGET_SHOW_PROVIDER_LOGOS' => false,
            'COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL' => '',
            'COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL' => '',
            'COMFINO_WIDGET_DISABLE_BANNER' => false,
            'COMFINO_WIDGET_CALCULATOR_TRIGGER_SELECTOR' => '',
            'COMFINO_IGNORED_STATUSES' => implode(',', StatusManager::DEFAULT_IGNORED_STATUSES),
            'COMFINO_FORBIDDEN_STATUSES' => implode(',', StatusManager::DEFAULT_FORBIDDEN_STATUSES),
            'COMFINO_STATUS_MAP' => wp_json_encode(ShopStatusManager::DEFAULT_STATUS_MAP),
            'COMFINO_API_CONNECT_TIMEOUT' => 1,
            'COMFINO_API_TIMEOUT' => 3,
            'COMFINO_API_CONNECT_NUM_ATTEMPTS' => 3,
            'COMFINO_DEV_ENV_VARS' => false,
            'COMFINO_REMOTE_FLAGS' => [],
            'COMFINO_REMOTE_FLAG_ATTRIBUTES' => null,
        ];
    }

    private static function getExternalOptionName(string $internalOptionName): ?string
    {
        static $optionsMap = null;

        if ($optionsMap === null) {
            $optionsMap = array_flip(self::CONFIG_OPTIONS_MAP);
        }

        return $optionsMap[$internalOptionName] ?? null;
    }

    private static function getStorageAdapter(): StorageAdapterInterface
    {
        return self::$storageAdapter ?? (self::$storageAdapter = new StorageAdapter());
    }

    private static function getProductData(?int $productId): array
    {
        $price = 'null';
        $productCartDetails = 'null';

        if ($productId !== null && ($product = wc_get_product($productId)) instanceof \WC_Product) {
            $shopCart = OrderManager::getShopCartFromProduct($product);

            $price = (float) preg_replace(
                ['/[^\d,.]/', '/(?<=\d),(?=\d{3}(?:[^\d]|$))/', '/,00$/', '/,/'],
                ['', '', '', '.'],
                $product->get_price()
            );
            $availableProductTypes = SettingsManager::getAllowedProductTypes(
                ProductTypesListTypeEnum::LIST_TYPE_WIDGET,
                $shopCart,
                true
            );
            $productCartDetails = $shopCart->getAsArray();
        } else {
            $availableProductTypes = SettingsManager::getProductTypesStrings(
                ProductTypesListTypeEnum::LIST_TYPE_WIDGET
            );
        }

        return [
            'product_id' => $productId ?? 'null',
            'price' => $price,
            'available_product_types' => $availableProductTypes,
            'product_cart_details' => $productCartDetails,
        ];
    }

    private static function getAvailableConfigOptions(): array
    {
        if (self::$availConfigOptions === null) {
            self::$availConfigOptions = array_merge(array_merge(...array_values(self::CONFIG_OPTIONS)));
        }

        return self::$availConfigOptions;
    }
}
