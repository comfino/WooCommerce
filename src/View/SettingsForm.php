<?php

namespace Comfino\View;

use Comfino\Api\ApiClient;
use Comfino\Api\ApiService;
use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Configuration\ConfigManager;
use Comfino\Configuration\SettingsManager;
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

        return ['success' => $success, 'errorMessages' => $errorMessages];
    }

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
                    array_flip(['enabled', 'production_key', 'title', 'min_cart_amount', 'show_logo'])
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
                    array_flip(['sandbox_mode', 'sandbox_key', 'debug_mode', 'service_mode'])
                );
                break;
        }

        return $formFields;
    }

    /**
     * @param int[] $selectedCategories
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
     * @param int[] $selectedCategories
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
     * @param \WP_Term[] $treeNodes
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

    private static function getFormFieldsDefinitions(): array
    {
        return [
            'enabled' => [
                'title' => __('Enable/Disable', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Comfino payment module', 'comfino-payment-gateway'),
                'default' => ConfigManager::getDefaultValue('enabled') === true ? 'yes' : 'no',
                'description' => __('Shows Comfino payment option at the payment list.', 'comfino-payment-gateway'),
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
            'production_key' => [
                'title' => __('Production environment API key', 'comfino-payment-gateway'),
                'type' => 'text',
                'placeholder' => __('Please enter the key provided during registration', 'comfino-payment-gateway'),
            ],
            'show_logo' => [
                'title' => __('Show logo', 'comfino-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Show logo on payment method', 'comfino-payment-gateway'),
                'default' => ConfigManager::getDefaultValue('show_logo') === true ? 'yes' : 'no',
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
    }
}
