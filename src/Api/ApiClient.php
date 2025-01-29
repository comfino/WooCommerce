<?php

namespace Comfino\Api;

use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Common\Backend\Factory\ApiClientFactory;
use Comfino\Common\Exception\ConnectionTimeout;
use Comfino\Common\Frontend\FrontendHelper;
use Comfino\Configuration\ConfigManager;
use Comfino\DebugLogger;
use Comfino\ErrorLogger;
use Comfino\Main;
use Comfino\PaymentGateway;
use Comfino\View\FrontendManager;
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

    public static function processApiError(string $errorPrefix, \Throwable $exception): array
    {
        $userErrorMessage = __(
            'There was a technical problem. Please try again in a moment and it should work!',
            'comfino-payment-gateway'
        );

        $statusCode = 500;
        $isTimeout = false;
        $connectAttemptIdx = 1;
        $connectionTimeout = ConfigManager::getConfigurationValue('COMFINO_API_CONNECT_TIMEOUT', 1);
        $transferTimeout = ConfigManager::getConfigurationValue('COMFINO_API_TIMEOUT', 3);

        if ($exception instanceof HttpErrorExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $url = $exception->getUrl();
            $requestBody = $exception->getRequestBody();
            $responseBody = $exception->getResponseBody();

            if ($exception instanceof AccessDenied && $statusCode === 404) {
                $userErrorMessage = $exception->getMessage();
            } elseif ($exception instanceof ConnectionTimeout) {
                $isTimeout = true;
                $connectAttemptIdx = $exception->getConnectAttemptIdx();
                $connectionTimeout = $exception->getConnectionTimeout();
                $transferTimeout = $exception->getTransferTimeout();

                DebugLogger::logEvent(
                    '[API_TIMEOUT]',
                    $errorPrefix,
                    [
                        'exception' => $exception->getPrevious() !== null ? get_class($exception->getPrevious()) : '',
                        'code' => $exception->getPrevious() !== null ? $exception->getPrevious()->getCode() : 0,
                        'connect_attempt_idx' => $exception->getConnectAttemptIdx(),
                        'connection_timeout' => $exception->getConnectionTimeout(),
                        'transfer_timeout' => $exception->getTransferTimeout(),
                    ]
                );
            } elseif ($statusCode < 500) {
                $userErrorMessage = __(
                    'We have a configuration problem. The store is already working on a solution!',
                    'comfino-payment-gateway'
                );
            } elseif ($statusCode < 504) {
                $userErrorMessage = __(
                    'It looks like we have an outage. We\'ll fix it as soon as possible!',
                    'comfino-payment-gateway'
                );
            }
        } elseif ($exception instanceof NetworkExceptionInterface) {
            $exception->getRequest()->getBody()->rewind();

            DebugLogger::logEvent('[API_NETWORK_ERROR]', $errorPrefix . " [{$exception->getMessage()}]");

            $url = $exception->getRequest()->getRequestTarget();
            $requestBody = $exception->getRequest()->getBody()->getContents();
            $responseBody = null;
        } else {
            $url = null;
            $requestBody = null;
            $responseBody = null;
        }

        DebugLogger::logEvent(
            '[API_ERROR]',
            $errorPrefix,
            [
                'exception' => get_class($exception),
                'error_message' => $exception->getMessage(),
                'error_code' => $exception->getCode(),
                'error_file' => $exception->getFile(),
                'error_line' => $exception->getLine(),
                'error_trace' => $exception->getTraceAsString(),
            ]
        );

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

        return [
            'title' => $userErrorMessage,
            'error_details' => FrontendHelper::prepareErrorDetails(
                $userErrorMessage,
                $statusCode,
                ConfigManager::isDevEnv(),
                $exception,
                $isTimeout,
                $connectAttemptIdx,
                $connectionTimeout,
                $transferTimeout,
                $url,
                $requestBody,
                $responseBody
            ),
        ];
    }
}
