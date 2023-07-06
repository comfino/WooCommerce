<?php

namespace Comfino;

require_once 'comfino-shop-plugin-error.php';

final class Shop_Plugin_Error_Request
{
    /**
     * @var string
     */
    public $error_details;

    /**
     * @var string
     */
    public $hash;

    /**
     * @param Shop_Plugin_Error $shop_plugin_error
     * @param string $hash_key
     * @return bool
     */
    public function prepare_request(Shop_Plugin_Error $shop_plugin_error, string $hash_key): bool
    {
        $error_details_array = [
            'host' => $shop_plugin_error->host,
            'platform' => $shop_plugin_error->platform,
            'environment' => $shop_plugin_error->environment,
            'error_code' => $shop_plugin_error->error_code,
            'error_message' => $shop_plugin_error->error_message,
            'api_request_url' => $shop_plugin_error->api_request_url,
            'api_request' => $shop_plugin_error->api_request,
            'api_response' => $shop_plugin_error->api_response,
            'stack_trace' => $shop_plugin_error->stack_trace
        ];

        if (($encoded_error_details = json_encode($error_details_array)) === false) {
            return false;
        }

        if (($errorDetails = gzcompress($encoded_error_details, 9)) === false) {
            return false;
        }

        $this->error_details = base64_encode($errorDetails);
        $this->hash = hash_hmac('sha256', $this->error_details, $hash_key);

        return true;
    }
}
