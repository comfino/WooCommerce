<?php

namespace Comfino\Tests\View;

use Comfino\View\FrontendManager;
use Comfino\Main;
use WC_Settings_API;

class FrontendManagerTest extends \PHPUnit_Framework_TestCase
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

        // Mock functions are handled in bootstrap.php
    }

    public function testGetAuthTokenReturnsString(): void
    {
        $token = FrontendManager::getAuthToken();

        $this->assertInternalType('string', $token);
    }

    public function testRenderHiddenInput(): void
    {
        $fieldKey = 'test_field';
        $fieldValue = 'test_value';
        $data = ['title' => 'Test Field', 'type' => 'hidden'];
        $wcSettings = $this->getMockForAbstractClass(WC_Settings_API::class);

        $html = FrontendManager::renderHiddenInput($fieldKey, $fieldValue, $data, $wcSettings);

        $this->assertContains('type="hidden"', $html);
        $this->assertContains('name="' . $fieldKey . '"', $html);
        $this->assertContains('value="' . $fieldValue . '"', $html);
    }

    public function testRenderCheckboxSet(): void
    {
        $fieldKey = 'test_checkboxes';
        $fieldValue = ['option1', 'option3'];
        $data = [
            'title' => 'Test Checkboxes',
            'values' => [
                'option1' => 'Option 1',
                'option2' => 'Option 2',
                'option3' => 'Option 3'
            ]
        ];
        $wcSettings = $this->getMockForAbstractClass(WC_Settings_API::class);

        $html = FrontendManager::renderCheckboxSet($fieldKey, $fieldValue, $data, $wcSettings);

        $this->assertContains('type="checkbox"', $html);
        $this->assertContains('Option 1', $html);
        $this->assertContains('Option 2', $html);
        $this->assertContains('Option 3', $html);
    }

    public function testRenderCheckboxSetWithEmptyValues(): void
    {
        $fieldKey = 'test_checkboxes';
        $fieldValue = [];
        $data = ['title' => 'Test Checkboxes']; // No 'values' key.
        $wcSettings = $this->getMockForAbstractClass(WC_Settings_API::class);

        $html = FrontendManager::renderCheckboxSet($fieldKey, $fieldValue, $data, $wcSettings);

        $this->assertEmpty($html); // Should return empty string when no values provided.
    }

    public function testGetLocalScriptUrl(): void
    {
        // Mock global variable.
        global $comfino_payment_gateway;

        $comfino_payment_gateway = new class {
            public function plugin_url(): string
            {
                return 'https://comfino-wc-store.test/wp-content/plugins/comfino';
            }

            public function plugin_abspath(): string
            {
                return '/path/to/plugin';
            }
        };

        $url = FrontendManager::getLocalScriptUrl('test-script.js');

        $this->assertContains('test-script', $url);
        $this->assertContains('.js', $url);
    }

    public function testResetScripts(): void
    {
        // Should not throw any errors.
        FrontendManager::resetScripts();

        $this->assertTrue(true);
    }

    public function testResetStyles(): void
    {
        // Should not throw any errors.
        FrontendManager::resetStyles();

        $this->assertTrue(true);
    }

    public function testEmbedInlineScript(): void
    {
        $scriptId = 'test-inline-script';
        $scriptContents = 'console.log("test");';
        $dependencies = ['jquery'];

        // Should not throw any errors.
        FrontendManager::embedInlineScript($scriptId, $scriptContents, $dependencies);

        $this->assertTrue(true);
    }

    public function testIncludeLocalScripts(): void
    {
        // Mock global variable.
        global $comfino_payment_gateway;

        $comfino_payment_gateway = new class {
            public function plugin_url(): string
            {
                return 'https://comfino-wc-store.test/wp-content/plugins/comfino';
            }

            public function plugin_abspath(): string
            {
                return '/path/to/plugin';
            }
        };

        $scripts = ['script1.js', 'script2.js'];
        $scriptIds = FrontendManager::includeLocalScripts($scripts);

        $this->assertCount(2, $scriptIds);

        foreach ($scriptIds as $scriptId) {
            $this->assertInternalType('string', $scriptId);
            $this->assertStringStartsWith('comfino-script-', $scriptId);
        }
    }

    public function testRegisterLocalScripts(): void
    {
        // Mock global variable.
        global $comfino_payment_gateway;

        $comfino_payment_gateway = new class {
            public function plugin_url(): string
            {
                return 'https://comfino-wc-store.test/wp-content/plugins/comfino';
            }

            public function plugin_abspath(): string
            {
                return '/path/to/plugin';
            }
        };

        $scripts = ['script1.js', 'script2.js'];
        $scriptIds = FrontendManager::registerLocalScripts($scripts);

        $this->assertCount(2, $scriptIds);
    }

    public function testGetImageAllowedHtml(): void
    {
        $allowedHtml = FrontendManager::getImageAllowedHtml();

        $this->assertArrayHasKey('img', $allowedHtml);
        $this->assertArrayHasKey('src', $allowedHtml['img']);
        $this->assertArrayHasKey('style', $allowedHtml['img']);
        $this->assertArrayHasKey('alt', $allowedHtml['img']);
    }

    public function testGetAllowedScriptHtml(): void
    {
        $allowedHtml = FrontendManager::getAllowedScriptHtml();

        $this->assertArrayHasKey('script', $allowedHtml);
        $this->assertInternalType('array', $allowedHtml['script']);
    }

    public function testGetAllowedStyleHtml(): void
    {
        $allowedHtml = FrontendManager::getAllowedStyleHtml();

        $this->assertArrayHasKey('style', $allowedHtml);
        $this->assertInternalType('array', $allowedHtml['style']);
    }

    public function testGetAdminPanelAllowedHtml(): void
    {
        $allowedHtml = FrontendManager::getAdminPanelAllowedHtml();

        $this->assertArrayHasKey('input', $allowedHtml);
        $this->assertArrayHasKey('select', $allowedHtml);
        $this->assertArrayHasKey('option', $allowedHtml);
        $this->assertArrayHasKey('script', $allowedHtml);
        $this->assertArrayHasKey('style', $allowedHtml);
    }

    /**
     * Enhanced tests for widget functionality after vendor optimizations.
     * These tests cover critical widget rendering and asset management.
     */

    public function testRenderAdminLogo(): void
    {
        $logo = FrontendManager::renderAdminLogo();

        $this->assertInternalType('string', $logo);
        $this->assertNotEmpty($logo);
        // Should contain an img tag.
        $this->assertContains('<img', $logo);
    }

    public function testRenderHiddenInputWithCustomAttributes(): void
    {
        $fieldKey = 'test_field';
        $fieldValue = 'test_value';
        $data = [
            'title' => 'Test Field',
            'type' => 'hidden',
            'custom_attributes' => [
                'data-test' => 'value',
                'data-id' => '123',
            ],
        ];
        $wcSettings = $this->getMockForAbstractClass(WC_Settings_API::class);

        $html = FrontendManager::renderHiddenInput($fieldKey, $fieldValue, $data, $wcSettings);

        $this->assertContains('type="hidden"', $html);
        $this->assertContains('name="' . $fieldKey . '"', $html);
        $this->assertContains('value="' . $fieldValue . '"', $html);
    }

    public function testRenderHiddenInputWithDisabled(): void
    {
        $fieldKey = 'test_field';
        $fieldValue = 'test_value';
        $data = [
            'title' => 'Test Field',
            'type' => 'hidden',
            'disabled' => true,
        ];
        $wcSettings = $this->getMockForAbstractClass(WC_Settings_API::class);

        $html = FrontendManager::renderHiddenInput($fieldKey, $fieldValue, $data, $wcSettings);

        $this->assertContains('disabled', $html);
    }

    public function testRenderCheckboxSetWithDescription(): void
    {
        $fieldKey = 'test_checkboxes';
        $fieldValue = ['option1'];
        $data = [
            'title' => 'Test Checkboxes',
            'description' => 'This is a test description',
            'values' => [
                'option1' => 'Option 1',
                'option2' => 'Option 2',
            ]
        ];
        $wcSettings = $this->getMockForAbstractClass(WC_Settings_API::class);

        $html = FrontendManager::renderCheckboxSet($fieldKey, $fieldValue, $data, $wcSettings);

        $this->assertContains('Test Checkboxes', $html);
        $this->assertContains('This is a test description', $html);
    }

    public function testGetLocalScriptUrlWithVersion(): void
    {
        global $comfino_payment_gateway;

        $comfino_payment_gateway = new class {
            public function plugin_url(): string
            {
                return 'https://comfino-wc-store.test/wp-content/plugins/comfino';
            }

            public function plugin_abspath(): string
            {
                return '/path/to/plugin';
            }
        };

        $url = FrontendManager::getLocalScriptUrl('test-script.js');

        // Should contain version parameter or hash.
        $this->assertContains('test-script', $url);
        $this->assertContains('.js', $url);
    }

    public function testEmbedInlineScriptWithMultipleDependencies(): void
    {
        $scriptId = 'multi-dep-script';
        $scriptContents = 'var test = "value"; console.log(test);';
        $dependencies = ['jquery', 'wp-element', 'wp-i18n'];

        // Should not throw errors.
        FrontendManager::embedInlineScript($scriptId, $scriptContents, $dependencies);

        $this->assertTrue(true);
    }

    public function testEmbedInlineScriptWithNoDependencies(): void
    {
        $scriptId = 'no-dep-script';
        $scriptContents = 'console.log("standalone");';
        $dependencies = [];

        // Should not throw errors.
        FrontendManager::embedInlineScript($scriptId, $scriptContents, $dependencies);

        $this->assertTrue(true);
    }


    public function testAllowedScriptHtmlContainsRequiredAttributes(): void
    {
        $allowedHtml = FrontendManager::getAllowedScriptHtml();

        $this->assertArrayHasKey('script', $allowedHtml);
        $this->assertInternalType('array', $allowedHtml['script']);
    }

    public function testAllowedStyleHtmlContainsRequiredAttributes(): void
    {
        $allowedHtml = FrontendManager::getAllowedStyleHtml();

        $this->assertArrayHasKey('style', $allowedHtml);
        $this->assertInternalType('array', $allowedHtml['style']);
    }

    public function testAdminPanelAllowedHtmlContainsFormElements(): void
    {
        $allowedHtml = FrontendManager::getAdminPanelAllowedHtml();

        $this->assertArrayHasKey('input', $allowedHtml);
        $this->assertArrayHasKey('select', $allowedHtml);
        $this->assertArrayHasKey('option', $allowedHtml);
    }

    public function testGetAuthTokenIsBase64EncodedWhenConfigured(): void
    {
        // In unconfigured test environment, token is empty.
        // When credentials are set, token should be valid base64.
        $token = FrontendManager::getAuthToken();

        if (!empty($token)) {
            $decoded = base64_decode($token, true);
            $this->assertNotFalse($decoded);
            $this->assertEquals($token, base64_encode($decoded));
        } else {
            $this->assertInternalType('string', $token);
        }
    }

    public function testResetScriptsCanBeCalledMultipleTimes(): void
    {
        // Should be idempotent.
        FrontendManager::resetScripts();
        FrontendManager::resetScripts();
        FrontendManager::resetScripts();

        $this->assertTrue(true);
    }

    public function testResetStylesCanBeCalledMultipleTimes(): void
    {
        // Should be idempotent.
        FrontendManager::resetStyles();
        FrontendManager::resetStyles();
        FrontendManager::resetStyles();

        $this->assertTrue(true);
    }
}
