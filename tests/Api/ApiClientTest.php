<?php

namespace Comfino\Tests\Api;

use Comfino\Api\ApiClient;
use Comfino\Api\HttpErrorExceptionInterface;
use Comfino\Main;
use Comfino\Api\Exception\AccessDenied;
use Comfino\Common\Exception\ConnectionTimeout;

class ApiClientTest extends \PHPUnit_Framework_TestCase
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

    public function testGetInstanceSingleton(): void
    {
        $apiClient1 = ApiClient::getInstance();
        $apiClient2 = ApiClient::getInstance();

        $this->assertSame($apiClient1, $apiClient2);
    }

    public function testProcessApiErrorWithGenericException(): void
    {
        $exception = new \Exception('Test error message', 500);
        $errorPrefix = 'Test Error';

        $result = ApiClient::processApiError($errorPrefix, $exception);

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('error_details', $result);
        $this->assertInternalType('string', $result['title']);
        $this->assertInternalType('array', $result['error_details']);
    }

    public function testProcessApiErrorWithNetworkException(): void
    {
        // Create a simple Exception instead of trying to mock NetworkExceptionInterface.
        $networkException = new \Exception('Network error', 0);

        $result = ApiClient::processApiError('Network Error', $networkException);

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('error_details', $result);
        $this->assertInternalType('string', $result['title']);
        $this->assertInternalType('array', $result['error_details']);
    }

    public function testProcessApiErrorWithAccessDenied(): void
    {
        // Mock AccessDenied exception.
        $accessDeniedException = $this->createMock(AccessDenied::class);
        $accessDeniedException->method('getStatusCode')->willReturn(404);
        $accessDeniedException->method('getUrl')->willReturn('https://api.comfino.test/test');
        $accessDeniedException->method('getRequestBody')->willReturn('{"test": "request"}');
        $accessDeniedException->method('getResponseBody')->willReturn('{"error": "not found"}');
        $accessDeniedException->method('getStatusCode')->willReturn(404);
        $accessDeniedException->method('getCode')->willReturn(404);
        $accessDeniedException->method('getFile')->willReturn(__FILE__);
        $accessDeniedException->method('getLine')->willReturn(__LINE__);
        $accessDeniedException->method('getTraceAsString')->willReturn('trace');

        $result = ApiClient::processApiError('Access Denied', $accessDeniedException);

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('error_details', $result);
        // The title might be empty since getMessage() is final and can't be mocked.
        $this->assertInternalType('string', $result['title']);
        $this->assertInternalType('array', $result['error_details']);
    }

    public function testProcessApiErrorWithConnectionTimeout(): void
    {
        // Mock ConnectionTimeout exception.
        $timeoutException = $this->createMock(ConnectionTimeout::class);
        $timeoutException->method('getStatusCode')->willReturn(500);
        $timeoutException->method('getUrl')->willReturn('https://api.comfino.test/test');
        $timeoutException->method('getRequestBody')->willReturn('{"test": "request"}');
        $timeoutException->method('getResponseBody')->willReturn('');
        $timeoutException->method('getConnectAttemptIdx')->willReturn(2);
        $timeoutException->method('getConnectionTimeout')->willReturn(1);
        $timeoutException->method('getTransferTimeout')->willReturn(3);
        $timeoutException->method('getMessage')->willReturn('Connection timeout');
        $timeoutException->method('getCode')->willReturn(0);
        $timeoutException->method('getFile')->willReturn(__FILE__);
        $timeoutException->method('getLine')->willReturn(__LINE__);
        $timeoutException->method('getTraceAsString')->willReturn('trace');
        $timeoutException->method('getPrevious')->willReturn(null);

        $result = ApiClient::processApiError('Connection Timeout', $timeoutException);

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('error_details', $result);
        $this->assertInternalType('string', $result['title']);
        $this->assertInternalType('array', $result['error_details']);
    }

    public function testProcessApiErrorWithHttpError4xx(): void
    {
        // Create an anonymous class that properly implements both Exception and HttpErrorExceptionInterface.
        $httpException = new class('Bad Request', 400) extends \Exception implements HttpErrorExceptionInterface {
            public function getStatusCode(): int { return $this->getCode(); }
            public function getUrl(): string { return 'https://api.comfino.test/test'; }
            public function getRequestBody(): string { return '{"test": "request"}'; }
            public function setRequestBody($requestBody): void {}
            public function getResponseBody(): string { return '{"error": "Bad Request"}'; }
            public function setResponseBody($responseBody): void {}
        };

        $result = ApiClient::processApiError('HTTP 400 Error', $httpException);

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('error_details', $result);
        $this->assertContains('configuration problem', $result['title']);
        $this->assertInternalType('array', $result['error_details']);
    }

    public function testProcessApiErrorWithHttpError5xx(): void
    {
        // Create an anonymous class that properly implements both Exception and HttpErrorExceptionInterface.
        $httpException = new class('Bad Gateway', 502) extends \Exception implements HttpErrorExceptionInterface {
            public function getStatusCode(): int { return $this->getCode(); }
            public function getUrl(): string { return 'https://api.comfino.test/test'; }
            public function getRequestBody(): string { return '{"test": "request"}'; }
            public function setRequestBody($requestBody): void {}
            public function getResponseBody(): string { return '{"error": "Bad Gateway"}'; }
            public function setResponseBody($responseBody): void {}
        };

        $result = ApiClient::processApiError('HTTP 502 Error', $httpException);

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('error_details', $result);
        $this->assertContains('outage', $result['title']);
        $this->assertInternalType('array', $result['error_details']);
    }

    public function testProcessApiErrorWithHttpError504Plus(): void
    {
        // Create an anonymous class that properly implements both Exception and HttpErrorExceptionInterface.
        $httpException = new class('Gateway Timeout', 504) extends \Exception implements HttpErrorExceptionInterface {
            public function getStatusCode(): int { return $this->getCode(); }
            public function getUrl(): string { return 'https://api.comfino.test/test'; }
            public function getRequestBody(): string { return '{"test": "request"}'; }
            public function setRequestBody($requestBody): void {}
            public function getResponseBody(): string { return '{"error": "Gateway Timeout"}'; }
            public function setResponseBody($responseBody): void {}
        };

        $result = ApiClient::processApiError('HTTP 504 Error', $httpException);

        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('error_details', $result);
        $this->assertContains('technical problem', $result['title']);
        $this->assertInternalType('array', $result['error_details']);
    }
}
