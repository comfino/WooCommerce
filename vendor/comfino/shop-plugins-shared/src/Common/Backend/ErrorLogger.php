<?php

declare(strict_types=1);

namespace Comfino\Common\Backend;

use Comfino\Common\Backend\Log\LoggerFactory;
use Comfino\Extended\Api\Client;
use Comfino\Extended\Api\Dto\Plugin\ShopPluginError;
use ComfinoExternal\Monolog\Logger as MonologLogger;

final class ErrorLogger extends Logger
{
    /**
     * @var \Comfino\Extended\Api\Client
     */
    private $apiClient;
    /**
     * @var string
     */
    private $logFilePath;
    /**
     * @var string
     */
    private $host;
    /**
     * @var string
     */
    private $platform;
    /**
     * @var string
     */
    private $modulePath;
    /**
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
    /**
     * @var MonologLogger|null
     */
    private static $logger;

    /**
     * @param string $logFilePath
     * @return self
     * @param \Comfino\Extended\Api\Client $apiClient
     * @param string $host
     * @param string $platform
     * @param string $modulePath
     * @param mixed[] $environment
     */
    public static function getInstance($apiClient, $logFilePath, $host, $platform, $modulePath, $environment): self
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

    /**
     * @return MonologLogger
     */
    public function getLogger(): MonologLogger
    {
        if (self::$logger === null) {
            self::$logger = LoggerFactory::createErrorLogger($this->logFilePath);
        }

        return self::$logger;
    }

    /**
     * @param string $errorPrefix
     * @param string $errorCode
     * @param string $errorMessage
     * @param string|null $apiRequestUrl
     * @param string|null $apiRequest
     * @param string|null $apiResponse
     * @param string|null $stackTrace
     * @return void
     */
    public function sendError(
        $errorPrefix,
        $errorCode,
        $errorMessage,
        $apiRequestUrl = null,
        $apiRequest = null,
        $apiResponse = null,
        $stackTrace = null
    ): void {
        if (preg_match('/Error .*in |Exception .*in /', $errorMessage) && strpos($errorMessage, $this->modulePath) === false) {
            return;
        }

        if (getenv('COMFINO_DEBUG') === 'TRUE') {
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

    /**
     * @param string $errorPrefix
     * @param string $errorMessage
     */
    public function logError($errorPrefix, $errorMessage): void
    {
        $this->getLogger()->error($errorPrefix . ': ' . $errorMessage);
    }

    /**
     * @param int $numLines
     * @return string
     */
    public function getErrorLog($numLines): string
    {
        $actualLogPath = $this->findActualLogFile($this->logFilePath);

        if ($actualLogPath === null || !file_exists($actualLogPath)) {
            return '';
        }

        return implode('', FileUtils::readLastLines($actualLogPath, $numLines));
    }

    /**
     * @return int
     */
    public function clearLogs(): int
    {
        return $this->clearLogFiles($this->logFilePath);
    }

    /**
     * @return false
     */
    public function errorHandler($errNo, $errMsg, $file, $line): bool
    {
        $errorType = $this->getErrorTypeName($errNo);

        if (strpos($errorType, 'E_USER_') === false && strpos($errorType, 'NOTICE') === false) {
            $this->sendError("Error $errorType in $file:$line", (string) $errNo, $errMsg);
        }

        return false;
    }

    /**
     * @param \Throwable $exception
     */
    public function exceptionHandler($exception): void
    {
        $this->sendError(
            'Exception ' . get_class($exception) . " in {$exception->getFile()}:{$exception->getLine()}",
            (string) $exception->getCode(),
            $exception->getMessage(),
            null,
            null,
            null,
            $exception->getTraceAsString()
        );
    }

    public function init(): void
    {
        if (getenv('COMFINO_DEBUG') === 'TRUE') {
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
