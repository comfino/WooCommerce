<?php

namespace Comfino\Tests\View;

use Comfino\View\TemplateManager;
use Comfino\Main;

class TemplateManagerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Set plugin paths.
        Main::setPluginDirectory(__DIR__ . '/../..');
        Main::setPluginFile(__DIR__ . '/../../comfino-payment-gateway.php');

        // Mock functions are handled in bootstrap.php
    }

    public function testRenderViewWithDisplay(): void
    {
        $name = 'test-template';
        $path = 'admin';
        $variables = ['title' => 'Test Title', 'content' => 'Test Content'];
        $display = true;

        // Capture output since display=true will echo the template.
        ob_start();
        $result = TemplateManager::renderView($name, $path, $variables, $display);
        $output = ob_get_clean();

        $this->assertEmpty($result); // Should return empty string when displaying.
        $this->assertContains('Template: test-template.php', $output);
    }

    public function testRenderViewWithoutDisplay(): void
    {
        $name = 'test-template';
        $path = 'front';
        $variables = ['title' => 'Test Title', 'content' => 'Test Content'];
        $display = false;

        $result = TemplateManager::renderView($name, $path, $variables, $display);

        $this->assertNotEmpty($result);
        $this->assertContains('Template HTML: test-template.php', $result);
    }

    public function testRenderViewWithEmptyPath(): void
    {
        $name = 'test-template';
        $path = '';
        $variables = [];
        $display = false;

        $result = TemplateManager::renderView($name, $path, $variables, $display);

        $this->assertContains('test-template.php', $result);
    }

    public function testRenderViewWithLeadingSlashInPath(): void
    {
        $name = 'test-template';
        $path = '/admin/';
        $variables = [];
        $display = false;

        $result = TemplateManager::renderView($name, $path, $variables, $display);

        $this->assertContains('test-template.php', $result);
    }

    public function testRenderViewWithTrailingSlashInPath(): void
    {
        $name = 'test-template';
        $path = 'front/';
        $variables = [];
        $display = false;

        $result = TemplateManager::renderView($name, $path, $variables, $display);

        $this->assertContains('test-template.php', $result);
    }

    public function testRenderViewWithSpacesInPath(): void
    {
        $name = 'test-template';
        $path = ' emails ';
        $variables = [];
        $display = false;

        $result = TemplateManager::renderView($name, $path, $variables, $display);

        $this->assertContains('test-template.php', $result);
    }

    public function testRenderViewWithComplexVariables(): void
    {
        $name = 'complex-template';
        $path = 'admin';
        $variables = [
            'string' => 'Test String',
            'array' => ['item1', 'item2', 'item3'],
            'number' => 42,
            'boolean' => true,
            'object' => (object) ['prop' => 'value'],
        ];
        $display = false;

        $result = TemplateManager::renderView($name, $path, $variables, $display);

        $this->assertContains('complex-template.php', $result);
    }

    public function testRenderViewWithDefaultParameters(): void
    {
        $name = 'simple-template';
        $path = 'front';

        $this->expectOutputString('<div>Template: simple-template.php</div>');

        // Test with only required parameters.
        $result = TemplateManager::renderView($name, $path);

        $this->assertEmpty($result); // Display defaults to true, so should return empty string.
    }

    public function testRenderViewBuildsCorrectTemplatePath(): void
    {
        $name = 'test-template';
        $path = 'subdir/emails';
        $variables = [];
        $display = false;

        $result = TemplateManager::renderView($name, $path, $variables, $display);

        // The template path should be constructed correctly.
        $this->assertContains('test-template.php', $result);
    }

    public function testStaticMethodExists(): void
    {
        // Verify the static method exists and is callable.
        $this->assertTrue(method_exists(TemplateManager::class, 'renderView'));
        $this->assertTrue(is_callable([TemplateManager::class, 'renderView']));
    }

    public function testRenderViewReturnTypes(): void
    {
        $name = 'test-template';
        $path = 'admin';
        $variables = [];

        // Test display=true returns empty string.
        ob_start();
        $resultDisplay = TemplateManager::renderView($name, $path, $variables, true);
        ob_end_clean();

        $this->assertEmpty($resultDisplay);

        // Test display=false returns template HTML.
        $resultNoDisplay = TemplateManager::renderView($name, $path, $variables, false);

        $this->assertNotEmpty($resultNoDisplay);
    }

    public function testRenderViewTrimsResult(): void
    {
        $name = 'test-template';
        $path = 'front';
        $variables = [];
        $display = false;

        // The method calls trim() on the result from wc_get_template_html.
        $result = TemplateManager::renderView($name, $path, $variables, $display);

        // Result should be trimmed (no leading/trailing whitespace).
        $this->assertEquals(trim($result), $result);
    }
}
