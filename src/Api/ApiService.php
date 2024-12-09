<?php

namespace Comfino\Api;

use Comfino\Api\Dto\Payment\LoanQueryCriteria;
use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Common\Backend\Factory\ApiServiceFactory;
use Comfino\Common\Backend\RestEndpoint\CacheInvalidate;
use Comfino\Common\Backend\RestEndpoint\Configuration;
use Comfino\Common\Backend\RestEndpoint\StatusNotification;
use Comfino\Common\Backend\RestEndpointManager;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Configuration\ConfigManager;
use Comfino\Configuration\SettingsManager;
use Comfino\ErrorLogger;
use Comfino\Extended\Api\Serializer\Json as JsonSerializer;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Main;
use Comfino\Order\OrderManager;
use Comfino\Order\StatusAdapter;
use Comfino\PaymentGateway;
use Comfino\PluginShared\CacheManager;
use Comfino\Shop\Order\Cart;
use Comfino\View\FrontendManager;
use Comfino\View\TemplateManager;
use ComfinoExternal\Psr\Cache\InvalidArgumentException;
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
    /** @var string[] */
    private static $endpointUrls = [];
    /** @var callable[] */
    private static $requestCallbacks = [
        'availableOfferTypes' => [self::class, 'getAvailableOfferTypes'],
        'paywall' => [self::class, 'getPaywall'],
        'paywallItemDetails' => [self::class, 'getPaywallItemDetails'],
        'productDetails' => [self::class, 'getProductDetails'],
    ];

    public static function init(): void
    {
        global $comfino_payment_gateway;

        add_filter(
            'rest_pre_serve_request',
            static function (bool $served, \WP_HTTP_Response $result, \WP_REST_Request $request, \WP_REST_Server $server): bool {
                if (is_string($result->get_data()) && strpos($request->get_route(), 'comfino') !== false) {
                    echo esc_html($result->get_data());

                    $served = true;
                }

                return $served;
            },
            10,
            4
        );

        self::registerWordPressApiEndpoint('availableOfferTypes', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => function (\WP_REST_Request $request): \WP_REST_Response {
                    return self::processRequest('availableOfferTypes', $request);
                },
                'args' => ['product_id' => ['sanitize_callback' => 'absint']],
            ],
        ]);

        self::registerWordPressApiEndpoint('paywall', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => function (\WP_REST_Request $request): \WP_REST_Response {
                    return self::processRequest('paywall', $request);
                },
            ],
        ]);

        self::registerWordPressApiEndpoint('paywallItemDetails', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => function (\WP_REST_Request $request): \WP_REST_Response {
                    return self::processRequest('paywallItemDetails', $request);
                },
            ],
        ]);

        self::registerWordPressApiEndpoint('productDetails', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => function (\WP_REST_Request $request): \WP_REST_Response {
                    return self::processRequest('productDetails', $request);
                },
                'args' => ['loanTypeSelected' => ['sanitize_callback' => 'sanitize_text_field']],
            ],
        ]);

        self::getEndpointManager()->registerEndpoint(
            new StatusNotification(
                'transactionStatus',
                self::registerWordPressApiEndpoint('transactionStatus', [
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
                self::registerWordPressApiEndpoint('configuration', [
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
                ...array_merge(
                    array_values(ConfigManager::getEnvironmentInfo(['shop_version', 'plugin_version', 'plugin_build_ts', 'database_version'])),
                    [array_merge($comfino_payment_gateway->get_plugin_update_details(), ConfigManager::getEnvironmentInfo(['wordpress_version']))]
                )
            )
        );

        self::getEndpointManager()->registerEndpoint(
            new CacheInvalidate(
                'cacheInvalidate',
                self::registerWordPressApiEndpoint('cacheInvalidate', [
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

    public static function registerEndpoints(): void
    {
        self::$endpointUrls = [
            'availableOfferTypes' => '/availableoffertypes(?:/(?P<product_id>\d+))?',
            'paywall' => '/paywall',
            'paywallItemDetails' => '/paywallitemdetails',
            'productDetails' => '/productdetails(?:/(?P<product_id>\d+)/(?P<loanTypeSelected>[A-Z_]+))?',
            'transactionStatus' => '/transactionstatus',
            'configuration' => '/configuration(?:/(?P<vkey>[a-f0-9]+))?',
            'cacheInvalidate' => '/cacheinvalidate',
        ];

        add_action('rest_api_init', [self::class, 'init']);
    }

    public static function getEndpointUrl(string $endpointName): string
    {
        if (($endpoint = self::getEndpointManager()->getEndpointByName($endpointName)) !== null) {
            return $endpoint->getEndpointUrl();
        }

        return self::$endpoints[$endpointName] ?? self::getRestUrl(self::$endpointUrls[$endpointName] ?? '');
    }

    public static function getEndpointPath(string $endpointName): string
    {
        $endpointUrl = self::getEndpointUrl($endpointName);
        $endpointPath = wp_parse_url($endpointUrl, PHP_URL_PATH);
        $endpointParams = wp_parse_url($endpointUrl, PHP_URL_QUERY);

        return $endpointPath . (!empty($endpointParams) ? '?' . $endpointParams : '');
    }

    public static function processRequest(string $endpointName, \WP_REST_Request $request): \WP_REST_Response
    {
        Main::debugLog(
            '[REST API]',
            'processRequest',
            [
                '$endpointName' => $endpointName,
                'METHOD' => $request->get_method(),
                'PARAMS' => $request->get_params(),
                'HEADERS' => $request->get_headers(),
                'BODY' => $request->get_body(),
            ]
        );

        if (isset(self::$endpoints[$endpointName], self::$requestCallbacks[$endpointName])) {
            return call_user_func(self::$requestCallbacks[$endpointName], $request);
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

        $responseBody = json_decode($response->getBody()->getContents(), true);

        $apiResponse->set_status($response->getStatusCode());
        $apiResponse->set_data(!empty($responseBody) ? $responseBody : $response->getReasonPhrase());

        return $apiResponse;
    }

    private static function getRestUrl(string $endpointPath): string
    {
        if (empty($endpointPath)) {
            return '';
        }

        $endpointPath = ltrim($endpointPath, '/');
        $restEndpointPath = 'comfino/';

        if (($argsPos = strpos($endpointPath, '(')) !== false) {
            $restEndpointPath .= substr($endpointPath, 0, $argsPos);
        } else {
            $restEndpointPath .= $endpointPath;
        }

        return get_rest_url(null, $restEndpointPath);
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

    private static function registerWordPressApiEndpoint(string $endpointName, array $endpointCallbacks): string
    {
        register_rest_route(
            'comfino',
            self::$endpointUrls[$endpointName],
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

        self::$endpoints[$endpointName] = self::getRestUrl(self::$endpointUrls[$endpointName]);

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
            return new \WP_REST_Response($availableProductTypes);
        }

        $product = wc_get_product($productId);

        if (!$product) {
            return new \WP_REST_Response($availableProductTypes);
        }

        return new \WP_REST_Response(SettingsManager::getAllowedProductTypes('widget', OrderManager::getShopCartFromProduct($product), true));
    }

    private static function getProductDetails(\WP_REST_Request $request): \WP_REST_Response
    {
        $serializer = new JsonSerializer();

        if (empty($productId = $request->get_param('product_id') ?? '')) {
            return new \WP_REST_Response();
        }

        $product = wc_get_product($productId);

        if (!$product) {
            return new \WP_REST_Response();
        }

        $shopCart = OrderManager::getShopCartFromProduct($product);
        $loanAmount = $shopCart->getTotalValue();
        $loanTypeSelected = $request->get_param('loanTypeSelected') ?? '';

        Main::debugLog(
            '[PRODUCT_DETAILS]',
            'getFinancialProductDetails',
            [
                '$loanAmount' => $loanAmount,
                '$productId' =>$productId,
                '$loanTypeSelected' => $loanTypeSelected,
                '$shopCart' => $shopCart->getAsArray(),
            ]
        );

        try {
            $financialProducts = $serializer->serialize(
                ApiClient::getInstance()->getFinancialProductDetails(
                    new LoanQueryCriteria($loanAmount, null, LoanTypeEnum::from($loanTypeSelected)),
                    new Cart(
                        $shopCart->getCartItems(),
                        $shopCart->getTotalValue(),
                        $shopCart->getDeliveryCost(),
                        $shopCart->getDeliveryNetCost(),
                        $shopCart->getDeliveryTaxRate(),
                        $shopCart->getDeliveryTaxValue()
                    )
                )->financialProducts
            );
        } catch (\Throwable $e) {
            ErrorLogger::sendError(
                'Product details endpoint',
                $e->getCode(),
                $e->getMessage(),
                $e instanceof HttpErrorExceptionInterface ? $e->getUrl() : null,
                $e instanceof HttpErrorExceptionInterface ? $e->getRequestBody() : null,
                $e instanceof HttpErrorExceptionInterface ? $e->getResponseBody() : null,
                $e->getTraceAsString()
            );

            return new \WP_REST_Response($e->getMessage(), $e instanceof HttpErrorExceptionInterface ? $e->getStatusCode() : 500);
        } finally {
            if (($apiRequest = ApiClient::getInstance()->getRequest()) !== null) {
                Main::debugLog(
                    '[PRODUCT_DETAILS_API_REQUEST]',
                    'getFinancialProductDetails',
                    ['$request' => $apiRequest->getRequestBody()]
                );
            }
        }

        return new \WP_REST_Response($financialProducts);
    }

    private static function getPaywall(\WP_REST_Request $request): void
    {
        header('Content-Type: text/html');

        if (!ConfigManager::isEnabled()) {
            echo wp_kses(TemplateManager::renderView('plugin-disabled', 'front'), 'post');

            exit;
        }

        $loanAmount = (int) round(WC()->cart->get_total('edit') * 100);
        $shopCart = OrderManager::getShopCart(WC()->cart, $loanAmount);
        $allowedProductTypes = SettingsManager::getAllowedProductTypes(
            ProductTypesListTypeEnum::LIST_TYPE_PAYWALL,
            $shopCart
        );

        if ($allowedProductTypes === []) {
            // Filters active - all product types disabled.
            echo wp_kses(TemplateManager::renderView('paywall-disabled', 'front'), 'post');

            exit;
        }

        if ($request->has_param('priceModifier') && is_numeric($request->get_param('priceModifier'))) {
            $priceModifier = (float) $request->get_param('priceModifier');

            if ($priceModifier > 0) {
                $loanAmount += ((int) ($priceModifier * 100));
            }
        }

        Main::debugLog(
            '[PAYWALL]',
            'renderPaywall',
            [
                '$loanAmount' => $loanAmount,
                '$allowedProductTypes' => $allowedProductTypes,
                '$shopCart' => $shopCart->getAsArray(),
            ]
        );

        echo wp_kses(
            FrontendManager::getPaywallRenderer()->renderPaywall(new LoanQueryCriteria($loanAmount, null, null, $allowedProductTypes)),
            FrontendManager::getPaywallAllowedHtml()
        );

        if (($apiRequest = ApiClient::getInstance()->getRequest()) !== null) {
            Main::debugLog(
                '[PAYWALL_API_REQUEST]',
                'renderPaywall',
                ['$request' => $apiRequest->getRequestBody()]
            );
        }

        exit;
    }

    private static function getPaywallItemDetails(\WP_REST_Request $request): \WP_REST_Response
    {
        if (!ConfigManager::isEnabled()) {
            TemplateManager::renderView('plugin-disabled', 'front');

            exit;
        }

        $loanAmount = (int) round(WC()->cart->get_total('edit') * 100);
        $loanTypeSelected = $request->get_param('loanTypeSelected');
        $shopCart = OrderManager::getShopCart(WC()->cart, $loanAmount);

        Main::debugLog(
            '[PAYWALL_ITEM_DETAILS]',
            'getPaywallItemDetails',
            ['$loanTypeSelected' => $loanTypeSelected, '$shopCart' => $shopCart->getAsArray()]
        );

        $response = FrontendManager::getPaywallRenderer()
            ->getPaywallItemDetails(
                $loanAmount,
                LoanTypeEnum::from($loanTypeSelected),
                new Cart(
                    $shopCart->getCartItems(),
                    $shopCart->getTotalValue(),
                    $shopCart->getDeliveryCost(),
                    $shopCart->getDeliveryNetCost(),
                    $shopCart->getDeliveryTaxRate(),
                    $shopCart->getDeliveryTaxValue()
                )
            );

        if (($apiRequest = ApiClient::getInstance()->getRequest()) !== null) {
            Main::debugLog(
                '[PAYWALL_ITEM_DETAILS_API_REQUEST]',
                'getPaywallItemDetails',
                ['$request' => $apiRequest->getRequestBody()]
            );
        }

        return new \WP_REST_Response(['listItemData' => $response->listItemData, 'productDetails' => $response->productDetails]);
    }
}
