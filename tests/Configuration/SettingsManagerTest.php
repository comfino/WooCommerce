<?php

namespace Comfino\Tests\Configuration;

use Comfino\Configuration\SettingsManager;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Main;
use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\PluginShared\CacheManager;
use Comfino\Tests\Stub\StubApiClient;
use Comfino\Tests\TestState;
use Comfino\Common\Shop\Cart;

class SettingsManagerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Mock $_SERVER for Main class.
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REQUEST_SCHEME'] = 'https';
        $_SERVER['HTTP_HOST'] = 'comfino-wc-store.test';

        // Set plugin paths.
        Main::setPluginDirectory(__DIR__ . '/../..');
        Main::setPluginFile(__DIR__ . '/../../comfino-payment-gateway.php');
    }

    public function testGetProductTypesSelectList(): void
    {
        $productTypes = SettingsManager::getProductTypesSelectList(ProductTypesListTypeEnum::LIST_TYPE_PAYWALL);

        // Result may be empty array or error array in test environment.
        $this->assertArrayHasKey('error', $productTypes);
        $this->assertEquals('API key is required.', $productTypes['error']);
    }

    public function testGetWidgetTypesSelectList(): void
    {
        $widgetTypes = SettingsManager::getWidgetTypesSelectList();

        // Result may be empty array or error array in test environment.
        $this->assertArrayHasKey('error', $widgetTypes);
        $this->assertEquals('API key is required.', $widgetTypes['error']);
    }

    public function testGetProductTypes(): void
    {
        $listType = ProductTypesListTypeEnum::LIST_TYPE_PAYWALL;

        // Test without returning errors.
        $productTypes = SettingsManager::getProductTypes($listType);

        $this->assertCount(0, $productTypes);

        // Test with returning errors.
        $productTypesWithErrors = SettingsManager::getProductTypes($listType, true);

        // Should contain error due to missing API key in test environment.
        $this->assertArrayHasKey('error', $productTypesWithErrors);
        $this->assertEquals('API key is required.', $productTypesWithErrors['error']);
    }

    public function testGetProductTypesStrings(): void
    {
        // Should return empty array in test environment.
        $this->assertCount(0, SettingsManager::getProductTypesStrings(ProductTypesListTypeEnum::LIST_TYPE_PAYWALL));
    }

    public function testGetProductTypesEnums(): void
    {
        // Should return empty array in test environment.
        $this->assertCount(0, SettingsManager::getProductTypesEnums(ProductTypesListTypeEnum::LIST_TYPE_PAYWALL));
    }

    public function testGetWidgetTypes(): void
    {
        // Test without returning errors.
        $widgetTypes = SettingsManager::getWidgetTypes();

        // Should return empty array in test environment.
        $this->assertCount(0, $widgetTypes);

        // Test with returning errors.
        $widgetTypesWithErrors = SettingsManager::getWidgetTypes(true);

        // Should contain error due to missing API key in test environment.
        $this->assertArrayHasKey('error', $widgetTypesWithErrors);
        $this->assertEquals('API key is required.', $widgetTypesWithErrors['error']);
    }

    public function testGetProductCategoryFilters(): void
    {
        // Should return empty array in test environment.
        $this->assertCount(0, SettingsManager::getProductCategoryFilters());
    }

    public function testProductCategoryFiltersActive(): void
    {
        // Test with empty filters.
        $this->assertFalse(SettingsManager::productCategoryFiltersActive([]));

        // Test with filters that have no excluded categories.
        $emptyFilters = ['type1' => [], 'type2' => []];

        $this->assertFalse(SettingsManager::productCategoryFiltersActive($emptyFilters));

        // Test with filters that have excluded categories.
        $activeFilters = ['type1' => [1, 2], 'type2' => []];

        $this->assertTrue(SettingsManager::productCategoryFiltersActive($activeFilters));
    }

    public function testGetCatFilterAvailProdTypes(): void
    {
        // Should return empty array in test environment.
        $this->assertCount(0, SettingsManager::getCatFilterAvailProdTypes());
    }

    public function testIsProductTypeAllowedWithNullCart(): void
    {
        $cart = new Cart(10000, null, null, 0, null, null, null, []);
        $listType = ProductTypesListTypeEnum::LIST_TYPE_PAYWALL;
        $productType = new LoanTypeEnum(LoanTypeEnum::INSTALLMENTS_ZERO_PERCENT);

        $this->assertTrue(SettingsManager::isProductTypeAllowed($listType, $productType, $cart));
    }

    public function testGetAllowedProductTypesWithNullCart(): void
    {
        $cart = new Cart(10000, null, null, 0, null, null, null, []);
        $listType = ProductTypesListTypeEnum::LIST_TYPE_PAYWALL;

        $this->assertNull(SettingsManager::getAllowedProductTypes($listType, $cart));

        /* Inactive filters return null even with returnOnlyArray: the widget config forwards it as availableProductTypes,
           where null means "no filter" and [] would hide the widget. */
        $this->assertNull(SettingsManager::getAllowedProductTypes($listType, $cart, true));
    }

    public function testConstantsExist(): void
    {
        // Test that the class has access to required enum constants.
        $this->assertTrue(class_exists(ProductTypesListTypeEnum::class));
        $this->assertInternalType('string', ProductTypesListTypeEnum::LIST_TYPE_PAYWALL);
        $this->assertInternalType('string', ProductTypesListTypeEnum::LIST_TYPE_WIDGET);
    }

    public function testStaticMethodsExist(): void
    {
        // Verify all expected static methods exist and are callable.
        $this->assertTrue(method_exists(SettingsManager::class, 'getProductTypesSelectList'));
        $this->assertTrue(method_exists(SettingsManager::class, 'getWidgetTypesSelectList'));
        $this->assertTrue(method_exists(SettingsManager::class, 'getProductTypes'));
        $this->assertTrue(method_exists(SettingsManager::class, 'getProductTypesStrings'));
        $this->assertTrue(method_exists(SettingsManager::class, 'getProductTypesEnums'));
        $this->assertTrue(method_exists(SettingsManager::class, 'getWidgetTypes'));
        $this->assertTrue(method_exists(SettingsManager::class, 'isProductTypeAllowed'));
        $this->assertTrue(method_exists(SettingsManager::class, 'getAllowedProductTypes'));
        $this->assertTrue(method_exists(SettingsManager::class, 'getProductCategoryFilters'));
        $this->assertTrue(method_exists(SettingsManager::class, 'productCategoryFiltersActive'));
        $this->assertTrue(method_exists(SettingsManager::class, 'getCatFilterAvailProdTypes'));
    }

    public function testFailedListReadServesStaleCopyAndIsNotRetried(): void
    {
        TestState::reset();

        $apiClient = new StubApiClient();
        $apiClient->productTypesException = new \RuntimeException('API down');
        TestState::setApiClient($apiClient);

        $cacheKey = 'product_types.paywall.' . \Comfino\Main::getShopLanguage();

        // Last good list, kept without expiry; the regular entry has expired.
        CacheManager::set("$cacheKey.stale", ['PAY_LATER' => 'Pay later']);

        $this->assertSame(['PAY_LATER' => 'Pay later'], SettingsManager::getProductTypes('paywall'));
        $this->assertSame(['PAY_LATER' => 'Pay later'], SettingsManager::getProductTypes('paywall'));
        $this->assertSame(1, $apiClient->getCallCount('getProductTypes'), 'A failure is negative-cached, not retried on every read.');

        TestState::reset();
    }

    public function testFailedListReadWithoutStaleCopyReportsErrorOnce(): void
    {
        TestState::reset();

        $apiClient = new StubApiClient();
        $apiClient->productTypesException = new \RuntimeException('API down');
        TestState::setApiClient($apiClient);

        $this->assertSame(['error' => 'API down'], SettingsManager::getProductTypes('paywall', true));
        $this->assertSame(['error' => 'API down'], SettingsManager::getProductTypes('paywall', true));
        $this->assertSame([], SettingsManager::getProductTypes('paywall'));
        $this->assertSame(1, $apiClient->getCallCount('getProductTypes'));

        TestState::reset();
    }
}
