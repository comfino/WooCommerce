<?php

namespace Comfino\Tests\Api;

use Comfino\Api\ApiClient;
use Comfino\Configuration\ConfigManager;
use Comfino\Main;
use Comfino\Tests\TestState;

/**
 * Remotely writable transport options are clamped on read; storefront renders make a single attempt. Plain-HTTP API
 * hosts are allowed only in the local development environment.
 */
class ApiClientTransportTest extends \PHPUnit_Framework_TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/checkout/';
        $_SERVER['REQUEST_SCHEME'] = 'https';
        $_SERVER['HTTP_HOST'] = 'comfino-wc-store.test';

        Main::setPluginDirectory(__DIR__ . '/../..');
        Main::setPluginFile(__DIR__ . '/../../comfino-payment-gateway.php');

        TestState::reset();
    }

    public function tearDown(): void
    {
        putenv('COMFINO_DEV_ENV=TRUE');
        putenv('COMFINO_DEV_API_HOST');
        unset($_GET['wc-ajax']);
        TestState::reset();

        parent::tearDown();
    }

    /**
     * @dataProvider transportProvider
     */
    public function testTransportValuesAreClamped($connect, $transfer, $attempts, array $expected): void
    {
        $config = ConfigManager::getInstance();
        $config->setConfigurationValue('COMFINO_API_CONNECT_TIMEOUT', $connect);
        $config->setConfigurationValue('COMFINO_API_TIMEOUT', $transfer);
        $config->setConfigurationValue('COMFINO_API_CONNECT_NUM_ATTEMPTS', $attempts);

        $this->assertSame($expected, ApiClient::getTransportSettings(false));
    }

    public function transportProvider(): array
    {
        return [
            'defaults' => [1, 3, 3, [1, 3, 3]],
            'zero' => [0, 0, 0, [1, 2, 1]],
            'negative' => [-1, -1, -1, [1, 2, 1]],
            'huge' => [99999, 99999, 99999, [5, 15, 3]],
            'transfer below connect' => [5, 2, 2, [5, 6, 2]],
        ];
    }

    public function testStorefrontRenderMakesSingleAttempt(): void
    {
        ConfigManager::getInstance()->setConfigurationValue('COMFINO_API_CONNECT_NUM_ATTEMPTS', 3);

        $this->assertSame(1, ApiClient::getTransportSettings()[2], 'Checkout page render.');

        $_GET['wc-ajax'] = 'checkout';

        $this->assertSame(3, ApiClient::getTransportSettings()[2], 'Order submission keeps retries.');
    }

    public function testDevEnvCurlOptionsAllowPlainHttpOnlyInDevEnvironment(): void
    {
        $method = new \ReflectionMethod(ApiClient::class, 'getDevEnvCurlOptions');
        $method->setAccessible(true);

        putenv('COMFINO_DEV_API_HOST=http://ecommerce');
        ConfigManager::getInstance()->setConfigurationValue('COMFINO_DEV_ENV_VARS', true);

        $options = $method->invoke(null);

        $this->assertSame(CURLPROTO_HTTP | CURLPROTO_HTTPS, $options[CURLOPT_PROTOCOLS]);

        putenv('COMFINO_DEV_API_HOST=https://ecommerce');
        $this->assertSame([], $method->invoke(null), 'An https:// host keeps HTTPS-only.');

        putenv('COMFINO_DEV_API_HOST=http://ecommerce');
        ConfigManager::getInstance()->setConfigurationValue('COMFINO_DEV_ENV_VARS', false);
        $this->assertSame([], $method->invoke(null), 'Dev env vars option off.');

        ConfigManager::getInstance()->setConfigurationValue('COMFINO_DEV_ENV_VARS', true);
        putenv('COMFINO_DEV_ENV');
        $this->assertSame([], $method->invoke(null), 'Missing COMFINO_DEV_ENV environment variable.');
    }
}
