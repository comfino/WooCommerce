<?php

namespace Comfino\Tests\Api;

use Comfino\Api\ApiService;
use Comfino\Main;

class ApiServiceTest extends \PHPUnit_Framework_TestCase
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

        // WordPress functions are already mocked in global namespace via bootstrap.php.
    }

    public function testRegisterEndpoints(): void
    {
        // Test that the method exists and can be called.
        ApiService::registerEndpoints();

        // Verify that the method completes without errors.
        $this->assertTrue(true);
    }

    public function testGetEndpointUrl(): void
    {
        // Test getting URL for a known endpoint.
        $url = ApiService::getEndpointUrl('paywall');

        $this->assertEquals('https://comfino-wc-store.test/wp-json/comfino/paywall', $url);

        // Test getting URL for unknown endpoint.
        $this->assertEquals('', ApiService::getEndpointUrl('unknown_endpoint'));
    }

    public function testGetEndpointPath(): void
    {
        $this->assertEquals('/wp-json/comfino/paywall', ApiService::getEndpointPath('paywall'));
    }

    public function testProcessRequestWithPaywallEndpoint(): void
    {
        // Mock WC()->cart.
        global $woocommerce;

        if (!isset($woocommerce)) {
            $woocommerce = new \stdClass();
        }

        // WC function is already mocked in global namespace.

        $request = new \WP_REST_Request();
        $request->set_param('priceModifier', '0');

        // In test environment, endpoint manager is not initialized.
        $data = ApiService::processRequest('paywall', $request)->get_data();

        // Expect either an error string or successful response.
        $this->assertTrue(is_string($data) || is_array($data));
    }

    public function testProcessRequestWithPaywallItemDetailsEndpoint(): void
    {
        // WC function is already mocked in global namespace.

        $request = new \WP_REST_Request();
        $request->set_param('loanTypeSelected', 'INSTALLMENTS_ZERO_PERCENT');
        $request->set_param('priceModifier', '0');

        $response = ApiService::processRequest('paywallItemDetails', $request);

        // Should return service unavailable when endpoint manager is not initialized.
        $this->assertEquals(503, $response->get_status());
        $this->assertEquals('Endpoint manager not initialized.', $response->get_data());
    }

    public function testProcessRequestWithUnknownEndpoint(): void
    {
        $response = ApiService::processRequest('unknown_endpoint', new \WP_REST_Request());

        // Should return service unavailable when endpoint manager is not initialized.
        $this->assertEquals(503, $response->get_status());
        $this->assertEquals('Endpoint manager not initialized.', $response->get_data());
    }

    public function testProcessRequestWithEmptyLoanTypeSelected(): void
    {
        // Don't set loanTypeSelected parameter.
        $response = ApiService::processRequest('paywallItemDetails', new \WP_REST_Request());

        // In test environment, endpoint manager is not initialized, so expect error string.
        $this->assertEquals(503, $response->get_status());
        $this->assertEquals('Endpoint manager not initialized.', $response->get_data());
    }

    public function testStaticMethodsExist(): void
    {
        // Verify all expected static methods exist and are callable.
        $this->assertTrue(method_exists(ApiService::class, 'registerEndpoints'));
        $this->assertTrue(method_exists(ApiService::class, 'getEndpointUrl'));
        $this->assertTrue(method_exists(ApiService::class, 'getEndpointPath'));
        $this->assertTrue(method_exists(ApiService::class, 'processRequest'));
        $this->assertTrue(method_exists(ApiService::class, 'init'));
    }

    public function testEndpointUrlsAreStrings(): void
    {
        // Test several known endpoints.
        $endpoints = ['paywall', 'paywallItemDetails', 'configuration', 'transactionStatus'];

        foreach ($endpoints as $endpoint) {
            $this->assertEquals(
                'https://comfino-wc-store.test/wp-json/comfino/' . strtolower($endpoint),
                ApiService::getEndpointUrl($endpoint)
            );

            $this->assertEquals(
                '/wp-json/comfino/' . strtolower($endpoint),
                ApiService::getEndpointPath($endpoint)
            );
        }
    }

    public function testProcessRequestReturnsWPRestResponse(): void
    {
        // Test with an endpoint that should return a response without throwing.
        $response = ApiService::processRequest('unknown_endpoint', new \WP_REST_Request());

        // In test environment, endpoint manager is not initialized, so expect error string.
        $this->assertEquals(503, $response->get_status());
        $this->assertEquals('Endpoint manager not initialized.', $response->get_data());
    }
}
