<?php

declare(strict_types=1);

namespace Comfino\Common\Backend;

use Comfino\Extended\Api\Client;
use Comfino\Extended\Api\Dto\Plugin\ShopPluginError;

final class ErrorLogger
{
    /**
     * @readonly
     * @var \Comfino\Extended\Api\Client
     */
    private $apiClient;
    /**
     * @readonly
     * @var string
     */
    private $logFilePath;
    /**
     * @readonly
     * @var string
     */
    private $host;
    /**
     * @readonly
     * @var string
     */
    private $platform;
    /**
     * @readonly
     * @var string
     */
    private $modulePath;
    /**
     * @readonly
     * @var mixed[]
     */
    private $environment;
    private const ERROR_TYPES = [
        E_ERROR => 'E_ERROR',
        E_WARNING => 'E_WARNING',
        E_PARSE => 'E_PARSE',
        E_NOTICE => 'E_NOTICE',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_CORE_WARNING => 'E_CORE_WARNING',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING => 'E_COMPILE_WARNING',
        E_USER_ERROR => 'E_USER_ERROR',
        E_USER_WARNING => 'E_USER_WARNING',
        E_USER_NOTICE => 'E_USER_NOTICE',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED => 'E_DEPRECATED',
        E_USER_DEPRECATED => 'E_USER_DEPRECATED',
    ];

    /**
     * @var $this|null
     */
    private static $instance;

    public static function getInstance(Client $apiClient, string $logFilePath, string $host, string $platform, string $modulePath, array $environment): self
    {
        if (self::$instance === null) {
            self::$instance = new self($apiClient, $logFilePath, $host, $platform, $modulePath, $environment);
        }

        return self::$instance;
    }

    private function __construct(Client $apiClient, string $logFilePath, string $host, string $platform, string $modulePath, array $environment)
    {
        $this->apiClient = $apiClient;
        $this->logFilePath = $logFilePath;
        $this->host = $host;
        $this->platform = $platform;
        $this->modulePath = $modulePath;
        $this->environment = $environment;
    }

    public function sendError(
        string $errorPrefix,
        string $errorCode,
        string $errorMessage,
        ?string $apiRequestUrl = null,
        ?string $apiRequest = null,
        ?string $apiResponse = null,
        ?string $stackTrace = null
    ): void {
        if (preg_match('/Error .*in |Exception .*in /', $errorMessage) && strpos($errorMessage, $this->modulePath) === false) {
            // Ignore all errors and exceptions outside the plugin code.
            return;
        }

        if (getenv('COMFINO_DEBUG') === 'TRUE') {
            // Disable sending errors to the Comfino API if plugin is in debug mode.
            $errorsSendingDisabled = true;
        } else {
            $errorsSendingDisabled = false;
        }

        $error = new ShopPluginError(
            $this->host,
            $this->platform,
            $this->environment,
            $errorCode,
            "$errorPrefix: $errorMessage",
            $apiRequestUrl,
            $apiRequest,
            $apiResponse,
            $stackTrace
        );

        if ($errorsSendingDisabled || !$this->apiClient->sendLoggedError($error)) {
            $requestInfo = [];

            if ($apiRequestUrl !== null) {
                $requestInfo[] = "API URL: $apiRequestUrl";
            }

            if ($apiRequest !== null) {
                $requestInfo[] = "API request: $apiRequest";
            }

            if ($apiResponse !== null) {
                $requestInfo[] = "API response: $apiResponse";
            }

            if (count($requestInfo) > 0) {
                $errorMessage .= "\n" . implode("\n", $requestInfo);
            }

            if ($stackTrace !== null) {
                $errorMessage .= "\nStack trace: $stackTrace";
            }

            $this->logError($errorPrefix, $errorMessage);
        }
    }

    public function logError(string $errorPrefix, string $errorMessage): void
    {
        FileUtils::append($this->logFilePath, '[' . date('Y-m-d H:i:s') . "] $errorPrefix: $errorMessage\n");
    }

    public function getErrorLog(int $numLines): string
    {
        return implode('', FileUtils::readLastLines($this->logFilePath, $numLines));
    }

    public function errorHandler($errNo, $errMsg, $file, $line)
    {
        $errorType = $this->getErrorTypeName($errNo);

        if (strpos($errorType, 'E_USER_') === false && strpos($errorType, 'NOTICE') === false) {
            $this->sendError("Error $errorType in $file:$line", (string) $errNo, $errMsg);
        }

        return false;
    }

    public function exceptionHandler(\Throwable $exception): void
    {
        $this->sendError(
            'Exception ' . get_class($exception) . " in {$exception->getFile()}:{$exception->getLine()}",
            (string) $exception->getCode(), $exception->getMessage(),
            null, null, null, $exception->getTraceAsString()
        );
    }

    public function init(): void
    {
        if (getenv('COMFINO_DEBUG') === 'TRUE') {
            // Disable custom errors handling if plugin is in debug mode.
            return;
        }

        static $initialized = false;

        if (!$initialized) {
            set_error_handler([$this, 'errorHandler'], E_ERROR | E_RECOVERABLE_ERROR | E_PARSE);
            set_exception_handler([$this, 'exceptionHandler']);
            register_shutdown_function([$this, 'shutdown']);

            $initialized = true;
        }
    }

    public function shutdown(): void
    {
        if (($error = error_get_last()) !== null && ($error['type'] & (E_ERROR | E_RECOVERABLE_ERROR | E_PARSE))) {
            $errorType = $this->getErrorTypeName($error['type']);
            $this->sendError("Error $errorType in $error[file]:$error[line]", (string) $error['type'], $error['message']);
        }

        restore_error_handler();
        restore_exception_handler();
    }

    private function getErrorTypeName(int $errorType): string
    {
        return
            (($errorTypeName = array_key_exists($errorType, self::ERROR_TYPES) ? self::ERROR_TYPES[$errorType] : 'UNKNOWN') === 'UNKNOWN') &&
            (PHP_VERSION_ID < 70400) &&
            $errorType === E_STRICT
                ? 'E_STRICT'
                : $errorTypeName;
    }
}
