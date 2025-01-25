<?php

namespace Comfino\Api;

use Comfino\Api\Exception\AuthorizationError;
use Comfino\Common\Backend\Factory\ApiClientFactory;
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
                $apiKey = (string) ConfigManager::getConfigurationValue('COMFINO_SANDBOX_API_KEY');
            } else {
                $apiKey = (string) ConfigManager::getConfigurationValue('COMFINO_API_KEY');
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
                ConfigManager::getApiHost(),
                Main::getShopLanguage(),
                ConfigManager::getConfigurationValue('COMFINO_API_CONNECT_TIMEOUT', 1),
                ConfigManager::getConfigurationValue('COMFINO_API_TIMEOUT', 3),
                ConfigManager::getConfigurationValue('COMFINO_API_CONNECT_NUM_ATTEMPTS', 3)
            );

            self::$apiClient->addCustomHeader('Comfino-Build-Timestamp', (string) PaymentGateway::BUILD_TS);
        } else {
            self::$apiClient->setCustomApiHost(ConfigManager::getApiHost());
            self::$apiClient->setApiKey($apiKey);
            self::$apiClient->setApiLanguage(Main::getShopLanguage());
        }

        if ($sandboxMode) {
            self::$apiClient->enableSandboxMode();
        } else {
            self::$apiClient->disableSandboxMode();
        }

        return self::$apiClient;
    }

    public static function processApiError(string $errorPrefix, \Throwable $exception): void
    {
        if ($exception instanceof HttpErrorExceptionInterface) {
            $url = $exception->getUrl();
            $requestBody = $exception->getRequestBody();
            $responseBody = $exception->getResponseBody();
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
            $exception,
            $errorPrefix,
            $exception->getCode(),
            $exception->getMessage(),
            $url !== '' ? $url : null,
            $requestBody !== '' ? $requestBody : null,
            $responseBody !== '' ? $responseBody : null,
            $exception->getTraceAsString()
        );
    }
}
