<?php

namespace Comfino;

require_once __DIR__ . '/comfino-shop-plugin-error.php';
require_once __DIR__ . '/comfino-shop-plugin-error-request.php';

class Error_Logger
{
    const ERROR_TYPES = [
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
    public static function log_error(string $error_prefix, string $error_message)
    {
        @file_put_contents(
            __DIR__ . '/../payment_log.log',
            "[" . date('Y-m-d H:i:s') . "] $error_prefix: $error_message\n",
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
        $api_request_url = null,
        $api_request = null,
        $api_response = null,
        $stack_trace = null
    )
    {
        global $wp_version, $wpdb;

        if (preg_match('/Error .*in \/|Exception .*in \//', $error_message) && strpos($error_message, 'plugins/comfino') === false) {
            // Ignore all errors outside the plugin code.
            return;
        }

        $error = new Shop_Plugin_Error(
            Core::get_shop_url(),
            'WooCommerce',
            [
                'plugin_version' => \Comfino_Payment_Gateway::VERSION,
                'shop_version' => WC_VERSION,
                'wordpress_version' => $wp_version,
                'php_version' => PHP_VERSION,
                'server_software' => sanitize_text_field($_SERVER['SERVER_SOFTWARE']),
                'server_name' => sanitize_text_field($_SERVER['SERVER_NAME']),
                'server_addr' => sanitize_text_field($_SERVER['SERVER_ADDR']),
                'database_version' => $wpdb->db_version(),
            ],
            $error_code,
            "$error_prefix: $error_message",
            $api_request_url,
            $api_request,
            $api_response,
            $stack_trace
        );

        if (!Api_Client::send_logged_error($error)) {
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
                $error_message .= "\n" . implode("\n", $request_info);
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
        $log_file_path = __DIR__ . '/../payment_log.log';

        if (file_exists($log_file_path)) {
            $file = new \SplFileObject($log_file_path, 'r');
            $file->seek(PHP_INT_MAX);
            $last_line = $file->key();
            $lines = new \LimitIterator(
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
     * @param \Throwable $exception
     * @return void
     */
    public static function exception_handler(\Throwable $exception)
    {
        self::send_error(
            "Exception " . get_class($exception) . " in {$exception->getFile()}:{$exception->getLine()}",
            $exception->getCode(), $exception->getMessage(),
            null, null, null, $exception->getTraceAsString()
        );
    }

    public static function init()
    {
        if ((defined('WP_DEBUG') && WP_DEBUG === true) || getenv('COMFINO_DEBUG') === 'TRUE') {
            // Disable custom errors handling if WordPress or plugin is in debug mode.
            return;
        }

        static $initialized = false;

        if (!$initialized) {
            set_error_handler([__CLASS__, 'error_handler'], E_ERROR | E_RECOVERABLE_ERROR | E_PARSE);
            set_exception_handler([__CLASS__, 'exception_handler']);
            register_shutdown_function([__CLASS__, 'shutdown']);

            $initialized = true;
        }
    }

    public static function shutdown()
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
