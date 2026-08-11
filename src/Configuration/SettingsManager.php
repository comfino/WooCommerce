<?php

namespace Comfino\Configuration;

use Comfino\Api\ApiClient;
use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Common\Backend\Payment\ProductTypeFilter\FilterByExcludedCategory;
use Comfino\Common\Backend\Payment\ProductTypeFilter\FilterByExcludedProductId;
use Comfino\Common\Backend\Payment\ProductTypeFilter\FilterByProductTypeCartValueLimits;
use Comfino\Common\Backend\Payment\ProductTypeFilterInterface;
use Comfino\Common\Backend\Payment\ProductTypeFilterManager;
use Comfino\Common\Shop\Cart;
use Comfino\Common\Shop\Product\CategoryFilter;
use Comfino\DebugLogger;
use Comfino\ErrorLogger;
use Comfino\Extended\Api\Dto\Plugin\OperationContext;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Main;
use Comfino\PluginShared\CacheManager;
use ComfinoExternal\League\Flysystem\FilesystemException;

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
     * Reorders a product type code => name map by ConfigManager::getCheckoutProductTypesOrder(): entries whose code
     * appears in that priority list come first (in its order), followed by any remaining entries in their original
     * order.
     *
     * @param array $productTypes Product type code => name map, as returned by getProductTypesSelectList()
     *
     * @return array
     */
    public static function sortProductTypesByPriority(array $productTypes): array
    {
        $sortedProductTypes = [];

        foreach (ConfigManager::getCheckoutProductTypesOrder() as $productTypeCode) {
            if (array_key_exists($productTypeCode, $productTypes)) {
                $sortedProductTypes[$productTypeCode] = $productTypes[$productTypeCode];
            }
        }

        return $sortedProductTypes + $productTypes;
    }

    /**
     * Preselects up to two checkout payment label product types from the shop's available financial products,
     * following the priority order from ConfigManager::getCheckoutProductTypesOrder().
     *
     * @param array $availableProductTypes Product type code => name map, as returned by getProductTypesSelectList()
     *
     * @return string[]
     */
    public static function getDefaultCheckoutProductTypes(array $availableProductTypes): array
    {
        return array_slice(array_keys(self::sortProductTypesByPriority($availableProductTypes)), 0, 2);
    }

    /**
     * Sorts the product types passed to the paywall SDK config (`productTypes`/`productTypeNames`) using the same
     * priority order as the "Payment label product types" admin setting (ConfigManager::getCheckoutProductTypesOrder()),
     * so the SDK receives product types in the order configured in payment settings.
     *
     * @param LoanTypeEnum[]|null $allowedProductTypes
     * @param array $productTypeNames Product type code => public name map, as returned by getProductTypes() with $usePublicNames = true
     *
     * @return array{0: string[]|null, 1: array}
     */
    public static function sortPaywallProductTypes(?array $allowedProductTypes, array $productTypeNames): array
    {
        if (isset($productTypeNames['error'])) {
            return [$allowedProductTypes !== null ? array_map('strval', $allowedProductTypes) : null, $productTypeNames];
        }

        $sortedProductTypeNames = self::sortProductTypesByPriority($productTypeNames);

        if ($allowedProductTypes === null) {
            return [null, $sortedProductTypeNames];
        }

        $allowedCodes = array_map('strval', $allowedProductTypes);
        $sortedAllowedCodes = array_values(array_intersect(array_keys($sortedProductTypeNames), $allowedCodes));

        return [$sortedAllowedCodes, $sortedProductTypeNames];
    }

    /**
     * @return string[]
     */
    public static function getProductTypes(string $listType, bool $returnErrors = false, bool $usePublicNames = false): array
    {
        $language = Main::getShopLanguage();
        $cacheKey = "product_types.$listType" . ($usePublicNames ? '.public' : '') . ".$language";
        $listTypeEnum = new ProductTypesListTypeEnum($listType);

        if (($productTypes = CacheManager::get($cacheKey)) !== null) {
            return is_array($productTypes) ? $productTypes : [];
        }

        if (empty(ApiClient::getInstance()->getApiKey())) {
            return $returnErrors ? ['error' => 'API key is required.'] : [];
        }

        try {
            $productTypes = ApiClient::getInstance()->getProductTypes($listTypeEnum);
            $productTypesList = $usePublicNames
                ? $productTypes->productTypesWithPublicNames
                : $productTypes->productTypesWithNames;
            $cacheTtl = (int) $productTypes->getHeader('Cache-TTL', '0');

            CacheManager::set($cacheKey, $productTypesList, $cacheTtl, ['admin_product_types']);

            return $productTypesList;
        } catch (FilesystemException $e) {
            ErrorLogger::getLoggerInstance()->logError('Product types cache error', $e->getMessage());

            return $productTypesList ?? [];
        } catch (\Throwable $e) {
            ApiClient::processApiError('Settings error on page "' . Main::getCurrentUrl() . '" (Comfino API)', $e, OperationContext::Configuration);

            if ($returnErrors) {
                return ['error' => $e->getMessage()];
            }
        }

        return [];
    }

    /**
     * Returns a map of available creditors grouped by product type, cached with a single entry.
     *
     * @return array<string, string[]>
     */
    public static function getCreditors(): array
    {
        $cacheKey = 'creditors';

        if (($creditors = CacheManager::get($cacheKey)) !== null) {
            return is_array($creditors) ? $creditors : [];
        }

        if (empty(ApiClient::getInstance()->getApiKey())) {
            return [];
        }

        try {
            $response = ApiClient::getInstance()->getCreditors();
            $creditorsList = $response->creditors;
            $cacheTtl = (int) $response->getHeader('Cache-TTL', '0');

            CacheManager::set($cacheKey, $creditorsList, $cacheTtl, ['admin_product_types']);

            return $creditorsList;
        } catch (FilesystemException $e) {
            ErrorLogger::getLoggerInstance()->logError('Creditors cache error', $e->getMessage());

            return $creditorsList ?? [];
        } catch (\Throwable $e) {
            ApiClient::processApiError('Settings error on page "' . Main::getCurrentUrl() . '" (Comfino API)', $e, OperationContext::Configuration);
        }

        return [];
    }

    /**
     * @return string[]
     */
    public static function getProductTypesStrings(string $listType): array
    {
        $productTypes = self::getProductTypes($listType);

        if (isset($productTypes['error'])) {
            return [];
        }

        return array_keys($productTypes);
    }

    /**
     * @return LoanTypeEnum[]
     */
    public static function getProductTypesEnums(string $listType): array
    {
        $productTypes = self::getProductTypes($listType);

        if (isset($productTypes['error'])) {
            return [];
        }

        return array_map(
            static function (string $productType): LoanTypeEnum { return new LoanTypeEnum($productType, false); },
            array_keys($productTypes)
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
            return $returnErrors ? ['error' => 'API key is required.'] : [];
        }

        try {
            $widgetTypes = ApiClient::getInstance()->getWidgetTypes();
            $widgetTypesList = $widgetTypes->widgetTypesWithNames;
            $cacheTtl = (int) $widgetTypes->getHeader('Cache-TTL', '0');

            CacheManager::set($cacheKey, $widgetTypesList, $cacheTtl, ['admin_widget_types']);

            return $widgetTypesList;
        } catch (FilesystemException $e) {
            ErrorLogger::getLoggerInstance()->logError('Widget types cache error', $e->getMessage());

            return $widgetTypesList ?? [];
        } catch (\Throwable $e) {
            ApiClient::processApiError('Settings error on page "' . Main::getCurrentUrl() . '" (Comfino API)', $e, OperationContext::Configuration);

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
        if (!is_array($catFilters = ConfigManager::getConfigurationValue('COMFINO_PRODUCT_CATEGORY_FILTERS', []))) {
            $catFilters = array_map('trim', explode(',', $catFilters));
        }

        return $catFilters;
    }

    /**
     * @return int[]
     */
    public static function getProductIdFilter(): array
    {
        $productIdFilter = ConfigManager::getConfigurationValue('COMFINO_PRODUCT_ID_FILTER', []);

        if (!is_array($productIdFilter)) {
            $productIdFilter = explode(',', (string) $productIdFilter);
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $productIdFilter),
            static function (int $id): bool { return $id > 0; }
        )));
    }

    public static function getProductCategoryFiltersAvailProductTypes(): array
    {
        if (!is_array($availProds = ConfigManager::getConfigurationValue('COMFINO_CAT_FILTER_AVAIL_PROD_TYPES', []))) {
            $availProds = array_map('trim', explode(',', $availProds));
        }

        return $availProds;
    }

    public static function getAllowedProductsConfigForbiddenProductTypes(): array
    {
        if (!is_array($forbiddenProds = ConfigManager::getConfigurationValue('COMFINO_ALLOWED_PRODUCTS_CONFIG_FORBIDDEN_PROD_TYPES', []))) {
            $forbiddenProds = array_map('trim', explode(',', $forbiddenProds));
        }

        return $forbiddenProds;
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

        if (isset($productTypes['error'])) {
            return [];
        }

        $categoryFilterAvailProductTypes = [];

        foreach (self::getProductCategoryFiltersAvailProductTypes() as $productType) {
            $categoryFilterAvailProductTypes[$productType] = null;
        }

        if (empty($availProductTypes = array_intersect_key($productTypes, $categoryFilterAvailProductTypes))) {
            $availProductTypes = $productTypes;
        }

        return $availProductTypes;
    }

    public static function getAllowedProductsConfigAvailProdTypes(): array
    {
        $productTypes = self::getProductTypes(ProductTypesListTypeEnum::LIST_TYPE_PAYWALL);

        if (isset($productTypes['error'])) {
            return [];
        }

        $allowedProductConfigForbiddenProductTypes = [];

        foreach (self::getAllowedProductsConfigForbiddenProductTypes() as $productType) {
            $allowedProductConfigForbiddenProductTypes[$productType] = null;
        }

        if (empty($availProductTypes = array_diff_key($productTypes, $allowedProductConfigForbiddenProductTypes))) {
            $availProductTypes = $productTypes;
        }

        return $availProductTypes;
    }

    /**
     * @return array[]
     */
    public static function getCartValueLimitsConfig(): array
    {
        $rawConfig = ConfigManager::getConfigurationValue('COMFINO_CART_VALUE_LIMITS_CONFIG');

        return is_array($rawConfig) ? $rawConfig : [];
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
        $minLimitsByProductType = [];
        $maxLimitsByProductType = [];

        if ($minAmount > 0) {
            $availableProductTypes = self::getProductTypesStrings($listType);
            $minLimitsByProductType = array_fill_keys($availableProductTypes, $minAmount);
        }

        foreach (self::getCartValueLimitsConfig() as $entry) {
            if (empty($entry['type'])) {
                continue;
            }

            $productType = (string) $entry['type'];

            if (isset($entry['minAmount'])) {
                $minLimitsByProductType[$productType] = (int) round(((float) $entry['minAmount']) * 100);
            }

            if (isset($entry['maxAmount'])) {
                $maxLimitsByProductType[$productType] = (int) round(((float) $entry['maxAmount']) * 100);
            }
        }

        if (!empty($minLimitsByProductType) || !empty($maxLimitsByProductType)) {
            $filters[] = new FilterByProductTypeCartValueLimits(null, $minLimitsByProductType, $maxLimitsByProductType);
        }

        if (self::productCategoryFiltersActive($productCategoryFilters = self::getProductCategoryFilters())) {
            $filters[] = new FilterByExcludedCategory(
                new CategoryFilter(ConfigManager::getCategoriesTree()),
                $productCategoryFilters
            );
        }

        if (!empty($excludedProductIds = self::getProductIdFilter())) {
            $filters[] = new FilterByExcludedProductId($excludedProductIds);
        }

        return $filters;
    }

    /**
     * Returns the normalized `COMFINO_ALLOWED_PRODUCTS_CONFIG` payload ready for both the paywall iframe bootstrap
     * (frontend) and the backend `AllowedProductConfig` DTO builder. Drops entries whose `type` is missing or not
     * a known `LoanTypeEnum`, ensures `terms` are positive ints, returns `null` when the result is empty, so the
     * SDK's `?.length` short-circuit matches the "no restrictions" semantics.
     *
     * @return array[]|null
     */
    public static function getAllowedProductsConfigForFrontend(): ?array
    {
        $raw = ConfigManager::getConfigurationValue('COMFINO_ALLOWED_PRODUCTS_CONFIG');

        if (!is_array($raw) || empty($raw)) {
            return null;
        }

        $validTypes = LoanTypeEnum::values();
        $result = [];

        foreach ($raw as $entry) {
            if (!is_array($entry) || empty($entry['type']) || !in_array($entry['type'], $validTypes, true)) {
                continue;
            }

            $normalized = ['type' => (string) $entry['type']];

            if (isset($entry['minTerm']) && is_numeric($entry['minTerm'])) {
                $normalized['minTerm'] = (int) $entry['minTerm'];
            }

            if (isset($entry['maxTerm']) && is_numeric($entry['maxTerm'])) {
                $normalized['maxTerm'] = (int) $entry['maxTerm'];
            }

            if (isset($entry['terms']) && is_array($entry['terms'])) {
                $terms = array_values(array_filter(
                    array_map('intval', $entry['terms']),
                    static function (int $t): bool { return $t > 0; }
                ));

                if (!empty($terms)) {
                    $normalized['terms'] = $terms;
                }
            }

            $result[] = $normalized;
        }

        return !empty($result) ? $result : null;
    }
}
