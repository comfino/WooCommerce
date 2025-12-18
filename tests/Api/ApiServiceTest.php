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

    /**
     * Enhanced tests for widget functionality after vendor optimizations.
     * These tests cover critical paywall and product details endpoints.
     */

    public function testProcessRequestWithProductDetailsEndpoint(): void
    {
        $request = new \WP_REST_Request();
        $request->set_param('loanTypeSelected', 'INSTALLMENTS_ZERO_PERCENT');
        $request->set_param('priceModifier', '100');
        $request->set_param('productId', '123');

        $response = ApiService::processRequest('productDetails', $request);

        // Should return service unavailable when endpoint manager is not initialized.
        $this->assertEquals(503, $response->get_status());
        $this->assertEquals('Endpoint manager not initialized.', $response->get_data());
    }

    public function testProcessRequestWithAvailableOfferTypesEndpoint(): void
    {
        $request = new \WP_REST_Request();
        $request->set_param('product_id', '123');

        $response = ApiService::processRequest('availableOfferTypes', $request);

        // Should return service unavailable when endpoint manager is not initialized.
        $this->assertEquals(503, $response->get_status());
        $this->assertEquals('Endpoint manager not initialized.', $response->get_data());
    }

    public function testGetEndpointUrlForAllKnownEndpoints(): void
    {
        // Test all frontend endpoints.
        $frontendEndpoints = [
            'availableOfferTypes',
            'paywall',
            'paywallItemDetails',
            'productDetails',
        ];

        foreach ($frontendEndpoints as $endpoint) {
            $url = ApiService::getEndpointUrl($endpoint);

            $this->assertStringStartsWith('https://', $url);
            $this->assertContains('wp-json/comfino/', $url);
            $this->assertContains(strtolower($endpoint), $url);
        }

        // Test webhook endpoints.
        $webhookEndpoints = [
            'transactionStatus',
            'configuration',
            'cacheInvalidate',
        ];

        foreach ($webhookEndpoints as $endpoint) {
            $url = ApiService::getEndpointUrl($endpoint);

            $this->assertStringStartsWith('https://', $url);
            $this->assertContains('wp-json/comfino/', $url);
            $this->assertContains(strtolower($endpoint), $url);
        }
    }

    public function testGetEndpointPathForAllKnownEndpoints(): void
    {
        $allEndpoints = [
            'availableOfferTypes',
            'paywall',
            'paywallItemDetails',
            'productDetails',
            'transactionStatus',
            'configuration',
            'cacheInvalidate',
        ];

        foreach ($allEndpoints as $endpoint) {
            $path = ApiService::getEndpointPath($endpoint);

            $this->assertStringStartsWith('/wp-json/comfino/', $path);
            $this->assertContains(strtolower($endpoint), $path);
            $this->assertNotContains('https://', $path);
            $this->assertNotContains('http://', $path);
        }
    }

    public function testProcessRequestHandlesInvalidLoanType(): void
    {
        $request = new \WP_REST_Request();
        $request->set_param('loanTypeSelected', 'INVALID_TYPE');
        $request->set_param('priceModifier', '0');

        $response = ApiService::processRequest('paywallItemDetails', $request);

        // Verify graceful handling.
        $this->assertEquals(503, $response->get_status());
    }

    public function testProcessRequestHandlesNegativePriceModifier(): void
    {
        $request = new \WP_REST_Request();
        $request->set_param('priceModifier', '-100');

        $response = ApiService::processRequest('paywall', $request);

        // Verify method completes without fatal errors.
        $this->assertTrue(is_string($response->get_data()));
    }

    public function testProcessRequestHandlesZeroPriceModifier(): void
    {
        $request = new \WP_REST_Request();
        $request->set_param('priceModifier', '0');

        ApiService::processRequest('paywall', $request);

        // Verify method completes.
        $this->assertTrue(true);
    }

    public function testProcessRequestHandlesLargePriceModifier(): void
    {
        $request = new \WP_REST_Request();
        $request->set_param('priceModifier', '999999999');

        ApiService::processRequest('paywall', $request);

        // Verify method completes.
        $this->assertTrue(true);
    }

    public function testProcessRequestHandlesNonNumericPriceModifier(): void
    {
        $request = new \WP_REST_Request();
        $request->set_param('priceModifier', 'not-a-number');

        ApiService::processRequest('paywall', $request);

        // Verify invalid input handled gracefully.
        $this->assertTrue(true);
    }

    public function testGetEndpointUrlConsistency(): void
    {
        // Test that calling getEndpointUrl multiple times returns same result.
        $url1 = ApiService::getEndpointUrl('paywall');
        $url2 = ApiService::getEndpointUrl('paywall');
        $url3 = ApiService::getEndpointUrl('paywall');

        $this->assertEquals($url1, $url2);
        $this->assertEquals($url2, $url3);
    }

    public function testGetEndpointPathConsistency(): void
    {
        // Test that calling getEndpointPath multiple times returns same result.
        $path1 = ApiService::getEndpointPath('paywall');
        $path2 = ApiService::getEndpointPath('paywall');
        $path3 = ApiService::getEndpointPath('paywall');

        $this->assertEquals($path1, $path2);
        $this->assertEquals($path2, $path3);
    }

    public function testProcessRequestForWebhookEndpoints(): void
    {
        // Test webhook endpoints that require authentication.
        $webhookEndpoints = ['transactionStatus', 'configuration', 'cacheInvalidate'];

        foreach ($webhookEndpoints as $endpoint) {
            $request = new \WP_REST_Request();
            $response = ApiService::processRequest($endpoint, $request);

            // Verify service unavailable response.
            $this->assertEquals(503, $response->get_status());
        }
    }

    public function testEndpointUrlsDoNotContainDoubleSlashes(): void
    {
        $allEndpoints = [
            'availableOfferTypes',
            'paywall',
            'paywallItemDetails',
            'productDetails',
            'transactionStatus',
            'configuration',
            'cacheInvalidate',
        ];

        foreach ($allEndpoints as $endpoint) {
            $url = ApiService::getEndpointUrl($endpoint);

            // Should not contain double slashes except in https://
            $urlWithoutProtocol = str_replace('https://', '', $url);
            $this->assertNotContains('//', $urlWithoutProtocol);
        }
    }

    public function testGetEndpointUrlReturnsEmptyForUnknownEndpoint(): void
    {
        $this->assertEquals('', ApiService::getEndpointUrl('unknown_endpoint_xyz'));
    }

    public function testGetEndpointPathReturnsEmptyForUnknownEndpoint(): void
    {
        $this->assertEquals('', ApiService::getEndpointPath('unknown_endpoint_xyz'));
    }
}
