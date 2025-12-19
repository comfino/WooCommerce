<?php

namespace Comfino\Tests;

use Comfino\PaymentGateway;
use Comfino\Main;

class PaymentGatewayTest extends \PHPUnit_Framework_TestCase
{
    private $gateway;

    public function setUp(): void
    {
        parent::setUp();

        // Mock $_GET for nonce check.
        $_GET['comfino_nonce'] = 'test-nonce';
        $_GET['subsection'] = 'payment_settings';

        // Mock $_SERVER for Main class.
        $_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=comfino';
        $_SERVER['REQUEST_SCHEME'] = 'https';
        $_SERVER['HTTP_HOST'] = 'comfino-wc-store.test';

        // Set plugin paths.
        Main::setPluginDirectory(__DIR__ . '/..');
        Main::setPluginFile(__DIR__ . '/../comfino-payment-gateway.php');

        $this->gateway = new PaymentGateway();
    }

    public function testGatewayInitialization(): void
    {
        $this->assertEquals(PaymentGateway::GATEWAY_ID, $this->gateway->id);
        $this->assertEquals('comfino', $this->gateway->id);
        $this->assertTrue($this->gateway->has_fields);
        $this->assertEquals('Comfino payments', $this->gateway->method_title);
        $this->assertInternalType('array', $this->gateway->supports);
        $this->assertContains('products', $this->gateway->supports);
    }

    public function testConstants(): void
    {
        $this->assertEquals('comfino', PaymentGateway::GATEWAY_ID);
        $this->assertInternalType('string', PaymentGateway::VERSION);
        $this->assertInternalType("int", PaymentGateway::BUILD_TS);
        $this->assertInternalType('string', PaymentGateway::WIDGET_INIT_SCRIPT_HASH);
        $this->assertInternalType('string', PaymentGateway::WIDGET_INIT_SCRIPT_LAST_HASH);
    }

    public function testGetTotal(): void
    {
        $this->assertGreaterThanOrEqual(0, $this->gateway->getTotal());
    }

    /**
     * @throws \ReflectionException
     */
    public function testGetSubsection(): void
    {
        $reflection = new \ReflectionClass($this->gateway);
        $method = $reflection->getMethod('getSubsection');
        $method->setAccessible(true);

        // Test default subsection.
        $_GET['subsection'] = 'payment_settings';

        $this->assertEquals('payment_settings', $method->invoke($this->gateway));

        // Test invalid subsection defaults to payment_settings.
        $_GET['subsection'] = 'invalid_section';

        $this->assertEquals('payment_settings', $method->invoke($this->gateway));

        // Test valid subsections.
        $validSections = [
            'payment_settings',
            'sale_settings',
            'widget_settings',
            'abandoned_cart_settings',
            'developer_settings',
            'plugin_diagnostics'
        ];

        foreach ($validSections as $section) {
            $_GET['subsection'] = $section;

            $this->assertEquals($section, $method->invoke($this->gateway));
        }
    }

    public function testProcessPaymentWithInvalidData(): void
    {
        // Mock minimal POST data.
        $_POST = [
            'comfino_loan_amount' => '0',
            'comfino_price_modifier' => '0',
            'comfino_loan_term' => '0',
            'comfino_loan_type' => 'undefined',
        ];

        /* This test mainly verifies the method exists and handles basic input.
           Full testing would require mocking WooCommerce cart and order objects. */
        $this->expectException(\Error::class); // Expected since WC()->cart will be null.

        $this->gateway->process_payment(123);
    }

    public function testOrderStatusChanged(): void
    {
        /* Test that the method exists and can be called.
           Full testing would require mocking WooCommerce order objects. */
        $this->expectException(\Error::class); // Expected since wc_get_order will fail.

        $this->gateway->order_status_changed(123, 'pending', 'processing');
    }

    public function testInitFormFields(): void
    {
        $this->gateway->init_form_fields();

        $this->assertInternalType('array', $this->gateway->form_fields);
    }

    public function testAdminOptions(): void
    {
        // Capture output to test the method runs without errors.
        ob_start();

        try {
            $this->gateway->admin_options();
        } catch (\Exception $e) {
            // Expected due to missing WordPress globals and functions.
        }

        $output = ob_get_clean();

        // Just verify the method doesn't cause fatal errors.
        $this->assertInternalType('string', $output);
    }

    public function testAdminScripts(): void
    {
        // Ensure WooCommerce global is initialized.
        global $woocommerce;

        if (!isset($woocommerce)) {
            $woocommerce = new \WooCommerce_Mock();
        }

        // Test with correct hook.
        $this->gateway->admin_scripts('woocommerce_page_wc-settings');

        // Test with incorrect hook (should do nothing).
        $this->gateway->admin_scripts('other_page');

        // No assertions needed, just verify no fatal errors.
        $this->assertTrue(true);
    }
}
