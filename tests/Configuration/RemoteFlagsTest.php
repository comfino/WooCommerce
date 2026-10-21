<?php

namespace Comfino\Tests\Configuration;

use Comfino\Common\Backend\Cache\ItemTypeEnum;
use Comfino\Configuration\ConfigManager;
use Comfino\Main;
use Comfino\PluginShared\CacheManager;
use Comfino\Tests\Stub\StubApiClient;
use Comfino\Tests\TestState;

/**
 * Remote flags pipeline: flag name list, cached flag attributes (tag "remote_flags"), sanitizer, checkout projection,
 * the retired COMFINO_REMOTE_FLAG_ATTRIBUTES option and the checkout config encoding.
 */
class RemoteFlagsTest extends \PHPUnit_Framework_TestCase
{
    const PROMO = ['amount' => 10000, 'minAmount' => 100000, 'maxAmount' => 500000, 'allowedTypes' => ['PAY_LATER']];

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

    public function testCacheMissFetchesSanitizesAndCaches(): void
    {
        $this->apiClient->userSettingsFlags = ['PROMO' => self::PROMO, 'bad-name' => ['a' => 1]];

        $this->assertSame(['PROMO' => self::PROMO], ConfigManager::getRemoteFlagAttributes());
        $this->assertSame(1, $this->apiClient->getCallCount('getUserSettings'));

        ConfigManager::getRemoteFlagAttributes();

        $this->assertSame(1, $this->apiClient->getCallCount('getUserSettings'), 'Cache hit must not call the API.');
    }

    public function testInvalidationByTagRefetches(): void
    {
        $this->apiClient->userSettingsFlags = ['PROMO' => self::PROMO];
        ConfigManager::getRemoteFlagAttributes();

        $this->apiClient->userSettingsFlags = ['PROMO' => ['amount' => 20000]];
        CacheManager::getCachePool()->invalidateTags([ItemTypeEnum::REMOTE_FLAGS]);

        $this->assertSame(['PROMO' => ['amount' => 20000]], ConfigManager::getRemoteFlagAttributes());
        $this->assertSame(2, $this->apiClient->getCallCount('getUserSettings'));
    }

    public function testVendoredLibraryKnowsRemoteFlagsItemType(): void
    {
        // Without the enum value, the shared CacheInvalidate endpoint silently drops the backend's "remote_flags" item.
        $this->assertContains('remote_flags', ItemTypeEnum::values());
    }

    public function testTtlIsCappedAndShortenedByHeader(): void
    {
        $this->assertSame(86400, $this->cachedTtlFor(null));
        $this->assertSame(86400, $this->cachedTtlFor('0'));
        $this->assertSame(86400, $this->cachedTtlFor('172800'));
        $this->assertEquals(3600, $this->cachedTtlFor('3600'), '', 5);
    }

    public function testFailureIsCachedAndDoesNotEscape(): void
    {
        $this->apiClient->userSettingsFlags = new \RuntimeException('down');

        $this->assertSame([], ConfigManager::getRemoteFlagAttributes());
        $this->assertSame([], ConfigManager::getRemoteFlagAttributes());
        $this->assertSame(1, $this->apiClient->getCallCount('getUserSettings'), 'Failure must be cached, not retried on every call.');
        $this->assertEquals(300, $this->cachedTtl(), '', 5);
    }

    public function testNoApiKeyMakesNoCall(): void
    {
        ConfigManager::getInstance()->setConfigurationValue('COMFINO_API_KEY', '');

        $this->assertSame([], ConfigManager::getRemoteFlagAttributes());
        $this->assertSame(0, $this->apiClient->getCallCount('getUserSettings'));
        $this->assertNull(CacheManager::get('remote_flag_attributes'));
    }

    public function testEmptyNameListIsSeededFromBodyWithoutPromo(): void
    {
        $this->apiClient->userSettingsFlags = ['PROMO' => self::PROMO, 'PAYMENT_ITEM_PROMOTION' => [], 'OTHER_FLAG' => []];

        ConfigManager::getRemoteFlagAttributes();

        $this->assertSame(['PAYMENT_ITEM_PROMOTION', 'OTHER_FLAG'], ConfigManager::getRemoteFlags());
    }

    public function testSanitizerKeepsValuesUnchanged(): void
    {
        $odd = ['amount' => '5', 'minAmount' => -1, 'allowedTypes' => ['PAY_LATER', '<script>'], 'renamed' => ['y' => null]];
        $sanitized = ConfigManager::sanitizeRemoteFlagAttributes([
            'PROMO' => $odd,
            'PROMO_WITHOUT_AMOUNT' => ['amount' => 0],
            'EMPTY_HOLDER' => [],
            'SCALAR_FLAG' => 'text',
            str_repeat('A', 65) => [],
            'mixedCase_1' => ['x' => 1],
            'NESTED_CASE' => ['camelCase' => 1, 'snake_case' => ['Mixed_Key' => true]],
            'bad-name' => ['x' => 1],
            'BROKEN_UTF8' => ['x' => "\xB1\x31"],
            0 => ['x' => 1],
        ]);

        $this->assertSame(
            [
                'PROMO' => $odd,
                'PROMO_WITHOUT_AMOUNT' => ['amount' => 0],
                'EMPTY_HOLDER' => [],
                'SCALAR_FLAG' => 'text',
                'NESTED_CASE' => ['camelCase' => 1, 'snake_case' => ['Mixed_Key' => true]],
            ],
            $sanitized
        );
        $this->assertSame([], ConfigManager::sanitizeRemoteFlagAttributes('not-an-array'));
    }

    public function testCheckoutProjectionForwardsBodyVerbatim(): void
    {
        $this->apiClient->userSettingsFlags = [
            'PROMO' => ['someNewField' => ['nested' => true]],
            'PAYMENT_ITEM_PROMOTION' => ['text' => ['pl' => 'Promocja']],
        ];
        ConfigManager::getInstance()->setConfigurationValue('COMFINO_REMOTE_FLAGS', ['PAYMENT_ITEM_PROMOTION']);

        $this->assertSame($this->apiClient->userSettingsFlags, ConfigManager::getCheckoutFlagAttributes());
    }

    public function testTokenClaimNoLongerWritesFlagAttributes(): void
    {
        $this->assertArrayNotHasKey('COMFINO_REMOTE_FLAG_ATTRIBUTES', ConfigManager::CONFIG_OPTIONS_MAP);
        $this->assertArrayNotHasKey('COMFINO_REMOTE_FLAG_ATTRIBUTES', ConfigManager::CONFIG_OPTIONS['hidden_settings']);
        $this->assertNotContains('COMFINO_REMOTE_FLAG_ATTRIBUTES', ConfigManager::ACCESSIBLE_CONFIG_OPTIONS);
        $this->assertArrayNotHasKey('COMFINO_REMOTE_FLAG_ATTRIBUTES', ConfigManager::getDefaultConfigurationValues());

        $source = file_get_contents(dirname(__DIR__, 2) . '/src/Configuration/ConfigManager.php');

        $this->assertNotContains('COMFINO_REMOTE_FLAG_ATTRIBUTES', $source);
    }

    public function testUpgradeRemovesStoredFlagAttributesOption(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/comfino-payment-gateway.php');

        $this->assertContains("unset(\$comfinoSettings['remote_flag_attributes']);", $source);
    }

    public function testRemotelyWritableOptionsAreThe44KeptOptions(): void
    {
        $expected = [
            'COMFINO_ENABLED', 'COMFINO_PAYMENT_TEXT_ENABLED', 'COMFINO_PAYMENT_TEXT', 'COMFINO_CHECKOUT_PRODUCT_TYPES',
            'COMFINO_CHECKOUT_PRODUCT_TYPES_ORDER', 'COMFINO_MINIMAL_CART_AMOUNT', 'COMFINO_CART_VALUE_LIMITS_CONFIG',
            'COMFINO_USE_ORDER_REFERENCE', 'COMFINO_PAYWALL_DIRECT_REDIRECT', 'COMFINO_PAYWALL_CUSTOM_CSS_URL',
            'COMFINO_IS_SANDBOX', 'COMFINO_DEBUG', 'COMFINO_SERVICE_MODE', 'COMFINO_ALLOWED_PRODUCTS_CONFIG',
            'COMFINO_ALLOWED_PRODUCTS_CONFIG_ENABLED', 'COMFINO_PRODUCT_CATEGORY_FILTERS', 'COMFINO_PRODUCT_ID_FILTER',
            'COMFINO_CAT_FILTER_AVAIL_PROD_TYPES', 'COMFINO_ALLOWED_PRODUCTS_CONFIG_FORBIDDEN_PROD_TYPES',
            'COMFINO_WIDGET_ENABLED', 'COMFINO_WIDGET_KEY', 'COMFINO_WIDGET_PRICE_SELECTOR', 'COMFINO_WIDGET_PRICE_ATTRIBUTE',
            'COMFINO_WIDGET_TARGET_SELECTOR', 'COMFINO_WIDGET_PRICE_OBSERVER_SELECTOR', 'COMFINO_WIDGET_PRICE_OBSERVER_LEVEL',
            'COMFINO_WIDGET_TYPE', 'COMFINO_WIDGET_OFFER_TYPES', 'COMFINO_WIDGET_EMBED_METHOD',
            'COMFINO_WIDGET_SHOW_PROVIDER_LOGOS', 'COMFINO_WIDGET_CUSTOM_BANNER_CSS_URL',
            'COMFINO_WIDGET_CUSTOM_CALCULATOR_CSS_URL', 'COMFINO_WIDGET_DISABLE_BANNER',
            'COMFINO_WIDGET_CALCULATOR_TRIGGER_SELECTOR', 'COMFINO_ABANDONED_CART_ENABLED', 'COMFINO_ABANDONED_PAYMENTS',
            'COMFINO_IGNORED_STATUSES', 'COMFINO_FORBIDDEN_STATUSES', 'COMFINO_STATUS_MAP', 'COMFINO_API_CONNECT_TIMEOUT',
            'COMFINO_API_TIMEOUT', 'COMFINO_API_CONNECT_NUM_ATTEMPTS', 'COMFINO_DEV_ENV_VARS', 'COMFINO_REMOTE_FLAGS',
        ];

        $this->assertCount(44, $expected);
        $this->assertSame($expected, ConfigManager::ACCESSIBLE_CONFIG_OPTIONS);
        $this->assertNotContains('COMFINO_API_KEY', ConfigManager::ACCESSIBLE_CONFIG_OPTIONS);
        $this->assertNotContains('COMFINO_SANDBOX_API_KEY', ConfigManager::ACCESSIBLE_CONFIG_OPTIONS);
        $this->assertNotContains('COMFINO_PAYWALL_TRACKING_CONSENT', ConfigManager::ACCESSIBLE_CONFIG_OPTIONS);
    }

    public function testCheckoutConfigEncodingIsScriptSafe(): void
    {
        $config = ['flagAttributes' => ['X' => ['text' => '</script><b>"\'&']], 'flags' => null];

        // Classic checkout renders the config through views/front/payment.php.
        $comfino_total_amount = 100;
        $comfino_checkout_config = $config;

        ob_start();
        include dirname(__DIR__, 2) . '/views/front/payment.php';
        $html = ob_get_clean();

        $this->assertSame(1, preg_match('#<script type="application/json" id="comfino-checkout-config">(.*?)</script>#s', $html, $matches));
        $this->assertNotContains('<', $matches[1]);
        $this->assertSame($config, json_decode($matches[1], true));
    }

    private function cachedTtlFor(?string $ttlHeader): int
    {
        CacheManager::init('');
        $this->apiClient->userSettingsFlags = ['PROMO' => self::PROMO];
        $this->apiClient->userSettingsHeaders = $ttlHeader === null ? [] : ['Comfino-Cache-TTL' => $ttlHeader];

        ConfigManager::getRemoteFlagAttributes();

        return $this->cachedTtl();
    }

    private function cachedTtl(): int
    {
        $item = CacheManager::getCachePool()->getItem('remote_flag_attributes');
        $item->isHit(); // Items are loaded lazily; the expiry is known after the first read.

        return (int) $item->getExpirationTimestamp() - time();
    }
}
