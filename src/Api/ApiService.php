<?php

namespace Comfino\Api;

use Comfino\CacheManager;
use Comfino\Common\Backend\Factory\ApiServiceFactory;
use Comfino\Common\Backend\RestEndpoint\CacheInvalidate;
use Comfino\Common\Backend\RestEndpoint\Configuration;
use Comfino\Common\Backend\RestEndpoint\StatusNotification;
use Comfino\Common\Backend\RestEndpointManager;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Configuration\ConfigManager;
use Comfino\Core;
use Comfino\Order\StatusAdapter;
use const Comfino\WC_VERSION;

if (!defined('ABSPATH')) {
    exit;
}

final class ApiService
{
    /** @var RestEndpointManager */
    private static $endpointManager;
    /** @var array */
    private static $endpoints = [];

    public static function init(): void
    {
        self::registerWordPressApiEndpoint('availableOfferTypes', '/availableoffertypes(?:/(?P<product_id>\d+))?', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [Core::class, 'get_available_offer_types'],
                'args' => ['product_id' => ['sanitize_callback' => 'absint']],
            ],
        ]);

        self::$endpointManager = (new ApiServiceFactory())->createService(
            'WooCommerce',
            WC_VERSION,
            \Comfino_Payment_Gateway::VERSION,
            [
                ConfigManager::getConfigurationValue('COMFINO_API_KEY'),
                ConfigManager::getConfigurationValue('COMFINO_SANDBOX_API_KEY'),
            ]
        );

        self::$endpointManager->registerEndpoint(
            new StatusNotification(
                'transactionStatus',
                self::registerWordPressApiEndpoint('transactionStatus', 'transactionstatus', [
                    [
                        'methods' => \WP_REST_Server::EDITABLE,
                        'callback' => [Core::class, 'process_notification'],
                    ],
                ]),
                StatusManager::getInstance(new StatusAdapter()),
                ConfigManager::getForbiddenStatuses(),
                ConfigManager::getIgnoredStatuses()
            )
        );

        self::$endpointManager->registerEndpoint(
            new Configuration(
                'configuration',
                self::registerWordPressApiEndpoint('configuration', '/configuration(?:/(?P<vkey>[a-f0-9]+))?', [
                    [
                        'methods' => \WP_REST_Server::READABLE,
                        'callback' => [Core::class, 'get_configuration'],
                        'args' => ['vkey' => ['sanitize_callback' => 'sanitize_key']],
                    ],
                    [
                        'methods' => \WP_REST_Server::EDITABLE,
                        'callback' => [Core::class, 'update_configuration'],
                    ],
                ]),
                ConfigManager::getInstance(),
                'WooCommerce',
                ...array_values(
                    ConfigManager::getEnvironmentInfo(['shop_version', 'plugin_version', 'database_version'])
                )
            )
        );

        self::$endpointManager->registerEndpoint(
            new CacheInvalidate(
                'cacheInvalidate',
                self::registerWordPressApiEndpoint('cacheInvalidate', 'cacheinvalidate', [
                    [
                        'methods' => \WP_REST_Server::EDITABLE,
                        'callback' => [Core::class, 'cache_invalidate'],
                    ],
                ]),
                CacheManager::getCachePool()
            )
        );
    }

    public static function getControllerUrl(
        \PaymentModule $module,
        string $controllerName,
        array $params = [],
        bool $withLangId = true
    ): string {
        $url = \Context::getContext()->link->getModuleLink($module->name, $controllerName, $params, true);

        return $withLangId ? $url : preg_replace('/&?id_lang=\d+&?/', '', $url);
    }

    public static function getEndpointUrl(string $endpointName): string
    {
        if (($endpoint = self::$endpointManager->getEndpointByName($endpointName)) !== null) {
            return $endpoint->getEndpointUrl();
        }

        return self::$endpoints[$endpointName] ?? '';
    }

    public static function processRequest(string $endpointName): string
    {
        if (self::$endpointManager === null || empty(self::$endpointManager->getRegisteredEndpoints())) {
            http_response_code(503);

            return 'Endpoint manager not initialized.';
        }

        $response = self::$endpointManager->processRequest($endpointName);

        foreach ($response->getHeaders() as $headerName => $headerValues) {
            foreach ($headerValues as $headerValue) {
                header(sprintf('%s: %s', $headerName, $headerValue), false);
            }
        }

        $responseBody = $response->getBody()->getContents();

        http_response_code($response->getStatusCode());

        return !empty($responseBody) ? $responseBody : $response->getReasonPhrase();
    }

    private static function registerWordPressApiEndpoint(string $endpointName, string $endpointPath, array $endpointCallbacks): string
    {
        register_rest_route(
            'comfino',
            "/$endpointPath",
            array_map(
                static function (array $endpointCallback): array {
                    $endpointParams = [
                        'methods' => $endpointCallback['methods'],
                        'callback' => $endpointCallback['callback'],
                        'permission_callback' => '__return_true',
                    ];

                    if (isset($endpointCallback['args'])) {
                        $endpointParams['args'] = $endpointCallback['args'];
                    }

                    return $endpointParams;
                },
                $endpointCallbacks
            )
        );

        $restEndpointPath = 'comfino/';

        if (($argsPos = strpos($endpointPath, '(')) !== false) {
            $restEndpointPath .= substr($endpointPath, 0, $argsPos);
        } else {
            $restEndpointPath .= $endpointPath;
        }

        self::$endpoints[$endpointName] = get_rest_url(null, $restEndpointPath);

        return self::$endpoints[$endpointName];
    }
}
