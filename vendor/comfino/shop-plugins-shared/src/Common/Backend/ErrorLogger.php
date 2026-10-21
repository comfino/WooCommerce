<?php

declare(strict_types=1);

namespace Comfino\Common\Backend;

use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\SensitiveDataRedactor;
use Comfino\Api\Serializer\Json;
use Comfino\Api\SerializerInterface;
use Comfino\Common\Backend\Log\ErrorMessageNormalizer;
use Comfino\Common\Backend\Log\LoggerFactory;
use Comfino\Common\Backend\Log\ResponseBodySummarizer;
use Comfino\Common\Backend\Queue\ApiTransientErrorClassifier;
use Comfino\Common\Backend\Queue\OutboundRequestQueue;
use Comfino\Common\Backend\Queue\ReportErrorHandler;
use Comfino\Extended\Api\Client;
use Comfino\Extended\Api\Dto\Plugin\ErrorCategory;
use Comfino\Extended\Api\Dto\Plugin\ErrorSeverity;
use Comfino\Extended\Api\Dto\Plugin\OperationContext;
use Comfino\Extended\Api\Dto\Plugin\ShopPluginError;
use Comfino\Extended\Api\Request\ReportShopPluginError;
use ComfinoExternal\Monolog\Logger as MonologLogger;

final class ErrorLogger extends Logger
{
    /**
     * @var Client
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
     * @var array
     */
    private $environment;
    /**
     * @var string
     */
    private $pluginVersion;
    /**
     * @var string
     */
    private $platformVersion;
    /**
     * @var string
     */
    private $phpVersion;
    /**
     * @var ErrorMessageNormalizer
     */
    private $normalizer;
    /**
     * @var OutboundRequestQueue|null
     */
    private $outboundQueue;
    /**
     * @var SerializerInterface
     */
    private $serializer;
    private const CATCHED_ERRORS_MASK = E_ERROR | E_RECOVERABLE_ERROR | E_PARSE;
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

    private const VERSION_ENV_KEYS = ['plugin_version', 'platform_version', 'shop_version', 'php_version'];

    /**
     * @var $this|null
     */
    private static $instance;
    /**
     * @var MonologLogger|null
     */
    private static $logger;

    private $previousExceptionHandler = null;

    /**
     * @var string|null
     */
    private $rethrownExceptionLocation;

    /**
     * @param Client $apiClient
     * @param string $logFilePath
     * @param string $host
     * @param string $platform
     * @param string $modulePath
     * @param array $environment
     * @param OutboundRequestQueue|null $outboundQueue
     * @param SerializerInterface|null $serializer
     * @return self
     */
    public static function getInstance(
        $apiClient,
        $logFilePath,
        $host,
        $platform,
        $modulePath,
        $environment,
        $outboundQueue = null,
        $serializer = null
    ): self {
        if (self::$instance === null) {
            $pluginVersion = (string) ($environment['plugin_version'] ?? '');
            $platformVersion = (string) ($environment['platform_version'] ?? $environment['shop_version'] ?? '');
            $phpVersion = (string) ($environment['php_version'] ?? '');

            $supplementaryEnv = array_diff_key($environment, array_flip(self::VERSION_ENV_KEYS));

            self::$instance = new self(
                $apiClient,
                $logFilePath,
                $host,
                $platform,
                $modulePath,
                $supplementaryEnv,
                $pluginVersion,
                $platformVersion,
                $phpVersion,
                new ErrorMessageNormalizer(),
                $outboundQueue,
                $serializer ?? new Json()
            );
        }

        return self::$instance;
    }

    /**
     * @param Client $apiClient
     * @param string $logFilePath
     * @param string $host
     * @param string $platform
     * @param string $modulePath
     * @param array $environment
     * @param string $pluginVersion
     * @param string $platformVersion
     * @param string $phpVersion
     * @param ErrorMessageNormalizer $normalizer
     * @param OutboundRequestQueue|null $outboundQueue
     * @param SerializerInterface $serializer
     */
    private function __construct(Client $apiClient, string $logFilePath, string $host, string $platform, string $modulePath, array $environment, string $pluginVersion, string $platformVersion, string $phpVersion, ErrorMessageNormalizer $normalizer, ?OutboundRequestQueue $outboundQueue = null, ?SerializerInterface $serializer = null)
    {
        $serializer = $serializer ?? new Json();
        $this->apiClient = $apiClient;
        $this->logFilePath = $logFilePath;
        $this->host = $host;
        $this->platform = $platform;
        $this->modulePath = $modulePath;
        $this->environment = $environment;
        $this->pluginVersion = $pluginVersion;
        $this->platformVersion = $platformVersion;
        $this->phpVersion = $phpVersion;
        $this->normalizer = $normalizer;
        $this->outboundQueue = $outboundQueue;
        $this->serializer = $serializer;
    }

    /**
     * @return MonologLogger
     * @throws \Exception
     */
    public function getLogger(): MonologLogger
    {
        if (self::$logger === null) {
            self::$logger = LoggerFactory::createErrorLogger($this->logFilePath);
        }

        return self::$logger;
    }

    /**
     * @param ErrorCategory $category
     * @param ErrorSeverity $severity
     * @param OperationContext $context
     * @param string $errorCode
     * @param string $errorMessage
     * @param string|null $apiRequestUrl
     * @param string|null $apiRequest
     * @param string|null $apiResponse
     * @param string|null $stackTrace
     */
    public function sendError(
        $category,
        $severity,
        $context,
        $errorCode,
        $errorMessage,
        $apiRequestUrl = null,
        $apiRequest = null,
        $apiResponse = null,
        $stackTrace = null
    ): void {
        if (preg_match('/Error .*in |Exception .*in /', $errorMessage) && strpos(self::normalizePath($errorMessage), self::normalizePath($this->modulePath)) === false) {
            return;
        }

        if (getenv('COMFINO_DEV_ENV') === 'TRUE' && getenv('COMFINO_FORCE_ERRORS_SENDING') !== 'TRUE') {
            $errorsSendingDisabled = true;
        } else {
            $errorsSendingDisabled = false;
        }

        $errorMessage = SensitiveDataRedactor::redactText($errorMessage);
        $apiRequest = $apiRequest !== null ? SensitiveDataRedactor::redactPayload($apiRequest) : null;
        $apiResponse = $apiResponse !== null
            ? SensitiveDataRedactor::redactPayload(ResponseBodySummarizer::summarize($apiResponse))
            : null;

        $callEnv = array_merge($this->environment, [
            'caller' => $this->formatContext($this->extractCallerContext()),
        ]);

        $normalizedMessage = $this->normalizer->normalizeMessage($errorMessage);
        $normalizedTrace = $stackTrace !== null ? $this->normalizer->normalizeStackTrace($stackTrace) : null;

        $error = new ShopPluginError(
            $this->host,
            $this->platform,
            $this->pluginVersion,
            $this->platformVersion,
            $this->phpVersion,
            $category,
            $severity,
            $context,
            $errorCode,
            $normalizedMessage,
            $callEnv,
            ReportShopPluginError::extractApiEndpoint($apiRequestUrl),
            $apiRequestUrl,
            $apiRequest,
            $apiResponse,
            $normalizedTrace,
            time()
        );

        $logPrefix = "[{$category->value}][{$context->value}]";
        $logContext = array_filter([
            'api_url' => $apiRequestUrl,
            'api_request' => $apiRequest,
            'api_response' => $apiResponse,
            'stack_trace' => $normalizedTrace,
        ], static function ($value) {
            return $value !== null;
        });

        if ($errorsSendingDisabled) {
            $this->logError($logPrefix, $normalizedMessage, $logContext);
        } elseif ($this->outboundQueue !== null) {
            $this->outboundQueue->enqueue(ReportErrorHandler::OPERATION_TYPE, ReportErrorHandler::toQueuePayload($error, $this->serializer));

            $this->logError($logPrefix, $normalizedMessage, $logContext);
        } else {
            try {
                $this->apiClient->sendLoggedError($error);
            } catch (\Throwable $exception) {
                $this->logError($logPrefix, $normalizedMessage, $logContext);
            }
        }
    }

    /**
     * @param string $errorPrefix
     * @param string $errorMessage
     * @param array $context
     */
    public function logError($errorPrefix, $errorMessage, $context = []): void
    {
        try {
            $this->getLogger()->error("$errorPrefix: $errorMessage", $context);
        } catch (\Exception $e) {
            if (FileUtils::isWritable($this->logFilePath)) {
                FileUtils::append($this->logFilePath, "$errorPrefix: $errorMessage");

                if (!empty($context)) {
                    FileUtils::append($this->logFilePath, "\n" . json_encode(SensitiveDataRedactor::redactStructure($context)));
                }

                FileUtils::append($this->logFilePath, "Logger error: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
            }
        }
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
     * @param \Throwable $exception
     * @return ErrorCategory
     */
    public static function classifyException($exception): ErrorCategory
    {
        return ErrorCategory::from((function () use ($exception) {
            switch (true) {
                case $exception instanceof \ArgumentCountError:
                    return ErrorCategory::ExceptionArgCount;
                case $exception instanceof \TypeError:
                    return ErrorCategory::ExceptionTypeError;
                case $exception instanceof \ParseError:
                    return ErrorCategory::ExceptionParseError;
                case $exception instanceof \DivisionByZeroError:
                    return ErrorCategory::ExceptionDivision;
                case $exception instanceof \LogicException:
                    return ErrorCategory::ExceptionLogicError;
                case $exception instanceof \Error:
                    return ErrorCategory::ExceptionError;
                default:
                    return ErrorCategory::ExceptionGeneric;
            }
        })());
    }

    /**
     * @param \Throwable $error
     * @param string $default
     * @return string|null
     */
    public static function resolveSeverity($error, $default = ErrorSeverity::Error): ?string
    {
        if (ApiTransientErrorClassifier::isTransient($error)) {
            return ErrorSeverity::Warning;
        }

        if ($error instanceof RequestValidationError || $error instanceof ResponseValidationError) {
            return null;
        }

        return $default;
    }

    /**
     * @param int $errorType
     * @param string $errorMessage
     * @param string $file
     * @param int $line
     * @return false
     */
    public function errorHandler($errorType, $errorMessage, $file, $line): bool
    {
        if (!($errorType & self::CATCHED_ERRORS_MASK)) {
            return false;
        }

        $errorLevel = $this->getErrorTypeName($errorType);

        $this->sendError(
            ErrorCategory::from(ErrorCategory::PhpError),
            $this->mapErrorSeverity($errorType),
            OperationContext::from(OperationContext::Unknown),
            (string) $errorType,
            "Error $errorLevel in $file:$line: $errorMessage"
        );

        return false;
    }

    /**
     * @param \Throwable $exception
     */
    public function exceptionHandler($exception): void
    {
        try {
            $exceptionClass = (new \ReflectionClass($exception))->getShortName();

            $this->sendError(
                self::classifyException($exception),
                ErrorSeverity::from(ErrorSeverity::Error),
                OperationContext::from(OperationContext::Unknown),
                $exceptionClass,
                sprintf('Exception %s in %s:%d: %s', $exceptionClass, $exception->getFile(), $exception->getLine(), $exception->getMessage()),
                null,
                null,
                null,
                $exception->getTraceAsString()
            );
        } catch (\Throwable $loggingFailure) {
            error_log('Comfino exception logger failed: ' . $loggingFailure->getMessage());
        }

        if ($this->previousExceptionHandler !== null) {
            ($this->previousExceptionHandler)($exception);

            return;
        }

        $this->rethrownExceptionLocation = $exception->getFile() . ':' . $exception->getLine();

        throw $exception;
    }

    /**
     * @param array $error
     * @return bool
     */
    private function isRethrownExceptionError(array $error): bool
    {
        return $error['file'] . ':' . $error['line'] === $this->rethrownExceptionLocation && strncmp($error['message'], 'Uncaught ', strlen('Uncaught ')) === 0;
    }

    /**
     * @param string $path
     * @return string
     */
    private static function normalizePath(string $path): string
    {
        return DIRECTORY_SEPARATOR === '/' ? $path : str_replace(DIRECTORY_SEPARATOR, '/', $path);
    }

    public function init(): void
    {
        if (getenv('COMFINO_DEV_ENV') === 'TRUE' && getenv('COMFINO_FORCE_ERRORS_HANDLING') !== 'TRUE') {
            return;
        }

        static $initialized = false;

        if (!$initialized) {
            set_error_handler([$this, 'errorHandler'], self::CATCHED_ERRORS_MASK);
            $this->previousExceptionHandler = set_exception_handler([$this, 'exceptionHandler']);
            register_shutdown_function([$this, 'shutdown']);

            $initialized = true;
        }
    }

    public function shutdown(): void
    {
        if (($error = error_get_last()) !== null && ($error['type'] & self::CATCHED_ERRORS_MASK) && !$this->isRethrownExceptionError($error)) {
            $errorLevel = $this->getErrorTypeName($error['type']);

            $this->sendError(
                ErrorCategory::from(ErrorCategory::PhpError),
                $this->mapErrorSeverity($error['type']),
                OperationContext::from(OperationContext::Unknown),
                (string) $error['type'],
                "Error $errorLevel in $error[file]:$error[line]: $error[message]"
            );
        }

        restore_error_handler();
        restore_exception_handler();
    }

    /**
     * @param int $errorType
     * @return string
     */
    private function getErrorTypeName(int $errorType): string
    {
        return
            (($errorTypeName = array_key_exists($errorType, self::ERROR_TYPES) ? self::ERROR_TYPES[$errorType] : 'UNKNOWN') === 'UNKNOWN') &&
            (PHP_VERSION_ID < 70400) &&
            $errorType === E_STRICT
                ? 'E_STRICT'
                : $errorTypeName;
    }

    /**
     * @param int $errorType
     * @return ErrorSeverity
     */
    private function mapErrorSeverity(int $errorType): ErrorSeverity
    {
        return ErrorSeverity::from((function () use ($errorType) {
            switch ($errorType) {
                case E_ERROR:
                case E_CORE_ERROR:
                case E_COMPILE_ERROR:
                case E_RECOVERABLE_ERROR:
                case E_PARSE:
                case E_USER_ERROR:
                    return ErrorSeverity::Error;
                case E_WARNING:
                case E_CORE_WARNING:
                case E_COMPILE_WARNING:
                case E_USER_WARNING:
                    return ErrorSeverity::Warning;
                case E_NOTICE:
                case E_USER_NOTICE:
                case E_DEPRECATED:
                case E_USER_DEPRECATED:
                    return ErrorSeverity::Notice;
                default:
                    return ErrorSeverity::Error;
            }
        })());
    }

    /**
     * @return array<string,
     */
    private function extractCallerContext(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $caller = $trace[2] ?? [];

        return [
            'class' => $caller['class'] ?? null,
            'function' => $caller['function'] ?? null,
            'file' => basename($caller['file'] ?? ''),
            'line' => $caller['line'] ?? 0,
        ];
    }

    /**
     * @return string
     */
    private function formatContext(array $context): string
    {
        if (!empty($context['class'])) {
            return sprintf(
                '[%s::%s@%s:%d]',
                substr(strrchr($context['class'], '\\'), 1),
                $context['function'],
                $context['file'],
                $context['line']
            );
        }

        return sprintf('[%s@%s:%d]', $context['function'], $context['file'], $context['line']);
    }
}
