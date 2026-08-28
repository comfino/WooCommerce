<?php /** @noinspection PhpExpressionResultUnusedInspection */

namespace Comfino\Api;

use Comfino\Common\Backend\Factory\ApiServiceFactory;
use Comfino\Common\Backend\RestEndpoint\CacheInvalidate;
use Comfino\Common\Backend\RestEndpoint\Configuration;
use Comfino\Common\Backend\RestEndpoint\StatusNotification;
use Comfino\Common\Backend\RestEndpointManager;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Configuration\ConfigManager;
use Comfino\DebugLogger;
use Comfino\Order\StatusAdapter;
use Comfino\PaymentGateway;
use Comfino\PluginShared\CacheManager;
use Comfino\View\SettingsForm;
use ComfinoExternal\Psr\Http\Message\ServerRequestInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class ApiService
{
    /**
     * Minimal length of an API key accepted as a request signature secret.
     *
     * The error reporting request builder already refuses to key a MAC with anything shorter
     * (ReportShopPluginError::MIN_HASH_KEY_LENGTH), so a value below this length cannot be a real Comfino API key.
     */
    private const MIN_API_KEY_LENGTH = 16;

    /** @var RestEndpointManager */
    private static $endpointManager;
    /** @var string[] */
    private static $endpoints = [];
    /** @var string[] */
    private static $endpointUrls = [];
    /** @var callable[] */
    private static $requestCallbacks = [];

    /**
     * Removes API keys which must never be accepted as a request signature secret.
     *
     * An unconfigured key slot holds null or an empty string, and a signature calculated with such a value
     * collapses to a hash of the request body alone - something any caller can compute without knowing any
     * secret at all. Empty slots are therefore dropped before they can be used for signature verification.
     *
     * @param mixed[] $apiKeys
     *
     * @return string[]
     */
    public static function filterApiKeys(array $apiKeys): array
    {
        return array_values(
            array_filter(
                $apiKeys,
                static function ($apiKey): bool {
                    return is_string($apiKey) && trim($apiKey) !== '' && strlen($apiKey) >= self::MIN_API_KEY_LENGTH;
                }
            )
        );
    }

    public static function init(): void
    {
        global $comfino_payment_gateway;

        add_filter(
            'rest_pre_serve_request',
            static function (bool $served, \WP_HTTP_Response $result, \WP_REST_Request $request, \WP_REST_Server $server): bool {
                if (is_string($result->get_data()) && strpos($request->get_route(), PaymentGateway::GATEWAY_ID) !== false) {
                    echo esc_html($result->get_data());

                    $served = true;
                }

                return $served;
            },
            10,
            4
        );

        /* Public frontend endpoints - accessible to everyone without authentication.
           These are called from frontend JavaScript for product widgets and checkout paywall. */

        self::registerWordPressApiEndpoint(
            'availableOfferTypes',
            [
                [
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => function (\WP_REST_Request $request): \WP_REST_Response {
                        return self::processRequest('availableOfferTypes', $request);
                    },
                    'args' => ['product_id' => ['sanitize_callback' => 'absint']],
                    'permission_callback' => '__return_true',
                ],
            ]
        );

        /* Comfino API callback endpoints - require CR-Signature authentication.
           These are server-to-server requests from Comfino API (no WordPress user context).
           Authentication is performed by RestEndpointManager::verifyRequest() using SHA3-256 HMAC signature verification. */

        self::getEndpointManager()->registerEndpoint(
            new StatusNotification(
                'transactionStatus',
                self::registerWordPressApiEndpoint(
                    'transactionStatus',
                    [
                        [
                            'methods' => \WP_REST_Server::EDITABLE,
                            'callback' => function (\WP_REST_Request $request): \WP_REST_Response {
                                return self::processRequest('transactionStatus', $request);
                            },
                            'permission_callback' => '__return_true',
                        ],
                    ]
                ),
                StatusManager::getInstance(new StatusAdapter()),
                ConfigManager::getForbiddenStatuses(),
                ConfigManager::getIgnoredStatuses()
            )
        );

        self::getEndpointManager()->registerEndpoint(
            new Configuration(
                'configuration',
                self::registerWordPressApiEndpoint(
                    'configuration',
                    [
                        [
                            'methods' => \WP_REST_Server::READABLE,
                            'callback' =>  function (\WP_REST_Request $request): \WP_REST_Response {
                                return self::processRequest('configuration', $request);
                            },
                            'args' => ['vkey' => ['sanitize_callback' => 'sanitize_key']],
                            'permission_callback' => '__return_true',
                        ],
                        [
                            'methods' => \WP_REST_Server::EDITABLE,
                            'callback' => function (\WP_REST_Request $request): \WP_REST_Response {
                                return self::processRequest('configuration', $request);
                            },
                            'permission_callback' => '__return_true',
                        ],
                    ]
                ),
                ConfigManager::getInstance(),
                DebugLogger::getLoggerInstance(),
                'WooCommerce',
                ...array_merge(
                    array_values(
                        ConfigManager::getEnvironmentInfo(
                            ['shop_version', 'plugin_version', 'plugin_build_ts', 'database_version']
                        )
                    ),
                    [SettingsForm::DEBUG_LOG_NUM_LINES], // $debugLogNumLines
                    [
                        array_merge($comfino_payment_gateway->get_plugin_update_details(),
                        ConfigManager::getEnvironmentInfo(['wordpress_version'])),
                    ], // $shopExtraVariables
                    [
                        static function (): ?array {
                            return \Comfino\Telemetry\ShopEnvironmentReporter::getReportArray();
                        },
                    ] // $shopEnvironmentReportProvider
                )
            )
        );

        self::getEndpointManager()->registerEndpoint(
            new CacheInvalidate(
                'cacheInvalidate',
                self::registerWordPressApiEndpoint(
                    'cacheInvalidate',
                    [
                        [
                            'methods' => \WP_REST_Server::EDITABLE,
                            'callback' => function (\WP_REST_Request $request): \WP_REST_Response {
                                return self::processRequest('cacheInvalidate', $request);
                            },
                            'permission_callback' => '__return_true',
                        ],
                    ]
                ),
                CacheManager::getCachePool()
            )
        );
    }

    public static function registerEndpoints(): void
    {
        self::$endpointUrls = [
            'availableOfferTypes' => '/availableoffertypes(?:/(?P<product_id>\d+))?',
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
        $endpointManager = self::getEndpointManager();

        DebugLogger::logEvent(
            '[REST API request]',
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

        if (empty($endpointManager->getRegisteredEndpoints())) {
            return new \WP_REST_Response('Endpoint manager not initialized.', 503);
        }

        $apiResponse = new \WP_REST_Response();

        $response = $endpointManager->processRequest($endpointName, self::createServerRequest($request));

        foreach ($response->getHeaders() as $headerName => $headerValues) {
            foreach ($headerValues as $headerValue) {
                $apiResponse->header($headerName, $headerValue, false);
            }
        }

        $responseBody = json_decode($response->getBody()->getContents(), true);

        $apiResponse->set_status($response->getStatusCode());
        $apiResponse->set_data(!empty($responseBody) ? $responseBody : $response->getReasonPhrase());

        if (ConfigManager::isDebugMode() && $response->getStatusCode() !== 200) {
            DebugLogger::logEvent(
                '[REST API response]',
                'processRequest',
                [
                    '$endpointName' => $endpointName,
                    'RECEIVED-CR-SIGNATURE-PREFIX' => substr((string) $endpointManager->getReceivedCrSignature(), 0, 8),
                    'HEADERS' => $response->getHeaders(),
                    'STATUS' => $response->getStatusCode(),
                    'BODY' => $response->getBody()->getContents(),
                ]
            );
        }

        return $apiResponse;
    }

    /**
     * Permission callback for admin-only endpoints (future use).
     * Requires WordPress admin capabilities.
     *
     * Note: This is NOT used for webhook/callback endpoints which authenticate via CR-Signature.
     * Use this only for admin panel features that modify plugin settings or trigger diagnostic actions.
     *
     * @return bool True if user has WooCommerce management capabilities.
     */
    private static function permissionAdminOnly(): bool
    {
        // For future admin-only endpoints (e.g., plugin diagnostics, manual actions).
        return current_user_can('manage_woocommerce');
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
            $apiKeys = [ConfigManager::getConfigurationValue('COMFINO_API_KEY')];

            /* The test environment key is accepted only while the shop actually runs in sandbox mode. Accepting
               both keys at once doubles the signature verification surface without any functional gain, and it
               mirrors ConfigManager::getApiKey(), which selects a single key by the same flag. */
            if (ConfigManager::isSandboxMode()) {
                $apiKeys[] = ConfigManager::getConfigurationValue('COMFINO_SANDBOX_API_KEY');
            }

            self::$endpointManager = (new ApiServiceFactory())->createService(
                'WooCommerce',
                WC_VERSION,
                PaymentGateway::VERSION,
                self::filterApiKeys($apiKeys)
            );
        }

        return self::$endpointManager;
    }

    private static function registerWordPressApiEndpoint(string $endpointName, array $endpointCallbacks): string
    {
        register_rest_route(
            PaymentGateway::GATEWAY_ID,
            self::$endpointUrls[$endpointName],
            array_map(
                static function (array $endpointCallback): array {
                    $endpointParams = [
                        'methods' => $endpointCallback['methods'],
                        'callback' => $endpointCallback['callback'],
                        'permission_callback' => $endpointCallback['permission_callback'] ?? '__return_true',
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

}
