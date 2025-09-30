<?php

namespace Comfino\Tests\Configuration;

use Comfino\Configuration\SettingsManager;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Main;
use Comfino\Api\Dto\Payment\LoanTypeEnum;
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
        // Create a mock cart (this would need to be a proper Cart object in real usage).
        $mockCart = $this->createMock(Cart::class);
        $mockCart->method('getTotalValue')->willReturn(10000);
        
        $listType = ProductTypesListTypeEnum::LIST_TYPE_PAYWALL;
        $mockProductType = $this->createMock(LoanTypeEnum::class);

        $this->assertTrue(SettingsManager::isProductTypeAllowed($listType, $mockProductType, $mockCart));
    }

    public function testGetAllowedProductTypesWithNullCart(): void
    {
        // Create a mock cart.
        $mockCart = $this->createMock(Cart::class);
        $mockCart->method('getTotalValue')->willReturn(10000);
        $mockCart->method('getCartItems')->willReturn([]);

        $listType = ProductTypesListTypeEnum::LIST_TYPE_PAYWALL;

        $this->assertNull(SettingsManager::getAllowedProductTypes($listType, $mockCart));

        // Test with returnOnlyArray flag.
        $this->assertCount(0, SettingsManager::getAllowedProductTypes($listType, $mockCart, true));
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
}
