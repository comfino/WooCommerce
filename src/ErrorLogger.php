<?php

namespace Comfino;

use Comfino\Api\ApiClient;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Configuration\ConfigManager;
use Comfino\Extended\Api\Dto\Plugin\ErrorCategory;
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
        if ($exception instanceof AuthorizationError) {
            // Don't collect authorization errors caused by empty or wrong API key (response with status code 401).
            return;
        }

        $severity = Common\Backend\ErrorLogger::resolveSeverity($exception);

        if ($severity === null) {
            /* Validation outcome (rejected request or invalid response, already visible at API side) - keep a local
               record only, never report it. Payloads travel as context, so the log processor redacts them. */
            $logContext = array_filter(
                [
                    'error_code' => $errorCode,
                    'api_url' => $apiRequestUrl,
                    'api_request' => $apiRequest,
                    'api_response' => $apiResponse,
                ],
                static function ($value): bool {
                    return $value !== null;
                }
            );

            self::getLoggerInstance()->logError(
                '[' . Common\Backend\ErrorLogger::classifyException($exception)->value . "][$context]",
                $errorMessage,
                $logContext
            );

            return;
        }

        self::getLoggerInstance()->sendError(
            Common\Backend\ErrorLogger::classifyException($exception),
            ErrorSeverity::from($severity),
            OperationContext::from($context),
            $errorCode,
            $errorMessage,
            $apiRequestUrl,
            $apiRequest,
            $apiResponse,
            $stackTrace ?? $exception->getTraceAsString()
        );
    }

    /**
     * Reports a message without an exception (e.g. a recoverable misconfiguration observed by the plugin itself).
     * The message must be stable, free of IDs and personal data, and contain no stack trace.
     */
    public static function sendWarning(string $category, string $context, string $errorCode, string $errorMessage): void
    {
        self::getLoggerInstance()->sendError(
            ErrorCategory::from($category),
            ErrorSeverity::from(ErrorSeverity::Warning),
            OperationContext::from($context),
            $errorCode,
            $errorMessage
        );
    }

    public static function clearLogs(): void
    {
        self::getLoggerInstance()->clearLogs();
    }
}
