<?php

namespace Comfino\Tests\Configuration;

use Comfino\Configuration\ConfigManager;
use Comfino\Extended\Api\Response\ClaimErrorLoggingToken as ClaimErrorLoggingTokenResponse;
use Comfino\Main;
use Comfino\PluginShared\CacheManager;
use Comfino\Tests\Stub\StubApiClient;
use Comfino\Tests\TestState;

/**
 * The error-logging access token claim must not run on every request: not after a failed claim (the shared API client
 * reports one as a null response) and not after a token that is already due for a refresh again.
 */
class ErrorLoggingTokenRefreshTest extends \PHPUnit_Framework_TestCase
{
    const COOLDOWN_KEY = 'error_logging_token_claim_cooldown';

    /** @var StubApiClient */
    private $apiClient;

    public function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REQUEST_SCHEME'] = 'https';
        $_SERVER['HTTP_HOST'] = 'comfino-wc-store.test';

        Main::setPluginDirectory(__DIR__ . '/../..');
        Main::setPluginFile(__DIR__ . '/../../comfino-payment-gateway.php');

        TestState::reset();

        $this->apiClient = new StubApiClient();
        TestState::setApiClient($this->apiClient);

        ConfigManager::getInstance()->setConfigurationValue('COMFINO_API_KEY', 'TEST_API_KEY_0123456789');
    }

    public function tearDown(): void
    {
        TestState::reset();

        parent::tearDown();
    }

    public function testNoApiKeyMakesNoClaim(): void
    {
        ConfigManager::getInstance()->setConfigurationValue('COMFINO_API_KEY', '');

        ConfigManager::refreshErrorLoggingTokenIfNeeded();

        $this->assertSame(0, $this->apiClient->getCallCount('claimErrorLoggingToken'));
    }

    public function testFailedClaimReportedAsNullStartsACooldown(): void
    {
        $this->apiClient->claimTokenResult = null;

        ConfigManager::refreshErrorLoggingTokenIfNeeded();
        ConfigManager::refreshErrorLoggingTokenIfNeeded();
        ConfigManager::refreshErrorLoggingTokenIfNeeded();

        $this->assertSame(1, $this->apiClient->getCallCount('claimErrorLoggingToken'), 'Only the first request may call the API during the cooldown.');
        $this->assertSame('', ConfigManager::getErrorLoggingAccessToken());
        $this->assertNotNull(CacheManager::get(self::COOLDOWN_KEY));
    }

    public function testThrownClaimErrorStartsACooldown(): void
    {
        $this->apiClient->claimTokenResult = new \RuntimeException('Connection refused.');

        ConfigManager::refreshErrorLoggingTokenIfNeeded();
        ConfigManager::refreshErrorLoggingTokenIfNeeded();

        $this->assertSame(1, $this->apiClient->getCallCount('claimErrorLoggingToken'));
    }

    public function testUnparsableExpiryIsAFailureAndStoresNothing(): void
    {
        $this->apiClient->claimTokenResult = $this->tokenResponse('new-token', 'not a date');

        ConfigManager::refreshErrorLoggingTokenIfNeeded();
        ConfigManager::refreshErrorLoggingTokenIfNeeded();

        $this->assertSame(1, $this->apiClient->getCallCount('claimErrorLoggingToken'));
        $this->assertSame('', ConfigManager::getErrorLoggingAccessToken());
    }

    public function testHealthyTokenIsStoredWithoutACooldownAndNotClaimedAgain(): void
    {
        $this->apiClient->claimTokenResult = $this->tokenResponse('long-token', gmdate('c', time() + 86400));

        ConfigManager::refreshErrorLoggingTokenIfNeeded();
        ConfigManager::refreshErrorLoggingTokenIfNeeded();

        $this->assertSame(1, $this->apiClient->getCallCount('claimErrorLoggingToken'), 'A valid token is not refreshed.');
        $this->assertSame('long-token', ConfigManager::getErrorLoggingAccessToken());
        $this->assertNull(CacheManager::get(self::COOLDOWN_KEY));
    }

    public function testShortLivedTokenStartsACooldownInsteadOfBeingClaimedOnEveryRequest(): void
    {
        $this->apiClient->claimTokenResult = $this->tokenResponse('short-token', gmdate('c', time() + 1800));

        ConfigManager::refreshErrorLoggingTokenIfNeeded();
        ConfigManager::refreshErrorLoggingTokenIfNeeded();
        ConfigManager::refreshErrorLoggingTokenIfNeeded();

        $this->assertSame(1, $this->apiClient->getCallCount('claimErrorLoggingToken'));
        $this->assertSame('short-token', ConfigManager::getErrorLoggingAccessToken());
        $this->assertNotNull(CacheManager::get(self::COOLDOWN_KEY));
    }

    private function tokenResponse(string $accessToken, string $expiresAt): ClaimErrorLoggingTokenResponse
    {
        $response = (new \ReflectionClass(ClaimErrorLoggingTokenResponse::class))->newInstanceWithoutConstructor();
        $response->accessToken = $accessToken;
        $response->expiresAt = $expiresAt;

        $headers = new \ReflectionProperty(\Comfino\Api\Response::class, 'headers');
        $headers->setAccessible(true);
        $headers->setValue($response, []);

        return $response;
    }
}
