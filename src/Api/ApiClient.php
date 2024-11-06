<?php

namespace Comfino\Api;

use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use Comfino\Common\Backend\Factory\ApiClientFactory;
use Comfino\Common\Frontend\FrontendHelper;
use Comfino\Configuration\ConfigManager;
use Comfino\ErrorLogger;
use Comfino\Main;
use Comfino\PaymentGateway;
use ComfinoExternal\Psr\Http\Client\NetworkExceptionInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class ApiClient
{
    /** @var \Comfino\Common\Api\Client */
    private static $apiClient;

    public static function getInstance(?bool $sandboxMode = null, ?string $apiKey = null): \Comfino\Common\Api\Client
    {
        if ($sandboxMode === null) {
            $sandboxMode = ConfigManager::isSandboxMode();
        }

        if ($apiKey === null) {
            if ($sandboxMode) {
                $apiKey = ConfigManager::getConfigurationValue('COMFINO_SANDBOX_API_KEY');
            } else {
                $apiKey = ConfigManager::getConfigurationValue('COMFINO_API_KEY');
            }
        }

        if (self::$apiClient === null) {
            self::$apiClient = (new ApiClientFactory())->createClient(
                $apiKey,
                sprintf(
                    'WC Comfino [%s], WP [%s], WC [%s], PHP [%s], %s',
                    ...array_merge(
                        array_values(ConfigManager::getEnvironmentInfo([
                            'plugin_version',
                            'wordpress_version',
                            'shop_version',
                            'php_version',
                        ])),
                        [Main::getShopDomain()]
                    )
                ),
                self::getApiHost(),
                Main::getShopLanguage(),
                ConfigManager::getConfigurationValue('COMFINO_API_CONNECT_TIMEOUT', 1),
                ConfigManager::getConfigurationValue('COMFINO_API_TIMEOUT', 3)
            );

            self::$apiClient->addCustomHeader('Comfino-Build-Timestamp', (string) PaymentGateway::BUILD_TS);
        } else {
            self::$apiClient->setApiKey($apiKey);
            self::$apiClient->setApiLanguage(Main::getShopLanguage());
        }

        return self::$apiClient;
    }

    public static function processApiError(string $errorPrefix, \Throwable $exception): void
    {
        if ($exception instanceof RequestValidationError || $exception instanceof ResponseValidationError
            || $exception instanceof AuthorizationError || $exception instanceof AccessDenied
            || $exception instanceof ServiceUnavailable
        ) {
            $url = $exception->getUrl();
            $requestBody = $exception->getRequestBody();

            if ($exception instanceof ResponseValidationError || $exception instanceof ServiceUnavailable) {
                $responseBody = $exception->getResponseBody();
            } else {
                $responseBody = null;
            }
        } elseif ($exception instanceof NetworkExceptionInterface) {
            $exception->getRequest()->getBody()->rewind();

            $url = $exception->getRequest()->getRequestTarget();
            $requestBody = $exception->getRequest()->getBody()->getContents();
            $responseBody = null;
        } else {
            $url = null;
            $requestBody = null;
            $responseBody = null;
        }

        ErrorLogger::sendError(
            $errorPrefix,
            $exception->getCode(),
            $exception->getMessage(),
            $url !== '' ? $url : null,
            $requestBody !== '' ? $requestBody : null,
            $responseBody !== '' ? $responseBody : null,
            $exception->getTraceAsString()
        );
    }

    public static function getLogoUrl(): string
    {
        return self::getApiHost(self::getInstance()->getApiHost())
            . '/v1/get-logo-url?auth='
            . FrontendHelper::getLogoAuthHash('WC', WC_VERSION, PaymentGateway::VERSION, PaymentGateway::BUILD_TS);
    }

    public static function getPaywallLogoUrl(): string
    {
        return self::getApiHost(self::getInstance()->getApiHost())
            . '/v1/get-paywall-logo?auth='
            . FrontendHelper::getPaywallLogoAuthHash(
                'WC',
                WC_VERSION,
                PaymentGateway::VERSION,
                self::getInstance()->getApiKey(),
                ConfigManager::getWidgetKey(),
                PaymentGateway::BUILD_TS
            );
    }

    public static function getWidgetScriptUrl(): string
    {
        if (self::isDevEnv() && getenv('COMFINO_DEV_WIDGET_SCRIPT_URL')) {
            return getenv('COMFINO_DEV_WIDGET_SCRIPT_URL');
        }

        $widgetScriptUrl = ConfigManager::isSandboxMode() ? 'https://widget.craty.pl' : 'https://widget.comfino.pl';
        $widgetProdScriptVersion = ConfigManager::getConfigurationValue('COMFINO_WIDGET_PROD_SCRIPT_VERSION');

        if (empty($widgetProdScriptVersion)) {
            $widgetScriptUrl .= '/comfino.min.js';
        } else {
            $widgetScriptUrl .= ('/' . trim($widgetProdScriptVersion, '/'));
        }

        return $widgetScriptUrl;
    }

    public static function isDevEnv(): bool
    {
        return ((string) getenv('COMFINO_DEV')) === ('WC_' . WC_VERSION . '_' . Main::getShopUrl());
    }

    private static function getApiHost(?string $apiHost = null): ?string
    {
        if (self::isDevEnv() && getenv('COMFINO_DEV_API_HOST')) {
            return getenv('COMFINO_DEV_API_HOST');
        }

        return $apiHost;
    }
}
