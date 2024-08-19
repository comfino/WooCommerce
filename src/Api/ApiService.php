<?php

namespace Comfino\Api;

use Comfino\Api\Dto\Payment\LoanQueryCriteria;
use Comfino\CacheManager;
use Comfino\Common\Backend\Factory\ApiServiceFactory;
use Comfino\Common\Backend\RestEndpoint\CacheInvalidate;
use Comfino\Common\Backend\RestEndpoint\Configuration;
use Comfino\Common\Backend\RestEndpoint\StatusNotification;
use Comfino\Common\Backend\RestEndpointManager;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Configuration\ConfigManager;
use Comfino\Configuration\SettingsManager;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Order\OrderManager;
use Comfino\Order\StatusAdapter;
use Comfino\PaymentGateway;
use Comfino\View\FrontendManager;
use Comfino\View\TemplateManager;
use ComfinoExternal\Psr\Http\Message\ServerRequestInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class ApiService
{
    /** @var RestEndpointManager */
    private static $endpointManager;
    /** @var string[] */
    private static $endpoints = [];
    /** @var callable[] */
    private static $requestCallbacks = [
        'availableOfferTypes' => [self::class, 'getAvailableOfferTypes'],
        'paywall' => [self::class, 'getPaywall'],
    ];

    public static function init(): void
    {
        self::registerWordPressApiEndpoint('availableOfferTypes', '/availableoffertypes(?:/(?P<product_id>\d+))?', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => function (\WP_REST_Request $request): \WP_REST_Response {
                    return self::processRequest('availableOfferTypes', $request);
                },
                'args' => ['product_id' => ['sanitize_callback' => 'absint']],
            ],
        ]);

        self::registerWordPressApiEndpoint('paywall', '/paywall', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => function (\WP_REST_Request $request): \WP_REST_Response {
                    return self::processRequest('paywall', $request);
                },
            ],
        ]);

        self::getEndpointManager()->registerEndpoint(
            new StatusNotification(
                'transactionStatus',
                self::registerWordPressApiEndpoint('transactionStatus', 'transactionstatus', [
                    [
                        'methods' => \WP_REST_Server::EDITABLE,
                        'callback' => function (\WP_REST_Request $request): \WP_REST_Response {
                            return self::processRequest('transactionStatus', $request);
                        },
                    ],
                ]),
                StatusManager::getInstance(new StatusAdapter()),
                ConfigManager::getForbiddenStatuses(),
                ConfigManager::getIgnoredStatuses()
            )
        );

        self::getEndpointManager()->registerEndpoint(
            new Configuration(
                'configuration',
                self::registerWordPressApiEndpoint('configuration', '/configuration(?:/(?P<vkey>[a-f0-9]+))?', [
                    [
                        'methods' => \WP_REST_Server::READABLE,
                        'callback' =>  function (\WP_REST_Request $request): \WP_REST_Response {
                            return self::processRequest('configuration', $request);
                        },
                        'args' => ['vkey' => ['sanitize_callback' => 'sanitize_key']],
                    ],
                    [
                        'methods' => \WP_REST_Server::EDITABLE,
                        'callback' => function (\WP_REST_Request $request): \WP_REST_Response {
                            return self::processRequest('configuration', $request);
                        },
                    ],
                ]),
                ConfigManager::getInstance(),
                'WooCommerce',
                ...array_values(
                    ConfigManager::getEnvironmentInfo(['shop_version', 'plugin_version', 'database_version'])
                )
            )
        );

        self::getEndpointManager()->registerEndpoint(
            new CacheInvalidate(
                'cacheInvalidate',
                self::registerWordPressApiEndpoint('cacheInvalidate', 'cacheinvalidate', [
                    [
                        'methods' => \WP_REST_Server::EDITABLE,
                        'callback' => function (\WP_REST_Request $request): \WP_REST_Response {
                            return self::processRequest('cacheInvalidate', $request);
                        },
                    ],
                ]),
                CacheManager::getCachePool()
            )
        );
    }

    public static function getEndpointUrl(string $endpointName): string
    {
        if (($endpoint = self::getEndpointManager()->getEndpointByName($endpointName)) !== null) {
            return $endpoint->getEndpointUrl();
        }

        return self::$endpoints[$endpointName] ?? '';
    }

    public static function processRequest(string $endpointName, \WP_REST_Request $request): \WP_REST_Response
    {
        if (isset(self::$endpoints[$endpointName])) {
            return isset(self::$requestCallbacks[$endpointName])
                ? call_user_func(self::$requestCallbacks[$endpointName], $request)
                : new \WP_REST_Response();
        }

        if (empty(self::getEndpointManager()->getRegisteredEndpoints())) {
            return new \WP_REST_Response('Endpoint manager not initialized.', 503);
        }

        $apiResponse = new \WP_REST_Response();

        $response = self::getEndpointManager()->processRequest($endpointName, self::createServerRequest($request));

        foreach ($response->getHeaders() as $headerName => $headerValues) {
            foreach ($headerValues as $headerValue) {
                $apiResponse->header($headerName, $headerValue, false);
            }
        }

        $responseBody = $response->getBody()->getContents();

        $apiResponse->set_status($response->getStatusCode());
        $apiResponse->set_data(!empty($responseBody) ? $responseBody : $response->getReasonPhrase());

        return $apiResponse;
    }

    private static function getEndpointManager(): RestEndpointManager
    {
        if (self::$endpointManager === null) {
            self::$endpointManager = (new ApiServiceFactory())->createService(
                'WooCommerce',
                WC_VERSION,
                PaymentGateway::VERSION,
                [
                    ConfigManager::getConfigurationValue('COMFINO_API_KEY'),
                    ConfigManager::getConfigurationValue('COMFINO_SANDBOX_API_KEY'),
                ]
            );
        }

        return self::$endpointManager;
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

    private static function createServerRequest(\WP_REST_Request $request): ?ServerRequestInterface
    {
        return count($requestParams = $request->get_params())
            ? self::getEndpointManager()->getServerRequest()->withQueryParams($requestParams)
            : null;
    }

    private static function getAvailableOfferTypes(\WP_REST_Request $request): \WP_REST_Response
    {
        $availableProductTypes = SettingsManager::getProductTypesStrings(ProductTypesListTypeEnum::LIST_TYPE_WIDGET);

        if (empty($productId = $request->get_param('product_id') ?? '')) {
            return new \WP_REST_Response($availableProductTypes, 200);
        }

        $product = wc_get_product($productId);

        if (!$product) {
            return new \WP_REST_Response($availableProductTypes, 200);
        }

        return new \WP_REST_Response(
            SettingsManager::getAllowedProductTypes('widget', OrderManager::getShopCartFromProduct($product), true),
            200
        );
    }

    private static function getPaywall(\WP_REST_Request $request): void
    {
        header('Content-Type: text/html');

        if (!ConfigManager::isEnabled()) {
            echo TemplateManager::renderView('module_disabled', 'front');

            exit;
        }

        $loanAmount = (int) (WC()->cart->get_total('edit') * 100);
        $allowedProductTypes = SettingsManager::getAllowedProductTypes(
            ProductTypesListTypeEnum::LIST_TYPE_PAYWALL,
            OrderManager::getShopCart(WC()->cart, $loanAmount)
        );

        if ($allowedProductTypes === []) {
            // Filters active - all product types disabled.
            echo TemplateManager::renderView('paywall_disabled', 'front');

            exit;
        }

        if ($request->has_param('priceModifier') && is_numeric($request->get_param('priceModifier'))) {
            $priceModifier = (float) $request->get_param('priceModifier');

            if ($priceModifier > 0) {
                $loanAmount += ((int) ($priceModifier * 100));
            }
        }

        echo FrontendManager::getPaywallRenderer()
            ->renderPaywall(new LoanQueryCriteria($loanAmount, null, null, $allowedProductTypes));

        exit;
    }
}
