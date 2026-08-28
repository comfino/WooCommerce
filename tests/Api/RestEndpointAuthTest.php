<?php

namespace Comfino\Tests\Api;

use Comfino\Api\ApiService;
use Comfino\Common\Backend\Factory\ApiServiceFactory;
use Comfino\Common\Backend\RestEndpointManager;
use Comfino\PaymentGateway;
use ComfinoExternal\Sunrise\Http\Factory\ServerRequestFactory;
use ComfinoExternal\Sunrise\Http\Factory\StreamFactory;

/**
 * Regression coverage for the REST endpoint signature check.
 *
 * A CR-Signature is calculated as sha3-256(apiKey . requestBody). When an API key slot is unconfigured its
 * value is null or an empty string, so the signature collapses to sha3-256(requestBody) - a value any caller
 * can compute without knowing a secret. These tests assert that such a signature is refused while a real
 * production key is configured.
 */
class RestEndpointAuthTest extends \PHPUnit_Framework_TestCase
{
    const PRODUCTION_API_KEY = 'PRODUCTION_KEY_1234567890';
    const SANDBOX_API_KEY = 'SANDBOX_KEY_0987654321';
    const REQUEST_BODY = '{"COMFINO_DEBUG":true}';

    public function setUp(): void
    {
        parent::setUp();

        $this->resetEndpointManager();
    }

    public function tearDown(): void
    {
        $this->resetEndpointManager();

        parent::tearDown();
    }

    /**
     * @dataProvider unusableApiKeysProvider
     *
     * @param mixed $unusableApiKey
     */
    public function testApiKeyFilterDropsUnusableKeys($unusableApiKey, string $description): void
    {
        $filteredKeys = ApiService::filterApiKeys([self::PRODUCTION_API_KEY, $unusableApiKey]);

        $this->assertSame([self::PRODUCTION_API_KEY], $filteredKeys, $description);
    }

    public function unusableApiKeysProvider(): array
    {
        return [
            'unconfigured key slot (null)' => [null, 'A null key must never be used as a signature secret.'],
            'unconfigured key slot (empty string)' => ['', 'An empty key must never be used as a signature secret.'],
            'whitespace only' => ['                ', 'A blank key must never be used as a signature secret.'],
            'too short to be a real key' => ['SHORT_KEY', 'A key shorter than 16 characters cannot be a Comfino key.'],
            'wrong type' => [12345678901234567890, 'A non-string key must never be used as a signature secret.'],
        ];
    }

    public function testApiKeyFilterKeepsUsableKeys(): void
    {
        $this->assertSame(
            [self::PRODUCTION_API_KEY, self::SANDBOX_API_KEY],
            ApiService::filterApiKeys([self::PRODUCTION_API_KEY, self::SANDBOX_API_KEY])
        );
    }

    public function testSignatureCalculatedWithAnEmptyKeyIsRejected(): void
    {
        $manager = $this->createEndpointManager(
            ApiService::filterApiKeys([self::PRODUCTION_API_KEY, null])
        );

        $this->assertFalse(
            $this->requestIsAuthorized($manager, hash('sha3-256', '' . self::REQUEST_BODY)),
            'A signature calculated with an empty API key must not authorize a request.'
        );
    }

    public function testSignatureCalculatedWithTheProductionKeyIsAccepted(): void
    {
        $manager = $this->createEndpointManager(
            ApiService::filterApiKeys([self::PRODUCTION_API_KEY, null])
        );

        $this->assertTrue(
            $this->requestIsAuthorized($manager, hash('sha3-256', self::PRODUCTION_API_KEY . self::REQUEST_BODY)),
            'A correctly signed request must still be authorized.'
        );
    }

    public function testRequestIsRejectedWhenNoApiKeyIsConfigured(): void
    {
        $manager = $this->createEndpointManager(ApiService::filterApiKeys([null, '']));

        $this->assertFalse(
            $this->requestIsAuthorized($manager, hash('sha3-256', '' . self::REQUEST_BODY)),
            'A shop without any configured API key must not authorize any request.'
        );
    }

    /**
     * The test environment key is passed to the endpoint manager only while the shop runs in sandbox mode,
     * so a request signed with it must not be authorized on a production shop.
     */
    public function testSandboxKeyIsNotAcceptedOutsideSandboxMode(): void
    {
        $manager = $this->createEndpointManager(
            ApiService::filterApiKeys([self::PRODUCTION_API_KEY])
        );

        $this->assertFalse(
            $this->requestIsAuthorized($manager, hash('sha3-256', self::SANDBOX_API_KEY . self::REQUEST_BODY)),
            'The test environment key must not authorize requests while sandbox mode is disabled.'
        );
    }

    public function testSandboxKeyIsAcceptedInSandboxMode(): void
    {
        $manager = $this->createEndpointManager(
            ApiService::filterApiKeys([self::PRODUCTION_API_KEY, self::SANDBOX_API_KEY])
        );

        $this->assertTrue(
            $this->requestIsAuthorized($manager, hash('sha3-256', self::SANDBOX_API_KEY . self::REQUEST_BODY)),
            'The test environment key must authorize requests while sandbox mode is enabled.'
        );
    }

    /**
     * @param string[] $apiKeys
     */
    private function createEndpointManager(array $apiKeys): RestEndpointManager
    {
        return (new ApiServiceFactory())->createService(
            'WooCommerce',
            WC_VERSION,
            PaymentGateway::VERSION,
            $apiKeys
        );
    }

    /**
     * @param string $crSignature
     */
    private function requestIsAuthorized(RestEndpointManager $manager, $crSignature): bool
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', 'https://shop.example/wp-json/comfino/configuration')
            ->withHeader('CR-Signature', $crSignature)
            ->withBody((new StreamFactory())->createStream(self::REQUEST_BODY));

        $verifyRequest = new \ReflectionMethod(RestEndpointManager::class, 'verifyRequest');
        $verifyRequest->setAccessible(true);

        try {
            $verifyRequest->invoke($manager, $request);
        } catch (\Throwable $exception) {
            return false;
        }

        return true;
    }

    /**
     * The endpoint manager is a singleton which ignores the arguments of every call after the first one,
     * so it has to be discarded between test cases.
     */
    private function resetEndpointManager(): void
    {
        $instance = new \ReflectionProperty(RestEndpointManager::class, 'instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
    }
}
