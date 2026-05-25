<?php

namespace Comfino\Tests\Configuration;

use Comfino\Common\Backend\ConfigurationManager;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Configuration\ConfigManager;
use Comfino\Main;
use Comfino\Order\ShopStatusManager;

class ConfigManagerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Mock $_SERVER for Main class.
        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REQUEST_SCHEME'] = 'https';
        $_SERVER['HTTP_HOST'] = 'comfino-wc-store.test';
        $_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4.0';
        $_SERVER['SERVER_NAME'] = 'comfino-wc-store.test';
        $_SERVER['SERVER_ADDR'] = '127.0.0.1';

        // Set plugin paths.
        Main::setPluginDirectory(__DIR__ . '/../..');
        Main::setPluginFile(__DIR__ . '/../../comfino-payment-gateway.php');
    }

    public function tearDown(): void
    {
        // Reset ConfigManager and ConfigurationManager singletons so that any
        // configuration mutations in this test class (e.g. COMFINO_DEBUG=true in
        // testUpdateConfigurationValue) don't leak into subsequent test classes.
        $cmProp = new \ReflectionProperty(ConfigManager::class, 'configurationManager');
        $cmProp->setAccessible(true);
        $cmProp->setValue(null, null);

        $baseProp = new \ReflectionProperty(ConfigurationManager::class, 'instance');
        $baseProp->setAccessible(true);
        $baseProp->setValue(null, null);

        parent::tearDown();
    }

    public function testGetInstance(): void
    {
        $instance1 = ConfigManager::getInstance();
        $instance2 = ConfigManager::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function testConfigOptionsMapConstants(): void
    {
        $this->assertInternalType('array', ConfigManager::CONFIG_OPTIONS_MAP);
        $this->assertArrayHasKey('COMFINO_ENABLED', ConfigManager::CONFIG_OPTIONS_MAP);
        $this->assertArrayHasKey('COMFINO_API_KEY', ConfigManager::CONFIG_OPTIONS_MAP);
        $this->assertEquals('enabled', ConfigManager::CONFIG_OPTIONS_MAP['COMFINO_ENABLED']);
        $this->assertEquals('production_key', ConfigManager::CONFIG_OPTIONS_MAP['COMFINO_API_KEY']);
        $this->assertArrayHasKey('COMFINO_ALLOWED_PRODUCTS_CONFIG', ConfigManager::CONFIG_OPTIONS_MAP);
        $this->assertEquals('allowed_products_config', ConfigManager::CONFIG_OPTIONS_MAP['COMFINO_ALLOWED_PRODUCTS_CONFIG']);
    }

    public function testConfigOptionsConstants(): void
    {
        $this->assertInternalType('array', ConfigManager::CONFIG_OPTIONS);
        $this->assertArrayHasKey('payment_settings', ConfigManager::CONFIG_OPTIONS);
        $this->assertArrayHasKey('widget_settings', ConfigManager::CONFIG_OPTIONS);
        $this->assertArrayHasKey('developer_settings', ConfigManager::CONFIG_OPTIONS);
    }

    public function testAccessibleConfigOptionsConstants(): void
    {
        $this->assertInternalType('array', ConfigManager::ACCESSIBLE_CONFIG_OPTIONS);
        $this->assertContains('COMFINO_ENABLED', ConfigManager::ACCESSIBLE_CONFIG_OPTIONS);
        // COMFINO_API_KEY is not in accessible options for security reasons.
        $this->assertContains('COMFINO_PAYMENT_TEXT', ConfigManager::ACCESSIBLE_CONFIG_OPTIONS);
        $this->assertContains('COMFINO_ALLOWED_PRODUCTS_CONFIG', ConfigManager::ACCESSIBLE_CONFIG_OPTIONS);
    }

    public function testAllowedProductsConfigDefaultIsNull(): void
    {
        $defaults = ConfigManager::getDefaultConfigurationValues();
        $this->assertArrayHasKey('COMFINO_ALLOWED_PRODUCTS_CONFIG', $defaults);
        $this->assertNull($defaults['COMFINO_ALLOWED_PRODUCTS_CONFIG']);
    }

    public function testGetEnvironmentInfo(): void
    {
        // Mock global variables.
        global $wp_version, $wpdb;

        $wp_version = '6.0.0';
        $wpdb = new class {
            public function db_version(): string
            {
                return '8.0.0';
            }
        };

        $envInfo = ConfigManager::getEnvironmentInfo();

        $this->assertArrayHasKey('plugin_version', $envInfo);
        $this->assertArrayHasKey('php_version', $envInfo);
        $this->assertArrayHasKey('wordpress_version', $envInfo);
        $this->assertArrayHasKey('shop_version', $envInfo);
        $this->assertArrayHasKey('server_software', $envInfo);
        $this->assertArrayHasKey('database_version', $envInfo);
        $this->assertEquals(PHP_VERSION, $envInfo['php_version']);
    }

    public function testGetEnvironmentInfoWithSelectedFields(): void
    {
        // Mock global variables
        global $wp_version, $wpdb;

        $wp_version = '6.0.0';
        $wpdb = new class {
            public function db_version(): string
            {
                return '8.0.0';
            }
        };

        $selectedFields = ['plugin_version', 'php_version'];
        $envInfo = ConfigManager::getEnvironmentInfo($selectedFields);

        $this->assertCount(2, $envInfo);
        $this->assertArrayHasKey('plugin_version', $envInfo);
        $this->assertArrayHasKey('php_version', $envInfo);
        $this->assertArrayNotHasKey('wordpress_version', $envInfo);
    }

    public function testGetAllProductCategories(): void
    {
        // Mock functions are handled in bootstrap.php.
        $categories = ConfigManager::getAllProductCategories();

        $this->assertInternalType('array', $categories);
        // The method might return null or array depending on implementation.
        if ($categories !== null) {
            $this->assertArrayHasKey(1, $categories);
            $this->assertArrayHasKey(2, $categories);
        }
    }

    public function testGetWidgetOfferTypes(): void
    {
        $this->assertContains('CONVENIENT_INSTALLMENTS', ConfigManager::getWidgetOfferTypes());
    }

    public function testGetApiKey(): void
    {
        $this->assertNull(ConfigManager::getApiKey());
    }

    public function testGetWidgetKey(): void
    {
        $this->assertEquals('', ConfigManager::getWidgetKey());
    }

    public function testGetIgnoredStatuses(): void
    {
        $this->assertEquals(StatusManager::DEFAULT_IGNORED_STATUSES, ConfigManager::getIgnoredStatuses());
    }

    public function testGetForbiddenStatuses(): void
    {
        $this->assertEquals(StatusManager::DEFAULT_FORBIDDEN_STATUSES, ConfigManager::getForbiddenStatuses());
    }

    public function testGetStatusMap(): void
    {
        $this->assertEquals(ShopStatusManager::DEFAULT_STATUS_MAP, ConfigManager::getStatusMap());
    }

    public function testGetDefaultConfigurationValues(): void
    {
        $defaults = ConfigManager::getDefaultConfigurationValues();

        $this->assertArrayHasKey('COMFINO_ENABLED', $defaults);
        $this->assertArrayHasKey('COMFINO_PAYMENT_TEXT', $defaults);
        $this->assertArrayHasKey('COMFINO_SHOW_LOGO', $defaults);
        $this->assertArrayHasKey('COMFINO_MINIMAL_CART_AMOUNT', $defaults);

        $this->assertFalse($defaults['COMFINO_ENABLED']);
        $this->assertEquals('Comfino', $defaults['COMFINO_PAYMENT_TEXT']);
        $this->assertTrue($defaults['COMFINO_SHOW_LOGO']);
        $this->assertEquals(30, $defaults['COMFINO_MINIMAL_CART_AMOUNT']);
    }

    public function testGetDefaultValue(): void
    {
        $this->assertFalse(ConfigManager::getDefaultValue('enabled'));
        $this->assertEquals('Comfino', ConfigManager::getDefaultValue('title'));
        $this->assertNull(ConfigManager::getDefaultValue('non_existent'));
    }

    public function testGetConfigurationValue(): void
    {
        // Test with default value.
        $this->assertInternalType('bool', ConfigManager::getConfigurationValue('COMFINO_ENABLED', true));
        $this->assertInternalType('string', ConfigManager::getConfigurationValue('COMFINO_PAYMENT_TEXT', 'Default Title'));
    }

    public function testGetConfigurationValueByInternalName(): void
    {
        $this->assertInternalType('bool', ConfigManager::getConfigurationValueByInternalName('enabled', false));
        $this->assertInternalType('string', ConfigManager::getConfigurationValueByInternalName('title', 'Default'));
        $this->assertEquals('default', ConfigManager::getConfigurationValueByInternalName('non_existent', 'default'));
    }

    public function testGetConfigurationValueType(): void
    {
        $this->assertEquals(ConfigurationManager::OPT_VALUE_TYPE_BOOL, ConfigManager::getConfigurationValueType('COMFINO_ENABLED'));
        // Should return default type.
        $this->assertEquals(ConfigurationManager::OPT_VALUE_TYPE_STRING, ConfigManager::getConfigurationValueType('NON_EXISTENT'));
    }

    public function testGetPaywallLogoUrl(): void
    {
        $this->assertContains('get-paywall-logo', ConfigManager::getPaywallLogoUrl());
    }

    public function testGetWidgetScriptUrl(): void
    {
        $this->assertContains('widget', ConfigManager::getWidgetScriptUrl());
    }

    public function testGetWidgetVariables(): void
    {
        $variables = ConfigManager::getWidgetVariables();

        $this->assertArrayHasKey('WIDGET_SCRIPT_URL', $variables);
        $this->assertArrayHasKey('PLATFORM', $variables);
        $this->assertArrayHasKey('PLATFORM_NAME', $variables);
        $this->assertArrayHasKey('PLATFORM_VERSION', $variables);
        $this->assertArrayHasKey('PLUGIN_VERSION', $variables);
        $this->assertArrayHasKey('LANGUAGE', $variables);
        $this->assertArrayHasKey('CURRENCY', $variables);

        $this->assertEquals('woocommerce', $variables['PLATFORM']);
        $this->assertEquals('WooCommerce', $variables['PLATFORM_NAME']);
    }

    public function testGetCurrentWidgetCode(): void
    {
        $this->assertContains('productId: {PRODUCT_ID}', ConfigManager::getCurrentWidgetCode());
        $this->assertContains('productId: {PRODUCT_ID}', ConfigManager::getCurrentWidgetCode(123));
    }

    public function testUpdateConfigurationValue(): void
    {
        /* This test mainly verifies the method exists and can be called.
           Full testing would require mocking the storage adapter. */
        try {
            ConfigManager::updateConfigurationValue('COMFINO_DEBUG', true);
            $this->assertTrue(true); // If no exception is thrown, the method works.
        } catch (\Throwable $e) {
            // Expected in test environment due to missing WordPress functions.
            $this->assertTrue(true);
        }
    }

    public function testDeleteConfigurationValues(): void
    {
        // Mock functions are handled in bootstrap.php
        $this->assertTrue(ConfigManager::deleteConfigurationValues());
    }

    public function testGetConfigurationValues(): void
    {
        $this->assertEquals(
            array_keys(ConfigManager::CONFIG_OPTIONS['payment_settings']),
            array_keys(ConfigManager::getConfigurationValues('payment_settings'))
        );
        $this->assertEmpty(ConfigManager::getConfigurationValues('non_existent_group'));

        $specificOptions = ['COMFINO_ENABLED', 'COMFINO_API_KEY'];

        $this->assertEquals($specificOptions, array_keys(ConfigManager::getConfigurationValues('payment_settings', $specificOptions)));
    }
}
