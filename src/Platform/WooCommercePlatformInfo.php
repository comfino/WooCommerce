<?php

namespace Comfino\Platform;

use Comfino\Configuration\ConfigManager;
use Comfino\Main;
use Comfino\PaymentGateway;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce implementation of the shared PlatformInfoInterface.
 *
 * Reads platform/shop metadata from WooCommerce/WordPress globals via the existing ConfigManager and Main helpers, so
 * the shop-environment builder can assemble a backend report.
 */
class WooCommercePlatformInfo implements PlatformInfoInterface
{
    /**
     * @var array<string, string>
     */
    private $envInfo;

    public function __construct()
    {
        $this->envInfo = ConfigManager::getEnvironmentInfo([
            'plugin_version',
            'shop_version',
            'php_version',
            'database_version',
        ]);
    }

    public function getCode(): string
    {
        return 'WC';
    }

    public function getName(): string
    {
        return 'WooCommerce';
    }

    public function getVersion(): string
    {
        return (string) ($this->envInfo['shop_version'] ?? '');
    }

    public function getLanguage(): string
    {
        return Main::getShopLanguage();
    }

    public function getCurrency(): string
    {
        return Main::getShopCurrency();
    }

    public function getDomain(): string
    {
        return Main::getShopDomain();
    }

    public function getDatabaseVersion(): string
    {
        return (string) ($this->envInfo['database_version'] ?? '');
    }

    public function getPhpVersion(): string
    {
        return (string) ($this->envInfo['php_version'] ?? PHP_VERSION);
    }

    public function getPluginVersion(): string
    {
        return (string) ($this->envInfo['plugin_version'] ?? PaymentGateway::VERSION);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'shopName' => $this->getName(),
            'shopVersion' => $this->getVersion(),
            'shopLanguage' => $this->getLanguage(),
            'shopCurrency' => $this->getCurrency(),
            'shopDomain' => $this->getDomain(),
            'databaseVersion' => $this->getDatabaseVersion(),
            'phpVersion' => $this->getPhpVersion(),
            'pluginVersion' => $this->getPluginVersion(),
        ];
    }
}
