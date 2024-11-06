<?php

namespace Comfino;

use Comfino\Api\ApiClient;
use Comfino\Configuration\ConfigManager;
use Comfino\ErrorLogger\StorageAdapter;

if (!defined('ABSPATH')) {
    exit;
}

final class ErrorLogger
{
    /** @var Common\Backend\ErrorLogger */
    private static $errorLogger;
    /** @var string */
    private static $logFilePath;

    public static function init(string $pluginDirectory): void
    {
        static $initialized = false;

        if (!$initialized) {
            self::$errorLogger = self::getLoggerInstance();
            self::$errorLogger->init();

            self::$logFilePath = "$pluginDirectory/var/log/errors.log";

            $initialized = true;
        }
    }

    public static function getLoggerInstance(): Common\Backend\ErrorLogger
    {
        return Common\Backend\ErrorLogger::getInstance(
            Main::getShopDomain(),
            'WooCommerce',
            'plugins/comfino',
            ConfigManager::getEnvironmentInfo(),
            ApiClient::getInstance(),
            new StorageAdapter()
        );
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

    public static function logError(string $errorPrefix, string $errorMessage): void
    {
        @file_put_contents(
            self::$logFilePath,
            '[' . date('Y-m-d H:i:s') . "] $errorPrefix: $errorMessage\n",
            FILE_APPEND
        );
    }

    public static function getErrorLog(int $numLines): string
    {
        return self::$errorLogger->getErrorLog(self::$logFilePath, $numLines);
    }
}
