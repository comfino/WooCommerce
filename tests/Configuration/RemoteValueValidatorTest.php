<?php

namespace Comfino\Tests\Configuration;

use Comfino\Configuration\ConfigManager;
use Comfino\Configuration\RemoteValueValidator;
use Comfino\Configuration\StorageAdapter;
use Comfino\Main;
use Comfino\Order\ShopStatusManager;
use Comfino\Tests\TestState;

/**
 * Remotely writable values validated on write (StorageAdapter::save(), shared by the admin form and the signed
 * remote configuration endpoint) and on read. Covers the custom CSS URLs and the status options.
 */
class RemoteValueValidatorTest extends \PHPUnit_Framework_TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/test';
        $_SERVER['REQUEST_SCHEME'] = 'https';
        $_SERVER['HTTP_HOST'] = 'comfino-wc-store.test';

        Main::setPluginDirectory(__DIR__ . '/../..');
        Main::setPluginFile(__DIR__ . '/../../comfino-payment-gateway.php');

        TestState::reset();
    }

    public function tearDown(): void
    {
        TestState::reset();

        parent::tearDown();
    }

    /**
     * @dataProvider cssUrlProvider
     */
    public function testCssUrlValidation(string $url, bool $expected): void
    {
        $this->assertSame($expected, RemoteValueValidator::isValidCssUrl($url), $url);
    }

    public function cssUrlProvider(): array
    {
        return [
            'shop host' => ['https://comfino-wc-store.test/wp-content/themes/x/paywall.css', true],
            'shop host, upper case' => ['https://COMFINO-WC-STORE.test/paywall.css', true],
            'empty means not set' => ['', true],
            'plain http' => ['http://comfino-wc-store.test/paywall.css', false],
            'foreign host' => ['https://evil.tld/x.css', false],
            'shop domain as query' => ['https://evil.tld/x.css?comfino-wc-store.test', false],
            'shop domain as subdomain' => ['https://comfino-wc-store.test.evil.tld/x.css', false],
            'other port' => ['https://comfino-wc-store.test:8443/x.css', false],
            'javascript scheme' => ['javascript:alert(1)', false],
            'quote injection' => ['https://comfino-wc-store.test/x.css"onload="x', false],
        ];
    }

    public function testInvalidCssUrlIsRejectedOnRemoteWritePathAndOldValueKept(): void
    {
        foreach (RemoteValueValidator::CSS_URL_OPTIONS as $optionName) {
            $adapter = new StorageAdapter();
            $adapter->save([
                $optionName => 'https://evil.tld/x.css?comfino-wc-store.test',
                'COMFINO_PAYMENT_TEXT' => 'ComfinoPay',
            ]);

            $internalName = ConfigManager::CONFIG_OPTIONS_MAP[$optionName];

            $this->assertArrayNotHasKey($internalName, $adapter->settings, $optionName);
            $this->assertSame('ComfinoPay', $adapter->settings['payment_text'], 'Valid options in the same write are saved.');
        }
    }

    public function testValidCssUrlIsSaved(): void
    {
        $adapter = new StorageAdapter();
        $adapter->save(['COMFINO_PAYWALL_CUSTOM_CSS_URL' => 'https://comfino-wc-store.test/paywall.css']);

        $this->assertSame('https://comfino-wc-store.test/paywall.css', $adapter->settings['paywall_custom_css_url']);
    }

    public function testInvalidCssUrlIsDroppedOnRead(): void
    {
        ConfigManager::getInstance()->setConfigurationValue('COMFINO_PAYWALL_CUSTOM_CSS_URL', 'https://evil.tld/x.css');

        $this->assertNull(ConfigManager::getCustomCssUrl('COMFINO_PAYWALL_CUSTOM_CSS_URL'));

        ConfigManager::getInstance()->setConfigurationValue('COMFINO_PAYWALL_CUSTOM_CSS_URL', 'https://comfino-wc-store.test/p.css');

        $this->assertSame('https://comfino-wc-store.test/p.css', ConfigManager::getCustomCssUrl('COMFINO_PAYWALL_CUSTOM_CSS_URL'));
    }

    public function testStatusMapWithInvalidEntryIsRejectedOnWrite(): void
    {
        $adapter = new StorageAdapter();
        $adapter->save([
            'COMFINO_STATUS_MAP' => wp_json_encode(['ACCEPTED' => 'completed', 'NOT_A_STATUS' => 'cancelled']),
        ]);
        $adapter->save([
            'COMFINO_STATUS_MAP' => wp_json_encode(['ACCEPTED' => 'no-such-order-status']),
        ]);

        $this->assertArrayNotHasKey('status_map', $adapter->settings);

        $adapter->save(['COMFINO_STATUS_MAP' => wp_json_encode(ShopStatusManager::DEFAULT_STATUS_MAP)]);

        $this->assertSame(wp_json_encode(ShopStatusManager::DEFAULT_STATUS_MAP), $adapter->settings['status_map']);
    }

    public function testStatusMapIsFilteredOnRead(): void
    {
        ConfigManager::getInstance()->setConfigurationValue(
            'COMFINO_STATUS_MAP',
            ['ACCEPTED' => 'completed', 'REJECTED' => 'no-such-order-status', 'NOT_A_STATUS' => 'cancelled']
        );

        $this->assertSame(['ACCEPTED' => 'completed'], ConfigManager::getStatusMap());

        ConfigManager::getInstance()->setConfigurationValue('COMFINO_STATUS_MAP', ['NOT_A_STATUS' => 'cancelled']);

        $this->assertSame(ShopStatusManager::DEFAULT_STATUS_MAP, ConfigManager::getStatusMap(), 'Nothing valid left: defaults.');
    }

    public function testStatusListsAcceptOnlyKnownComfinoStatuses(): void
    {
        $adapter = new StorageAdapter();
        $adapter->save(['COMFINO_IGNORED_STATUSES' => ['WAITING_FOR_FILLING', 'completed']]);
        $adapter->save(['COMFINO_FORBIDDEN_STATUSES' => 'RESIGN,<script>']);

        $this->assertArrayNotHasKey('ignored_statuses', $adapter->settings);
        $this->assertArrayNotHasKey('forbidden_statuses', $adapter->settings);

        ConfigManager::getInstance()->setConfigurationValue('COMFINO_IGNORED_STATUSES', ['WAITING_FOR_FILLING', 'completed']);
        ConfigManager::getInstance()->setConfigurationValue('COMFINO_FORBIDDEN_STATUSES', ['RESIGN', '<script>']);

        $this->assertSame(['WAITING_FOR_FILLING'], ConfigManager::getIgnoredStatuses());
        $this->assertSame(['RESIGN'], ConfigManager::getForbiddenStatuses());
    }

    public function testTypeChecksOnWrite(): void
    {
        $adapter = new StorageAdapter();
        $adapter->save([
            'COMFINO_ENABLED' => ['yes'],
            'COMFINO_PAYMENT_TEXT' => ['not', 'a', 'string'],
            'COMFINO_CHECKOUT_PRODUCT_TYPES' => ['PAY_LATER', 'NOT_A_PRODUCT'],
            'COMFINO_DEBUG' => true,
        ]);

        $this->assertArrayNotHasKey('enabled', $adapter->settings);
        $this->assertArrayNotHasKey('payment_text', $adapter->settings);
        $this->assertArrayNotHasKey('checkout_product_types', $adapter->settings);
        $this->assertSame('yes', $adapter->settings['debug_mode']);
    }
}
