<?php

namespace Comfino;

final class Shop_Plugin_Error
{
    /**
     * @var string
     */
    public $host;

    /**
     * @var string
     */
    public $platform;

    /**
     * @var array
     */
    public $environment;

    /**
     * @var string
     */
    public $error_code;

    /**
     * @var string
     */
    public $error_message;

    /**
     * @var string|null
     */
    public $api_request_url;

    /**
     * @var string|null
     */
    public $api_request;

    /**
     * @var string|null
     */
    public $api_response;

    /**
     * @var string|null
     */
    public $stack_trace;

    /**
     * @param string $host
     * @param string $platform
     * @param array $environment
     * @param string $error_code
     * @param string $error_message
     * @param string|null $api_request_url
     * @param string|null $api_request
     * @param string|null $api_response
     * @param string|null $stack_trace
     */
    public function  __construct(
        string  $host,
        string  $platform,
        array   $environment,
        string  $error_code,
        string  $error_message,
        $api_request_url = null,
        $api_request = null,
        $api_response = null,
        $stack_trace = null
    )
    {
        $this->host = $host;
        $this->platform = $platform;
        $this->environment = $environment;
        $this->error_code = $error_code;
        $this->error_message = $error_message;
        $this->api_request_url = $api_request_url;
        $this->api_request = $api_request;
        $this->api_response = $api_response;
        $this->stack_trace = $stack_trace;
    }
}
