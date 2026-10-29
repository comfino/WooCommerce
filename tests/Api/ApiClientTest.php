<?php

namespace Comfino\Tests\Api;

use Comfino\Api\ApiClient;
use Comfino\Api\HttpErrorExceptionInterface;
use Comfino\Common\Backend\ConfigurationManager;
use Comfino\Common\Backend\Factory\ApiClientFactory;
use Comfino\Configuration\ConfigManager;
use Comfino\Main;
use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\Conflict;
use Comfino\Api\Exception\TooManyRequests;
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

    public function tearDown(): void
    {
        $this->resetTrackIdPinning();

        parent::tearDown();
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

    public function testProcessApiErrorSummarizesHtmlResponseBodyInDiagnostics(): void
    {
        $httpException = new class('Internal Server Error', 500) extends \Exception implements HttpErrorExceptionInterface {
            public function getStatusCode(): int { return $this->getCode(); }
            public function getUrl(): string { return 'https://api.comfino.test/test'; }
            public function getRequestBody(): string { return '{"test": "request"}'; }
            public function setRequestBody($requestBody): void {}
            public function getResponseBody(): string
            {
                return '<!DOCTYPE html><html><head><style>.a { color: red; }</style></head>'
                    . '<body><h1>Unknown column</h1></body></html>';
            }
            public function setResponseBody($responseBody): void {}
        };

        putenv('COMFINO_DEV_ENV=TRUE');

        try {
            ConfigManager::updateConfigurationValue('COMFINO_DEV_ENV_VARS', true);

            $result = ApiClient::processApiError('HTTP 500 Error', $httpException);
        } finally {
            putenv('COMFINO_DEV_ENV');
        }

        $this->assertArrayHasKey('responseBody', $result['error_details']);
        $this->assertSame('[HTML response] Unknown column', $result['error_details']['responseBody']);
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

    public function testProcessApiErrorShowsOutageMessageForRateLimit(): void
    {
        // A 429 is a temporary outage for the customer, not a "configuration problem".
        $result = ApiClient::processApiError('HTTP 429 Error', new TooManyRequests('Too Many Requests', 429));

        $this->assertContains('outage', $result['title']);
    }

    public function testProcessApiErrorWithIdempotentConflictReturnsResult(): void
    {
        // A 409 on an already cancelled order is an idempotent failure: handled like a 404, not reported.
        $conflict = new Conflict('Conflict', 409);
        $conflict->setIdempotentFailure(true);

        $result = ApiClient::processApiError('Order cancellation error', $conflict);

        $this->assertArrayHasKey('title', $result);
        $this->assertTrue($conflict->isIdempotentFailure());
    }

    public function testPinCheckoutTrackIdPrefersJourneyCookieWhenTrackingConsentEnabled(): void
    {
        $this->prepareTrackIdPinning(true, 'journey-track-id.1', 'checkout-track-id.1');

        ApiClient::pinCheckoutTrackId();

        $this->assertEquals('journey-track-id.1', ApiClient::getInstance()->getTrackId());
    }

    public function testPinCheckoutTrackIdIgnoresJourneyCookieWhenTrackingConsentDisabled(): void
    {
        $this->prepareTrackIdPinning(false, 'journey-track-id.1', 'checkout-track-id.1');

        ApiClient::pinCheckoutTrackId();

        $this->assertEquals('checkout-track-id.1', ApiClient::getInstance()->getTrackId());
    }

    public function testPinCheckoutTrackIdFallsBackToCheckoutCookieOnInvalidJourneyCookie(): void
    {
        $this->prepareTrackIdPinning(true, "bad\r\nvalue", 'checkout-track-id.1');

        ApiClient::pinCheckoutTrackId();

        $this->assertEquals('checkout-track-id.1', ApiClient::getInstance()->getTrackId());
    }

    /**
     * Swaps in a fresh client (a pinned trackId can't be overwritten) and sets the consent option and cookies.
     */
    private function prepareTrackIdPinning(bool $trackingConsent, ?string $journeyTrackId, ?string $checkoutTrackId): void
    {
        $this->resetTrackIdPinning();

        $clientProp = new \ReflectionProperty(ApiClient::class, 'apiClient');
        $clientProp->setAccessible(true);
        $clientProp->setValue(null, (new ApiClientFactory())->createClient('TEST_API_KEY', 'test'));

        ConfigManager::getInstance()->setConfigurationValue('COMFINO_PAYWALL_TRACKING_CONSENT', $trackingConsent);

        if ($journeyTrackId !== null) {
            $_COOKIE['comfino_journey_track_id'] = $journeyTrackId;
        }

        if ($checkoutTrackId !== null) {
            $_COOKIE['comfino_checkout_track_id'] = $checkoutTrackId;
        }
    }

    private function resetTrackIdPinning(): void
    {
        unset($_COOKIE['comfino_journey_track_id'], $_COOKIE['comfino_checkout_track_id']);

        $staticProps = [
            [ApiClient::class, 'apiClient', null],
            [ConfigManager::class, 'configurationManager', null],
            [ConfigurationManager::class, 'instances', []],
        ];

        foreach ($staticProps as [$class, $property, $value]) {
            $prop = new \ReflectionProperty($class, $property);
            $prop->setAccessible(true);
            $prop->setValue(null, $value);
        }
    }
}
