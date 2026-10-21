<?php

namespace Comfino\Tests;

use Comfino\Api\ApiClient;
use Comfino\Common\Backend\ConfigurationManager;
use Comfino\Common\Backend\Payment\ProductTypeFilterManager;
use Comfino\Common\Shop\Order\StatusApplicationContext;
use Comfino\Configuration\ConfigManager;
use Comfino\Configuration\SettingsManager;
use Comfino\PluginShared\CacheManager;

/**
 * Resets the static state shared between tests: configuration managers, the API client, the status application
 * context, the cache and the in-memory transients.
 */
final class TestState
{
    public static function reset(): void
    {
        self::setStatic(ConfigManager::class, 'configurationManager', null);
        self::setStatic(ConfigurationManager::class, 'instances', []);
        self::setStatic(ApiClient::class, 'apiClient', null);
        self::setStatic(SettingsManager::class, 'filterManager', null);
        self::setStatic(ProductTypeFilterManager::class, 'instance', null);

        StatusApplicationContext::reset();
        CacheManager::init('');

        $GLOBALS['comfino_test_transients'] = [];

        unset(
            $GLOBALS['comfino_test_order_notes'],
            $GLOBALS['comfino_test_order_status_changed'],
            $GLOBALS['comfino_test_translations']
        );
    }

    /**
     * @param mixed $client
     */
    public static function setApiClient($client): void
    {
        self::setStatic(ApiClient::class, 'apiClient', $client);
    }

    /**
     * @param mixed $value
     */
    public static function setStatic(string $className, string $propertyName, $value): void
    {
        $property = new \ReflectionProperty($className, $propertyName);
        $property->setAccessible(true);
        $property->setValue(null, $value);
    }
}
