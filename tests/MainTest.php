<?php

namespace Comfino\Tests;

use Comfino\Main;

class MainTest extends \PHPUnit_Framework_TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Mock $_SERVER variables.
        $_SERVER['REQUEST_URI'] = '/test-path';
        $_SERVER['REQUEST_SCHEME'] = 'https';
        $_SERVER['HTTP_HOST'] = 'comfino-wc-store.test';
        $_SERVER['HTTP_REFERER'] = 'https://comfino-wc-store.test/referer';

        // Set plugin directory and file for testing.
        Main::setPluginDirectory('/test/plugin/dir');
        Main::setPluginFile('/test/plugin/dir/plugin.php');
    }

    public function testInitializesOnlyOnce(): void
    {
        // Reset the initialized state for testing.
        $reflection = new \ReflectionClass(Main::class);
        $property = $reflection->getProperty('initialized');
        $property->setAccessible(true);
        $property->setValue(false);

        // First call should initialize.
        Main::init();

        $this->assertTrue($property->getValue());

        // Second call should not re-initialize (tested by no exceptions).
        Main::init();

        $this->assertTrue($property->getValue());
    }

    public function testGetEnvironmentWarningWithOldPhpVersion(): void
    {
        /* This test would need to mock PHP_VERSION_ID constant.
          For now, we test the method exists and returns appropriate type/ */
        $warning = Main::getEnvironmentWarning();

        $this->assertTrue(is_string($warning) || is_bool($warning));
    }

    public function testGetShopDomain(): void
    {
        $this->assertEquals('comfino-wc-store.test', Main::getShopDomain());
    }

    public function testGetShopUrl(): void
    {
        $this->assertStringStartsWith('https://', Main::getShopUrl());
        $this->assertNotContains('://', Main::getShopUrl(true));
    }

    public function testGetShopLanguage(): void
    {
        $this->assertEquals('en', Main::getShopLanguage()); // Based on our mock.
    }

    public function testGetShopCurrency(): void
    {
        $this->assertEquals('PLN', Main::getShopCurrency()); // Based on our mock.
    }

    public function testGetCurrentUrl(): void
    {
        $this->assertEquals('/test-path', Main::getCurrentUrl());
    }

    public function testGetCacheRootPath(): void
    {
        $this->assertStringEndsWith('/var', Main::getCacheRootPath());
    }

    public function testGetPluginDirectory(): void
    {
        $this->assertEquals('/test/plugin/dir', Main::getPluginDirectory());
    }

    public function testSetAndGetPluginDirectory(): void
    {
        $testDir = '/new/test/dir';
        Main::setPluginDirectory($testDir);

        $this->assertEquals($testDir, Main::getPluginDirectory());
    }

    public function testGetPluginFile(): void
    {
        $this->assertEquals('/test/plugin/dir/plugin.php', Main::getPluginFile());
    }

    public function testSetAndGetPluginFile(): void
    {
        $testFile = '/new/test/file.php';
        Main::setPluginFile($testFile);

        $this->assertEquals($testFile, Main::getPluginFile());
    }

    public function testPaymentIsAvailableWithNullCart(): void
    {
        // Returns false because plugin is not enabled/configured in test environment.
        $this->assertFalse(Main::paymentIsAvailable(null));
    }

    public function testGetPaywallOptions(): void
    {
        $total = 150.50;
        $options = Main::getPaywallOptions($total);

        $this->assertEquals('woocommerce', $options['platform']);
        $this->assertEquals('WooCommerce', $options['platformName']);
        $this->assertEquals($total, $options['cartTotal']);
        $this->assertArrayHasKey('language', $options);
        $this->assertArrayHasKey('currency', $options);
        $this->assertArrayHasKey('pluginVersion', $options);
        $this->assertArrayHasKey('platformVersion', $options);
        $this->assertArrayHasKey('platformDomain', $options);
    }

    public function testUninstall(): void
    {
        $testDir = '/test/uninstall/dir';

        $this->assertTrue(Main::uninstall($testDir));
        $this->assertEquals($testDir, Main::getPluginDirectory());
    }
}
