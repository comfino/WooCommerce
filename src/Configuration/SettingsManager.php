<?php

namespace Comfino\Configuration;

use Comfino\Api\ApiClient;
use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Common\Backend\Payment\ProductTypeFilter\FilterByCartValueLowerLimit;
use Comfino\Common\Backend\Payment\ProductTypeFilter\FilterByExcludedCategory;
use Comfino\Common\Backend\Payment\ProductTypeFilter\FilterByProductType;
use Comfino\Common\Backend\Payment\ProductTypeFilterInterface;
use Comfino\Common\Backend\Payment\ProductTypeFilterManager;
use Comfino\Common\Shop\Cart;
use Comfino\Common\Shop\Product\CategoryFilter;
use Comfino\DebugLogger;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Main;
use Comfino\PluginShared\CacheManager;

if (!defined('ABSPATH')) {
    exit;
}

final class SettingsManager
{
    /** @var ProductTypeFilterManager */
    private static $filterManager;

    public static function getProductTypesSelectList(string $listType): array
    {
        return self::getProductTypes($listType, true);
    }

    public static function getWidgetTypesSelectList(): array
    {
        return self::getWidgetTypes(true);
    }

    /**
     * @return string[]
     */
    public static function getProductTypes(string $listType, bool $returnErrors = false): array
    {
        $language = Main::getShopLanguage();
        $cacheKey = "product_types.$listType.$language";
        $listTypeEnum = new ProductTypesListTypeEnum($listType);

        if (($productTypes = CacheManager::get($cacheKey)) !== null) {
            return is_array($productTypes) ? $productTypes : [];
        }

        if (empty(ApiClient::getInstance()->getApiKey())) {
            return ['error' => 'API key is required.'];
        }

        try {
            $productTypes = ApiClient::getInstance()->getProductTypes($listTypeEnum);
            $productTypesList = $productTypes->productTypesWithNames;
            $cacheTtl = (int) $productTypes->getHeader('Cache-TTL', '0');

            CacheManager::set($cacheKey, $productTypesList, $cacheTtl, ['admin_product_types']);

            return $productTypesList;
        } catch (\Throwable $e) {
            ApiClient::processApiError('Settings error on page "' . Main::getCurrentUrl() . '" (Comfino API)', $e);

            if ($returnErrors) {
                return ['error' => $e->getMessage()];
            }
        }

        return [];
    }

    /**
     * @return string[]
     */
    public static function getProductTypesStrings(string $listType): array
    {
        return array_keys(self::getProductTypes($listType));
    }

    /**
     * @return LoanTypeEnum[]
     */
    public static function getProductTypesEnums(string $listType): array
    {
        return array_map(
            static function (string $productType): LoanTypeEnum { return new LoanTypeEnum($productType); },
            array_keys(self::getProductTypes($listType))
        );
    }

    /**
     * @return string[]
     */
    public static function getWidgetTypes(bool $returnErrors = false): array
    {
        $language = Main::getShopLanguage();
        $cacheKey = "widget_types.$language";

        if (($widgetTypes = CacheManager::get($cacheKey)) !== null) {
            return is_array($widgetTypes) ? $widgetTypes : [];
        }

        if (empty(ApiClient::getInstance()->getApiKey())) {
            return ['error' => 'API key is required.'];
        }

        try {
            $widgetTypes = ApiClient::getInstance()->getWidgetTypes();
            $widgetTypesList = $widgetTypes->widgetTypesWithNames;
            $cacheTtl = (int) $widgetTypes->getHeader('Cache-TTL', '0');

            CacheManager::set($cacheKey, $widgetTypesList, $cacheTtl, ['admin_widget_types']);

            return $widgetTypesList;
        } catch (\Throwable $e) {
            ApiClient::processApiError('Settings error on page "' . Main::getCurrentUrl() . '" (Comfino API)', $e);

            if ($returnErrors) {
                return ['error' => $e->getMessage()];
            }
        }

        return [];
    }

    public static function isProductTypeAllowed(string $listType, LoanTypeEnum $productType, Cart $cart): bool
    {
        if (($allowedProductTypes = self::getAllowedProductTypes($listType, $cart)) === null) {
            return true;
        }

        return in_array($productType, $allowedProductTypes, true);
    }

    /**
     * @return LoanTypeEnum[]|null
     */
    public static function getAllowedProductTypes(string $listType, Cart $cart, bool $returnOnlyArray = false): ?array
    {
        $filterManager = self::getFilterManager($listType);

        if (!$filterManager->filtersActive()) {
            return null;
        }

        $availableProductTypes = self::getProductTypesEnums($listType);
        $allowedProductTypes = $filterManager->getAllowedProductTypes($availableProductTypes, $cart);

        if (ConfigManager::isDebugMode()) {
            $activeFilters = array_map(
                static function (ProductTypeFilterInterface $filter): string {
                    return get_class($filter) . ': ' . wp_json_encode($filter->getAsArray());
                },
                $filterManager->getFilters()
            );

            DebugLogger::logEvent(
                '[PAYWALL]',
                'getAllowedProductTypes',
                [
                    '$activeFilters' => $activeFilters,
                    '$availableProductTypes' => $availableProductTypes,
                    '$allowedProductTypes' => $allowedProductTypes,
                ]
            );
        }

        if ($returnOnlyArray) {
            return $allowedProductTypes;
        }

        return count($availableProductTypes) !== count($allowedProductTypes) ? $allowedProductTypes : null;
    }

    public static function getProductCategoryFilters(): array
    {
        return ConfigManager::getConfigurationValue('COMFINO_PRODUCT_CATEGORY_FILTERS', []);
    }

    public static function productCategoryFiltersActive(array $productCategoryFilters): bool
    {
        if (empty($productCategoryFilters)) {
            return false;
        }

        foreach ($productCategoryFilters as $excludedCategoryIds) {
            if (!empty($excludedCategoryIds)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[] [['prodTypeCode' => 'prodTypeName'], ...]
     */
    public static function getCatFilterAvailProdTypes(): array
    {
        $productTypes = self::getProductTypes(ProductTypesListTypeEnum::LIST_TYPE_PAYWALL);
        $categoryFilterAvailProductTypes = [];

        foreach (ConfigManager::getConfigurationValue('COMFINO_CAT_FILTER_AVAIL_PROD_TYPES') as $prod_type) {
            $categoryFilterAvailProductTypes[$prod_type] = null;
        }

        if (empty($availProductTypes = array_intersect_key($productTypes, $categoryFilterAvailProductTypes))) {
            $availProductTypes = $productTypes;
        }

        return $availProductTypes;
    }

    private static function getFilterManager(string $listType): ProductTypeFilterManager
    {
        if (self::$filterManager === null) {
            self::$filterManager = ProductTypeFilterManager::getInstance();

            foreach (self::buildFiltersList($listType) as $filter) {
                self::$filterManager->addFilter($filter);
            }
        }

        return self::$filterManager;
    }

    /**
     * @return ProductTypeFilterInterface[]
     */
    private static function buildFiltersList(string $listType): array
    {
        $filters = [];
        $minAmount = (int) (round(ConfigManager::getConfigurationValue('COMFINO_MINIMAL_CART_AMOUNT', 0), 2) * 100);

        if ($minAmount > 0) {
            $availableProductTypes = self::getProductTypesStrings($listType);
            $filters[] = new FilterByCartValueLowerLimit(
                array_combine($availableProductTypes, array_fill(0, count($availableProductTypes), $minAmount))
            );
        }

        if ($listType === ProductTypesListTypeEnum::LIST_TYPE_WIDGET
            && ConfigManager::getConfigurationValue('COMFINO_WIDGET_TYPE') === 'with-modal'
            && !empty($widgetProductTypes = ConfigManager::getWidgetOfferTypes())
        ) {
            $filters[] = new FilterByProductType([new LoanTypeEnum(current($widgetProductTypes))]);
        }

        if (self::productCategoryFiltersActive($productCategoryFilters = self::getProductCategoryFilters())) {
            $filters[] = new FilterByExcludedCategory(
                new CategoryFilter(ConfigManager::getCategoriesTree()),
                $productCategoryFilters
            );
        }

        return $filters;
    }
}
