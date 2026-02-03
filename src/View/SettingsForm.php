<?php

namespace Comfino\View;

use Comfino\Api\ApiClient;
use Comfino\Api\ApiService;
use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Configuration\ConfigManager;
use Comfino\Configuration\SettingsManager;
use Comfino\DebugLogger;
use Comfino\ErrorLogger;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Main;
use Comfino\PluginShared\CacheManager;

if (!defined('ABSPATH')) {
    exit;
}

final class SettingsForm
{
    public const ERROR_LOG_NUM_LINES = 100;
    public const DEBUG_LOG_NUM_LINES = 200;
    public const COMFINO_SUPPORT_EMAIL = 'pomoc@comfino.pl';
    public const COMFINO_SUPPORT_PHONE = '887-106-027';

    /**
     * Processes form submission from plugin configuration page.
     *
     * Handles various form submissions including:
     * - Plugin diagnostics actions (module reset, log clearing).
     * - Configuration updates for all settings tabs.
     * - API key validation and widget key retrieval.
     *
     * @param string $activeTab Active tab identifier
     * @param array $configurationOptionsToSave Configuration options to save
     * @param array $postData Posted form data
     *
     * @return array Processing result with success status and error messages
     */
    public static function processForm(string $activeTab, array $configurationOptionsToSave, array $postData): array
    {
        $errorMessages = [];
        $widgetKeyError = false;
        $widgetKey = ConfigManager::getConfigurationValue('COMFINO_WIDGET_KEY', '');

        /* translators: s%: Validated form field name */
        $errorEmptyMsg = __("Field '%s' can not be empty.", 'comfino-payment-gateway');
        /* translators: s%: Validated form field name */
        $errorNumericFormatMsg = __("Field '%s' has wrong numeric format.", 'comfino-payment-gateway');

        switch ($activeTab) {
            case 'payment_settings':
            case 'developer_settings':
                if ($activeTab === 'payment_settings') {
                    $sandboxMode = ConfigManager::isSandboxMode();
                    $apiKey = $sandboxMode
                        ? ConfigManager::getConfigurationValue('COMFINO_SANDBOX_API_KEY')
                        : $configurationOptionsToSave['COMFINO_API_KEY'];

                    if (empty($configurationOptionsToSave['COMFINO_API_KEY'])) {
                        $errorMessages[] = sprintf($errorEmptyMsg, __('Production environment API key', 'comfino-payment-gateway'));
                    }
                    if (empty($configurationOptionsToSave['COMFINO_PAYMENT_TEXT'])) {
                        $errorMessages[] = sprintf($errorEmptyMsg, __('Payment text', 'comfino-payment-gateway'));
                    }
                    if (empty($configurationOptionsToSave['COMFINO_MINIMAL_CART_AMOUNT'])) {
                        $errorMessages[] = sprintf($errorEmptyMsg, __('Minimal amount in cart', 'comfino-payment-gateway'));
                    } elseif (!is_numeric($configurationOptionsToSave['COMFINO_MINIMAL_CART_AMOUNT'])) {
                        $errorMessages[] = sprintf($errorNumericFormatMsg, __('Minimal amount in cart', 'comfino-payment-gateway'));
                    }
                } else {
                    $sandboxMode = (bool) $configurationOptionsToSave['COMFINO_IS_SANDBOX'];
                    $apiKey = $sandboxMode
                        ? $configurationOptionsToSave['COMFINO_SANDBOX_API_KEY']
                        : ConfigManager::getConfigurationValue('COMFINO_API_KEY');

                    if (isset($configurationOptionsToSave['COMFINO_DEV_ENV_VARS'])) {
                        ConfigManager::updateConfigurationValue(
                            'COMFINO_DEV_ENV_VARS',
                            $configurationOptionsToSave['COMFINO_DEV_ENV_VARS']
                        );
                    }
                }

                $apiClient = ApiClient::getInstance($sandboxMode, $apiKey);

                if (!empty($apiKey) && !count($errorMessages)) {
                    $cacheInvalidateUrl = ApiService::getEndpointUrl('cacheInvalidate');
                    $configurationUrl = ApiService::getEndpointUrl('configuration');

                    try {
                        // Check if passed API key is valid.
                        $apiClient->isShopAccountActive($cacheInvalidateUrl, $configurationUrl);

                        try {
                            // If API key is valid fetch widget key from API endpoint.
                            $widgetKey = $apiClient->getWidgetKey();
                        } catch (\Throwable $e) {
                            ApiClient::processApiError(
                                ($activeTab === 'payment_settings' ? 'Payment' : 'Developer') .
                                ' settings error on page "' . Main::getCurrentUrl() . '" (Comfino API)',
                                $e
                            );

                            $errorMessages[] = $e->getMessage();
                            $widgetKeyError = true;

                            if (!empty(getenv('COMFINO_DEV'))) {
                                $errorMessages[] = sprintf('Comfino API host: %s', $apiClient->getApiHost());
                            }
                        }
                    } catch (AuthorizationError|AccessDenied $e) {
                        /* translators: s%: Comfino API key */
                        $errorMessages[] = sprintf(__('API key %s is not valid.', 'comfino-payment-gateway'), $apiKey);

                        if (!empty(getenv('COMFINO_DEV'))) {
                            $errorMessages[] = sprintf('Comfino API host: %s', $apiClient->getApiHost());
                        }
                    } catch (\Throwable $e) {
                        ApiClient::processApiError(
                            ($activeTab === 'payment_settings' ? 'Payment' : 'Developer') .
                            ' settings error on page "' . Main::getCurrentUrl() . '" (Comfino API)',
                            $e
                        );

                        $errorMessages[] = $e->getMessage();

                        if (!empty(getenv('COMFINO_DEV'))) {
                            $errorMessages[] = sprintf('Comfino API host: %s', $apiClient->getApiHost());
                        }
                    }
                }

                $configurationOptionsToSave['COMFINO_WIDGET_KEY'] = $widgetKey;
                break;

            case 'sale_settings':
                $categoriesTree = ConfigManager::getCategoriesTree();
                $productCategories = array_keys(ConfigManager::getAllProductCategories());
                $productCategoryFilters = [];

                foreach ($postData['product_categories'] as $productType => $categoryIds) {
                    $nodeIds = [];

                    foreach (explode(',', $categoryIds) as $categoryId) {
                        if (($categoryNode = $categoriesTree->getNodeById((int) $categoryId)) !== null
                            && count($pathNodes = $categoryNode->getPathToRoot()) > 0
                        ) {
                            $nodeIds[] = $categoriesTree->getPathNodeIds($pathNodes);
                        }
                    }

                    if (count($nodeIds) > 0) {
                        $productCategoryFilters[$productType] = array_values(array_diff(
                            $productCategories,
                            ...$nodeIds
                        ));
                    } else {
                        $productCategoryFilters[$productType] = $productCategories;
                    }
                }

                $configurationOptionsToSave['COMFINO_PRODUCT_CATEGORY_FILTERS'] = $productCategoryFilters;
                break;

            case 'widget_settings':
                if (empty($configurationOptionsToSave['COMFINO_WIDGET_PRICE_OBSERVER_LEVEL'])) {
                    $configurationOptionsToSave['COMFINO_WIDGET_PRICE_OBSERVER_LEVEL'] = '0';
                }

                if (!is_numeric($configurationOptionsToSave['COMFINO_WIDGET_PRICE_OBSERVER_LEVEL'])) {
                    $errorMessages[] = sprintf(
                        $errorNumericFormatMsg,
                        __('Price change detection - container hierarchy level', 'comfino-payment-gateway')
                    );
                }

                $customCssUrlOptionNames = [
                    'COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL',
                    'COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL',
                ];

                foreach ($customCssUrlOptionNames as $customCssUrlOptionName) {
                    if (!empty($customCssUrl = $configurationOptionsToSave[$customCssUrlOptionName] ?? '')) {
                        if (!wp_http_validate_url($customCssUrl)) {
                            /* translators: s%: Custom CSS URL */
                            $errorMessages[] = sprintf(__('Custom CSS URL "%s" is not valid.', 'comfino-payment-gateway'), $customCssUrl);
                        } elseif (wp_parse_url($customCssUrl, PHP_URL_SCHEME) === null) {
                            /* translators: s%: Custom CSS URL */
                            $errorMessages[] = sprintf(__('Custom CSS URL "%s" is not absolute.', 'comfino-payment-gateway'), $customCssUrl);
                        } elseif (stripos($customCssUrl, Main::getShopDomain()) === false) {
                            $errorMessages[] = sprintf(
                                /* translators: 1: Custom CSS URL 2: Shop domain */
                                __('Custom CSS URL "%1$s" is not in shop domain "%2$s".', 'comfino-payment-gateway'),
                                $customCssUrl,
                                Main::getShopDomain()
                            );
                        }
                    }
                }

                if (!count($errorMessages) && !empty($apiKey = ConfigManager::getApiKey())) {
                    // Update widget key.
                    try {
                        $cacheInvalidateUrl = ApiService::getEndpointUrl('cacheInvalidate');
                        $configurationUrl = ApiService::getEndpointUrl('configuration');

                        // Check if passed API key is valid.
                        ApiClient::getInstance()->isShopAccountActive($cacheInvalidateUrl, $configurationUrl);

                        try {
                            $widgetKey = ApiClient::getInstance()->getWidgetKey();
                        } catch (\Throwable $e) {
                            ApiClient::processApiError(
                                'Widget settings error on page "' . Main::getCurrentUrl() . '" (Comfino API)',
                                $e
                            );

                            $errorMessages[] = $e->getMessage();
                            $widgetKeyError = true;
                        }
                    } catch (AuthorizationError|AccessDenied $e) {
                        /* translators: s%: Comfino API key */
                        $errorMessages[] = sprintf(__('API key %s is not valid.', 'comfino-payment-gateway'), $apiKey);
                    } catch (\Throwable $e) {
                        ApiClient::processApiError(
                            'Widget settings error on page "' . Main::getCurrentUrl() . '" (Comfino API)',
                            $e
                        );

                        $errorMessages[] = $e->getMessage();
                    }
                }

                $configurationOptionsToSave['COMFINO_WIDGET_KEY'] = $widgetKey;
                break;

            case 'plugin_diagnostics':
                // Handle diagnostics tab button submissions. These are handled separately and don't update configuration.
                if (isset($postData['submit_module_reset'])) {
                    // Module reset is handled separately via session data. The actual reset is triggered and results are stored for display.
                    return ['success' => true, 'errorMessages' => []];
                }

                if (isset($postData['submit_clear_error_log'])) {
                    ErrorLogger::clearLogs();

                    return ['success' => true, 'errorMessages' => [], 'log_cleared' => 'error'];
                }

                if (isset($postData['submit_clear_debug_log'])) {
                    DebugLogger::clearLogs();

                    return ['success' => true, 'errorMessages' => [], 'log_cleared' => 'debug'];
                }

                // No action buttons pressed - just viewing the diagnostics page.
                return ['success' => true, 'errorMessages' => []];
        }

        if (!$widgetKeyError && count($errorMessages)) {
            $success = false;
        } else {
            // Update plugin configuration.
            ConfigManager::updateConfiguration($configurationOptionsToSave, false);

            $success = true;
        }

        // Clear configuration and front cache.
        CacheManager::getCachePool()->clear();

        // Enable debug mode admin notice.
        update_user_meta(get_current_user_id(), 'comfino_debug_notice_dismissed', false);

        return ['success' => $success, 'errorMessages' => $errorMessages];
    }

    /**
     * Generates form fields configuration for plugin settings.
     *
     * Builds form field definitions for different configuration tabs:
     * - payment_settings: API key, payment text, minimal cart amount, logo display
     * - sale_settings: Product category filters for financial products
     * - widget_settings: Widget configuration and appearance options
     * - abandoned_cart_settings: Abandoned cart reminder settings
     * - developer_settings: Sandbox mode, debug mode, service mode
     *
     * @param string|null $activeTab Active tab identifier (null returns all fields)
     *
     * @return array Form fields configuration for WooCommerce form rendering
     */
    public static function getFormFields(?string $activeTab = null): array
    {
        if (empty($activeTab)) {
            return self::getFormFieldsDefinitions();
        }

        $formFields = [];

        switch ($activeTab) {
            case 'payment_settings':
                $formFields = array_intersect_key(
                    self::getFormFieldsDefinitions(),
                    array_flip(['enabled', 'production_key', 'title', 'min_cart_amount', 'show_logo', 'use_order_reference'])
                );
                break;

            case 'sale_settings':
                $formFields = array_intersect_key(
                    self::getFormFieldsDefinitions(),
                    array_flip(['cat_filter_avail_prod_types', 'sale_settings_fin_prods_avail_rules'])
                );

                $productCategories = ConfigManager::getAllProductCategories();
                $productCategoryFilters = SettingsManager::getProductCategoryFilters();

                foreach (SettingsManager::getCatFilterAvailProdTypes() as $prodTypeCode => $prodTypeName) {
                    if (isset($productCategoryFilters[$prodTypeCode])) {
                        $selectedCategories = array_diff(
                            array_keys($productCategories),
                            $productCategoryFilters[$prodTypeCode]
                        );
                    } else {
                        $selectedCategories = array_keys($productCategories);
                    }

                    $formFields['sale_settings_product_category_filter_' . $prodTypeCode] = [
                        'title' => $prodTypeName,
                        'type' => 'product_category_tree',
                        'product_type' => $prodTypeCode,
                        'id' => 'product_categories',
                        'selected_categories' => $selectedCategories,
                    ];
                }

                break;

            case 'widget_settings':
                $formFields = array_intersect_key(
                    self::getFormFieldsDefinitions(),
                    array_flip([
                        'widget_settings_basic',
                        'widget_enabled', 'widget_key', 'widget_type', 'widget_offer_types', 'widget_show_provider_logos',
                        'widget_settings_advanced',
                        'widget_price_selector', 'widget_target_selector', 'widget_price_observer_selector',
                        'widget_price_observer_level', 'widget_embed_method', 'widget_custom_banner_css_url',
                        'widget_custom_calculator_css_url', 'widget_js_code', 'widget_prod_script_version',
                        'widget_dev_script_version',
                    ])
                );
                break;

            case 'abandoned_cart_settings':
                $formFields = array_intersect_key(
                    self::getFormFieldsDefinitions(),
                    array_flip(['abandoned_cart_enabled', 'abandoned_payments'])
                );
                break;

            case 'developer_settings':
                $formFields = array_intersect_key(
                    self::getFormFieldsDefinitions(),
                    array_flip(['sandbox_mode', 'sandbox_key', 'debug_mode', 'service_mode', 'dev_env_vars'])
                );
                break;
        }

        return $formFields;
    }

    /**
     * Renders product category tree for filtering.
     *
     * @param string $treeId Tree element ID
     * @param string $productType Product type code
     * @param int[] $selectedCategories Selected category IDs
     *
     * @return string Rendered HTML for category tree
     */
    public static function renderCategoryTree(string $treeId, string $productType, array $selectedCategories): string
    {
        return TemplateManager::renderView(
            'product-category-filter',
            'admin/_configure',
            [
                'tree_id' => $treeId,
                'tree_nodes' => self::buildCategoriesTree($selectedCategories),
                'close_depth' => 3,
                'product_type' => $productType,
            ],
            false
        );
    }

    /**
     * Builds hierarchical category tree structure.
     *
     * @param int[] $selectedCategories Selected category IDs
     *
     * @return array Tree structure for JavaScript tree component
     */
    private static function buildCategoriesTree(array $selectedCategories): array
    {
        return self::processTreeNodes(
            get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name']),
            $selectedCategories,
            0
        );
    }

    /**
     * Recursively processes category nodes into tree structure.
     *
     * @param \WP_Term[] $treeNodes WooCommerce category terms
     * @param int[] $selectedNodes Selected category IDs
     * @param int $parentId Parent category ID for current level
     *
     * @return array Processed tree nodes with children
     */
    private static function processTreeNodes(array $treeNodes, array $selectedNodes, int $parentId): array
    {
        $categoryTree = [];

        foreach ($treeNodes as $node) {
            if ($node->parent === $parentId) {
                $categoryTreeNode = ['id' => $node->term_id, 'text' => $node->name];
                $childNodes = self::processTreeNodes($treeNodes, $selectedNodes, $node->term_id);

                if (count($childNodes) > 0) {
                    $categoryTreeNode['children'] = $childNodes;
                } elseif (in_array($node->term_id, $selectedNodes, true)) {
                    $categoryTreeNode['checked'] = true;
                }

                $categoryTree[] = $categoryTreeNode;
            }
        }

        return $categoryTree;
    }

    /**
     * Returns complete form field definitions for all configuration tabs.
     *
     * Defines all available form fields with their properties including:
     * - Field type (checkbox, text, textarea, select, etc.)
     * - Labels and descriptions
     * - Default values
     * - Validation rules
     *
     * @return array Complete form field definitions
     */
    private static function getFormFieldsDefinitions(): array
    {
        $fieldDefinitions = [
            'enabled' => [
                'title' => __('Enable/Disable', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Comfino payment module', 'comfino-payment-gateway'),
                'default' => ConfigManager::getDefaultValue('enabled') === true ? 'yes' : 'no',
                'description' => __('Shows Comfino payment option at the payment list.', 'comfino-payment-gateway'),
            ],
            'production_key' => [
                'title' => __('Production environment API key', 'comfino-payment-gateway'),
                'type' => 'text',
                'placeholder' => __('Please enter the key provided during registration', 'comfino-payment-gateway'),
            ],
            'title' => [
                'title' => __('Title', 'comfino-payment-gateway'),
                'type' => 'text',
                'default' => ConfigManager::getDefaultValue('title'),
            ],
            'min_cart_amount' => [
                'title' => __('Minimal amount in cart', 'comfino-payment-gateway'),
                'type' => 'text',
                'default' => (string) ConfigManager::getDefaultValue('min_cart_amount'),
            ],
            'show_logo' => [
                'title' => __('Show logo', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Show logo on payment method', 'comfino-payment-gateway'),
                'default' => ConfigManager::getDefaultValue('show_logo') === true ? 'yes' : 'no',
            ],
            'use_order_reference' => [
                'title' => __('Order number', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Use order reference as external ID', 'comfino-payment-gateway'),
                'default' => ConfigManager::getDefaultValue('use_order_reference') === true ? 'yes' : 'no',
                'description' => __(
                    'Use customer-visible order reference instead of numeric order ID for Comfino API integration. New orders only.',
                    'comfino-payment-gateway'
                ),
            ],
            'sandbox_mode' => [
                'title' => __('Test environment', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Use test environment', 'comfino-payment-gateway'),
                'default' => ConfigManager::getDefaultValue('sandbox_mode') === true ? 'yes' : 'no',
                'description' => __(
                    'The test environment allows the store owner to get acquainted with the functionality of the Comfino module. This is a Comfino simulator, thanks to which you can get to know all the advantages of this payment method. The use of the test mode is free (there are also no charges for orders).',
                    'comfino-payment-gateway'
                ),
            ],
            'sandbox_key' => [
                'title' => __('Test environment API key', 'comfino-payment-gateway'),
                'type' => 'text',
                'description' => __('Ask the supervisor for access to the test environment (key, login, password, link). Remember, the test key is different from the production key.', 'comfino-payment-gateway'),
            ],
            'debug_mode' => [
                'title' => __('Debug mode', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable debug mode', 'comfino-payment-gateway'),
                'default' => ConfigManager::getDefaultValue('debug_mode') === true ? 'yes' : 'no',
                'description' => __(
                    'Debug mode is useful in case of problems with Comfino payment availability. In this mode module logs details of internal process responsible for displaying of Comfino payment option at the payment methods list.',
                    'comfino-payment-gateway'
                ),
            ],
            'service_mode' => [
                'title' => __('Service mode', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable service mode', 'comfino-payment-gateway'),
                'default' => ConfigManager::getDefaultValue('service_mode') === true ? 'yes' : 'no',
                'description' => __(
                    'Service mode is useful in testing Comfino payment gateway without sharing it with customers. In this mode Comfino payment method is visible only for selected sessions and debug logs are collected only for these sessions.',
                    'comfino-payment-gateway'
                ),
            ],
            'cat_filter_avail_prod_types' => [
                'type' => 'hidden',
                'default' => ConfigManager::getDefaultValue('cat_filter_avail_prod_types'),
            ],
            'sale_settings_fin_prods_avail_rules' => [
                'title' => __('Rules for the availability of financial products', 'comfino-payment-gateway'),
                'type' => 'title',
            ],
            'widget_settings_basic' => [
                'title' => __('Basic settings', 'comfino-payment-gateway'),
                'type' => 'title',
            ],
            'widget_enabled' => [
                'title' => __('Widget enable', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Comfino widget', 'comfino-payment-gateway'),
                'default' => ConfigManager::getDefaultValue('debug_mode') === true ? 'yes' : 'no',
                'description' => __('Show Comfino widget in the product.', 'comfino-payment-gateway'),
            ],
            'widget_key' => [
                'title' => __('Widget key', 'comfino-payment-gateway'),
                'type' => 'hidden',
            ],
            'widget_type' => [
                'title' => __('Widget type', 'comfino-payment-gateway'),
                'type' => 'select',
                'options' => SettingsManager::getWidgetTypesSelectList(),
            ],
            'widget_offer_types' => [
                'title' => __('Offer types', 'comfino-payment-gateway'),
                'type' => 'checkboxset',
                'values' => SettingsManager::getProductTypesSelectList(ProductTypesListTypeEnum::LIST_TYPE_WIDGET),
                'default' => [key(SettingsManager::getProductTypesSelectList(ProductTypesListTypeEnum::LIST_TYPE_WIDGET))],
                'description' => __('Other payment methods (Installments 0%, Buy now, pay later, Installments for companies, Leasing) available after consulting a Comfino advisor (kontakt@comfino.pl).', 'comfino-payment-gateway'),
            ],
            'widget_show_provider_logos' => [
                'title' => __('Show logos', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Show logos of financial services providers', 'comfino-payment-gateway'),
                'default' => ConfigManager::getDefaultValue('widget_show_provider_logos') === true ? 'yes' : 'no',
            ],
            'widget_settings_advanced' => [
                'title' => __('Advanced settings', 'comfino-payment-gateway'),
                'type' => 'title',
            ],
            'widget_price_selector' => [
                'title' => __('Widget price element selector', 'comfino-payment-gateway'),
                'type' => 'text',
                'default' => ConfigManager::getDefaultValue('widget_price_selector'),
            ],
            'widget_target_selector' => [
                'title' => __('Widget anchor element selector', 'comfino-payment-gateway'),
                'type' => 'text',
                'default' => ConfigManager::getDefaultValue('widget_target_selector'),
            ],
            'widget_price_observer_selector' => [
                'title' => __('Price change detection - container selector', 'comfino-payment-gateway'),
                'type' => 'text',
                'default' => ConfigManager::getDefaultValue('widget_price_observer_selector'),
                'description' => __(
                    'Selector of observed parent element which contains price element.',
                    'comfino-payment-gateway'
                ),
            ],
            'widget_price_observer_level' => [
                'title' => __('Price change detection - container hierarchy level', 'comfino-payment-gateway'),
                'type' => 'number',
                'default' => (string) ConfigManager::getDefaultValue('widget_price_observer_level'),
                'description' => __(
                    'Hierarchy level of observed parent element relative to the price element.',
                    'comfino-payment-gateway'
                ),
            ],
            'widget_embed_method' => [
                'title' => __('Embedding method', 'comfino-payment-gateway'),
                'type' => 'select',
                'options' => [
                    'INSERT_INTO_FIRST' => 'INSERT_INTO_FIRST',
                    'INSERT_INTO_LAST' => 'INSERT_INTO_LAST',
                    'INSERT_BEFORE' => 'INSERT_BEFORE',
                    'INSERT_AFTER' => 'INSERT_AFTER',
                ],
            ],
            'widget_custom_banner_css_url' => [
                'title' => __('Custom banner CSS style', 'comfino-payment-gateway'),
                'type' => 'text',
                'default' => ConfigManager::getDefaultValue('widget_custom_banner_css_url'),
                'description' => __(
                    'URL for the custom banner style. Only links from your store domain are allowed.',
                    'comfino-payment-gateway'
                ),
            ],
            'widget_custom_calculator_css_url' => [
                'title' => __('Custom calculator CSS style', 'comfino-payment-gateway'),
                'type' => 'text',
                'default' => ConfigManager::getDefaultValue('widget_custom_calculator_css_url'),
                'description' => __(
                    'URL for the custom calculator style. Only links from your store domain are allowed.',
                    'comfino-payment-gateway'
                ),
            ],
            'widget_js_code' => [
                'title' => __('Widget initialization code', 'comfino-payment-gateway'),
                'type' => 'textarea',
                'css' => 'width: 800px; height: 400px',
                'default' => ConfigManager::getDefaultValue('widget_js_code'),
            ],
            'widget_prod_script_version' => [
                'type' => 'hidden',
                'default' => '',
            ],
            'widget_dev_script_version' => [
                'type' => 'hidden',
                'default' => '',
            ],
            'abandoned_cart_enabled' => [
                'title' => __('Enable/Disable', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('By enabling "Saving shopping cart", you agree and accept <a href="https://cdn.comfino.pl/regulamin/Regulamin-Ratowanie-Koszyka.pdf">Regulations</a>', 'comfino-payment-gateway'),
                'default' => ConfigManager::getDefaultValue('abandoned_cart_enabled') === true ? 'yes' : 'no',
                'description' => __('With the "Cart Rescue" feature, you will effectively minimize the problem of abandoned carts that all sellers face. When a customer adds products to the cart but abandons it, also due to an unsuccessful payment, they will automatically receive a reminder e-mail with a direct link leading to payment. This service allows you to effectively recover potential transactions and increase order conversions.', 'comfino-payment-gateway'),
            ],
            'abandoned_payments' => [
                'title' => __('View in payment list', 'comfino-payment-gateway'),
                'type' => 'select',
                'options' => [
                    'comfino' => __('Only Comfino', 'comfino-payment-gateway'),
                    'all' => __('All payments', 'comfino-payment-gateway'),
                ],
            ],
        ];

        if (getenv('COMFINO_DEV_ENV') === 'TRUE') {
            $fieldDefinitions['dev_env_vars'] = [
                'title' => __('Environment variables', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Use development environment variables', 'comfino-payment-gateway'),
                'default' => ConfigManager::getDefaultValue('dev_env_vars') === true ? 'yes' : 'no',
                'description' => __(
                    'Use of development environment variables with custom hosts which overwrite hosts stored in the plugin.',
                    'comfino-payment-gateway'
                ),
            ];
        }

        return $fieldDefinitions;
    }
}
