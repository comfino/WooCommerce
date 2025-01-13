<?php

namespace Comfino;

use Comfino\Api\ApiClient;
use Comfino\Configuration\ConfigManager;

if (!defined('ABSPATH')) {
    exit;
}

final class ErrorLogger
{
    /** @var Common\Backend\ErrorLogger */
    private static $errorLogger;

    public static function init(): void
    {
        static $initialized = false;

        if (!$initialized) {
            self::getLoggerInstance()->init();

            $initialized = true;
        }
    }

    public static function getLoggerInstance(): Common\Backend\ErrorLogger
    {
        if (self::$errorLogger === null) {
            self::$errorLogger = Common\Backend\ErrorLogger::getInstance(
                ApiClient::getInstance(),
                Main::getPluginDirectory() . '/var/log/errors.log',
                Main::getShopDomain(),
                'WooCommerce',
                'plugins/comfino',
                ConfigManager::getEnvironmentInfo()
            );
        }

        return self::$errorLogger;
    }

    public static function logError(string $errorPrefix, string $errorMessage): void
    {
        self::$errorLogger->logError($errorPrefix, $errorMessage);
    }

    public static function sendError(
        string  $errorPrefix,
        string  $errorCode,
        string  $errorMessage,
        ?string $apiRequestUrl = null,
        ?string $apiRequest = null,
        ?string $apiResponse = null,
        ?string $stackTrace = null
    ): void {
        self::$errorLogger->sendError(
            $errorPrefix, $errorCode, $errorMessage, $apiRequestUrl, $apiRequest, $apiResponse, $stackTrace
        );
    }
}
