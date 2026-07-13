<?php

namespace Comfino;

use Comfino\Api\ApiClient;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Configuration\ConfigManager;
use Comfino\Extended\Api\Dto\Plugin\ErrorSeverity;
use Comfino\Extended\Api\Dto\Plugin\OperationContext;

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

    public static function sendError(
        \Throwable $exception,
        string $context,
        string $errorCode,
        string $errorMessage,
        ?string $apiRequestUrl = null,
        ?string $apiRequest = null,
        ?string $apiResponse = null,
        ?string $stackTrace = null
    ): void {
        if ($exception instanceof ResponseValidationError || $exception instanceof AuthorizationError) {
            /* - Don't collect validation errors - validation errors are already collected at API side (response with status code 400).
               - Don't collect authorization errors caused by empty or wrong API key (response with status code 401). */
            return;
        }

        self::getLoggerInstance()->sendError(
            Common\Backend\ErrorLogger::classifyException($exception),
            ErrorSeverity::from(ErrorSeverity::Error),
            OperationContext::from($context),
            $errorCode,
            $errorMessage,
            $apiRequestUrl,
            $apiRequest,
            $apiResponse,
            $stackTrace ?? $exception->getTraceAsString()
        );
    }

    public static function clearLogs(): void
    {
        self::getLoggerInstance()->clearLogs();
    }
}
