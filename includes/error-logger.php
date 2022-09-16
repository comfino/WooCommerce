<?php

require_once 'shop-plugin-error.php';
require_once 'shop-plugin-error-request.php';

class ErrorLogger
{
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
        E_STRICT => 'E_STRICT',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED => 'E_DEPRECATED',
        E_USER_DEPRECATED => 'E_USER_DEPRECATED'
    ];

    /**
     * @param string $error_prefix
     * @param string $error_message
     * @return void
     */
    public static function log_error(string $error_prefix, string $error_message): void
    {
        @file_put_contents(
            __DIR__.'/../payment_log.log',
            "[".date('Y-m-d H:i:s')."] $error_prefix: $error_message\n",
            FILE_APPEND
        );
    }

    /**
     * @param string $error_prefix
     * @param string $error_code
     * @param string $error_message
     * @param string|null $api_request_url
     * @param string|null $api_request
     * @param string|null $api_response
     * @param string|null $stack_trace
     * @return void
     */
    public static function send_error(
        string  $error_prefix,
        string  $error_code,
        string  $error_message,
        ?string $api_request_url = null,
        ?string $api_request = null,
        ?string $api_response = null,
        ?string $stack_trace = null
    ): void
    {
        global $wp_version, $wpdb;

        $url_parts = parse_url(get_permalink(wc_get_page_id('shop')));

        $error = new ShopPluginError(
            $url_parts['host'].(isset($url_parts['port']) ? ':'.$url_parts['port'] : ''),
            'WooCommerce',
            [
                'plugin_version' => ComfinoPaymentGateway::VERSION,
                'shop_version' => WC_VERSION,
                'wordpress_version' => $wp_version,
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'],
                'server_name' => $_SERVER['SERVER_NAME'],
                'server_addr' => $_SERVER['SERVER_ADDR'],
                'database_version' => $wpdb->db_version()
            ],
            $error_code,
            "$error_prefix: $error_message",
            $api_request_url,
            $api_request,
            $api_response,
            $stack_trace
        );

        if (!Comfino_Gateway::send_logged_error($error)) {
            $request_info = [];

            if ($api_request_url !== null) {
                $request_info[] = "API URL: $api_request_url";
            }

            if ($api_request !== null) {
                $request_info[] = "API request: $api_request";
            }

            if ($api_response !== null) {
                $request_info[] = "API response: $api_response";
            }

            if (count($request_info)) {
                $error_message .= "\n".implode("\n", $request_info);
            }

            if ($stack_trace !== null) {
                $error_message .= "\nStack trace: $stack_trace";
            }

            self::log_error($error_prefix, $error_message);
        }
    }

    /**
     * @param int $num_lines
     * @return string
     */
    public static function get_error_log(int $num_lines): string
    {
        $errors_log = '';
        $log_file_path = __DIR__.'/../payment_log.log';

        if (file_exists($log_file_path)) {
            $file = new SplFileObject($log_file_path, 'r');
            $file->seek(PHP_INT_MAX);
            $last_line = $file->key();
            $lines = new LimitIterator(
                $file,
                $last_line > $num_lines ? $last_line - $num_lines : 0,
                $last_line
            );
            $errors_log = implode('', iterator_to_array($lines));
        }

        return $errors_log;
    }

    /**
     * @param int $err_no
     * @param string $err_msg
     * @param string $file
     * @param int $line
     * @return bool
     */
    public static function error_handler(int $err_no, string $err_msg, string $file, int $line): bool
    {
        $errorType = self::get_error_type_name($err_no);
        self::send_error("Error $errorType in $file:$line", $err_no, $err_msg);

        return false;
    }

    /**
     * @param Throwable $exception
     * @return void
     */
    public static function exception_handler(Throwable $exception): void
    {
        self::send_error(
            "Exception ".get_class($exception)." in {$exception->getFile()}:{$exception->getLine()}",
            $exception->getCode(), $exception->getMessage(),
            null, null, null, $exception->getTraceAsString()
        );
    }

    public static function init(): void
    {
        static $initialized = false;

        if (!$initialized) {
            set_error_handler(['ErrorLogger', 'error_handler'], E_ERROR | E_RECOVERABLE_ERROR | E_PARSE);
            set_exception_handler(['ErrorLogger', 'exception_handler']);
            register_shutdown_function(['ErrorLogger', 'shutdown']);

            $initialized = true;
        }
    }

    public static function shutdown(): void
    {
        if (($error = error_get_last()) !== null && ($error['type'] & (E_ERROR | E_RECOVERABLE_ERROR | E_PARSE))) {
            $errorType = self::get_error_type_name($error['type']);
            self::send_error("Error $errorType in $error[file]:$error[line]", $error['type'], $error['message']);
        }

        restore_error_handler();
        restore_exception_handler();
    }

    /**
     * @param int $error_type
     * @return string
     */
    private static function get_error_type_name(int $error_type): string
    {
        return array_key_exists($error_type, self::ERROR_TYPES) ? self::ERROR_TYPES[$error_type] : 'UNKNOWN';
    }
}
