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

    public function testUninstall(): void
    {
        $testDir = '/test/uninstall/dir';

        // unstall() should not throw exceptions.
        Main::uninstall($testDir);

        // Verify it completes without errors (no assertion needed, just checking no exception).
        $this->assertEquals($testDir, Main::getPluginDirectory());
    }

    public function testInstall(): void
    {
        // install() should not throw exceptions.
        Main::install();

        // Verify it completes without errors (no assertion needed, just checking no exception).
        $this->assertTrue(true);
    }

    public function testReset(): void
    {
        $stats = Main::reset();

        // Verify the structure of returned statistics.
        $this->assertInternalType('array', $stats);
        $this->assertArrayHasKey('config_repaired', $stats);
        $this->assertArrayHasKey('config_failed', $stats);
        $this->assertArrayHasKey('operations', $stats);
        $this->assertInternalType('array', $stats['operations']);
    }

    public function testReadInstallLog(): void
    {
        $log = Main::readInstallLog();

        // Should return string (empty if log doesn't exist).
        $this->assertInternalType('string', $log);
    }

    public function testReadUpgradeLog(): void
    {
        $log = Main::readUpgradeLog();

        // Should return string (empty if log doesn't exist).
        $this->assertInternalType('string', $log);
    }

    public function testReadUninstallLog(): void
    {
        $log = Main::readUninstallLog();

        // Should return string (empty if log doesn't exist).
        $this->assertInternalType('string', $log);
    }

    public function testUpdateUpgradeLog(): void
    {
        $testContent = 'Test upgrade log entry';

        // Should not throw exceptions.
        Main::updateUpgradeLog($testContent);

        $this->assertTrue(true);
    }
}
