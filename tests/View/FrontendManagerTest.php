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

    public function testGetPaywallRenderer(): void
    {
        $renderer1 = FrontendManager::getPaywallRenderer();
        $renderer2 = FrontendManager::getPaywallRenderer();

        $this->assertSame($renderer1, $renderer2); // Should be singleton.
    }

    public function testGetPaywallIframeRenderer(): void
    {
        $renderer1 = FrontendManager::getPaywallIframeRenderer();
        $renderer2 = FrontendManager::getPaywallIframeRenderer();

        $this->assertSame($renderer1, $renderer2); // Should be singleton.
    }

    public function testRenderHiddenInput(): void
    {
        $fieldKey = 'test_field';
        $fieldValue = 'test_value';
        $data = ['title' => 'Test Field', 'type' => 'hidden'];
        $wcSettings = new WC_Settings_API();

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
        $wcSettings = new WC_Settings_API();

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
        $wcSettings = new WC_Settings_API();

        $html = FrontendManager::renderCheckboxSet($fieldKey, $fieldValue, $data, $wcSettings);

        $this->assertEmpty($html); // Should return empty string when no values provided.
    }

    public function testRenderProductCategoryTree(): void
    {
        $data = [
            'title' => 'Product Categories',
            'id' => 'category_tree',
            'product_type' => 'INSTALLMENTS',
            'selected_categories' => [1, 2, 3]
        ];

        $this->assertContains('Product Categories', FrontendManager::renderProductCategoryTree($data));
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

    public function testGetExternalResourcesBaseUrl(): void
    {
        $baseUrl = FrontendManager::getExternalResourcesBaseUrl();

        $this->assertStringStartsWith('https://', $baseUrl);
        $this->assertContains('widget', $baseUrl);
    }

    public function testGetExternalScriptUrl(): void
    {
        $url = FrontendManager::getExternalScriptUrl('test-script.js');

        $this->assertContains('test-script', $url);
        $this->assertContains('.js', $url);
    }

    public function testGetExternalScriptUrlWithEmptyFileName(): void
    {
        $this->assertEmpty(FrontendManager::getExternalScriptUrl(''));
    }

    public function testGetExternalStyleUrl(): void
    {
        $url = FrontendManager::getExternalStyleUrl('test-style.css');

        $this->assertContains('test-style', $url);
        $this->assertContains('.css', $url);
    }

    public function testGetExternalStyleUrlWithEmptyFileName(): void
    {
        $this->assertEmpty(FrontendManager::getExternalStyleUrl(''));
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

    public function testIncludeExternalScripts(): void
    {
        $scripts = ['external-script1.js', 'external-script2.js'];
        $scriptIds = FrontendManager::includeExternalScripts($scripts);

        $this->assertCount(2, $scriptIds);

        foreach ($scriptIds as $scriptId) {
            $this->assertInternalType('string', $scriptId);
            $this->assertStringStartsWith('comfino-script-', $scriptId);
        }
    }

    public function testIncludeExternalStyles(): void
    {
        $styles = ['style1.css', 'style2.css'];
        $styleIds = FrontendManager::includeExternalStyles($styles);

        $this->assertCount(2, $styleIds);

        foreach ($styleIds as $styleId) {
            $this->assertInternalType('string', $styleId);
            $this->assertStringStartsWith('comfino-style-', $styleId);
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

    public function testRegisterExternalScripts(): void
    {
        $scripts = ['external-script1.js', 'external-script2.js'];
        $scriptIds = FrontendManager::registerExternalScripts($scripts);

        $this->assertCount(2, $scriptIds);
    }

    public function testRegisterExternalStyles(): void
    {
        $styles = ['style1.css', 'style2.css'];
        $styleIds = FrontendManager::registerExternalStyles($styles);

        $this->assertCount(2, $styleIds);
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
}
