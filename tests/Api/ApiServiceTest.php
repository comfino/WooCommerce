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
        $url = ApiService::getEndpointUrl('availableOfferTypes');

        $this->assertEquals('https://comfino-wc-store.test/wp-json/comfino/availableoffertypes', $url);

        // Test getting URL for unknown endpoint.
        $this->assertEquals('', ApiService::getEndpointUrl('unknown_endpoint'));
    }

    public function testGetEndpointPath(): void
    {
        $this->assertEquals('/wp-json/comfino/availableoffertypes', ApiService::getEndpointPath('availableOfferTypes'));
    }

    public function testProcessRequestWithUnknownEndpoint(): void
    {
        $response = ApiService::processRequest('unknown_endpoint', new \WP_REST_Request());

        // Should return service unavailable when endpoint manager is not initialized.
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
        $endpoints = ['availableOfferTypes', 'configuration', 'transactionStatus'];

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
        // Test frontend endpoints.
        $frontendEndpoints = ['availableOfferTypes'];

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

    public function testGetEndpointUrlConsistency(): void
    {
        // Test that calling getEndpointUrl multiple times returns same result.
        $url1 = ApiService::getEndpointUrl('availableOfferTypes');
        $url2 = ApiService::getEndpointUrl('availableOfferTypes');
        $url3 = ApiService::getEndpointUrl('availableOfferTypes');

        $this->assertEquals($url1, $url2);
        $this->assertEquals($url2, $url3);
    }

    public function testGetEndpointPathConsistency(): void
    {
        // Test that calling getEndpointPath multiple times returns same result.
        $path1 = ApiService::getEndpointPath('availableOfferTypes');
        $path2 = ApiService::getEndpointPath('availableOfferTypes');
        $path3 = ApiService::getEndpointPath('availableOfferTypes');

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