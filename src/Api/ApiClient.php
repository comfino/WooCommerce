<?php

namespace Comfino\Api;

use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\TooManyRequests;
use Comfino\Common\Backend\Factory\ApiClientFactory;
use Comfino\Common\Backend\Log\ResponseBodySummarizer;
use Comfino\Common\Exception\ConnectionTimeout;
use Comfino\Common\Frontend\FrontendHelper;
use Comfino\Configuration\ConfigManager;
use Comfino\DebugLogger;
use Comfino\ErrorLogger;
use Comfino\Extended\Api\Dto\Plugin\OperationContext;
use Comfino\Main;
use Comfino\PaymentGateway;
use ComfinoExternal\Psr\Http\Client\NetworkExceptionInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class ApiClient
{
    private const CHECKOUT_TRACK_ID_COOKIE = 'comfino_checkout_track_id';
    private const CHECKOUT_TRACK_ID_COOKIE_TTL = 900;
    private const CHECKOUT_TRACK_ID_PATTERN = '/^[A-Za-z0-9_.:-]{1,128}$/';
    private const JOURNEY_TRACK_ID_COOKIE = 'comfino_journey_track_id';

    /* Plugin bounds for the remotely writable transport options, tighter than the shared client's own floor. Retries
       double the timeouts, so the limits keep a single API call from stalling a page for minutes. */
    public const MAX_CONNECT_TIMEOUT = 5;
    public const MAX_TRANSFER_TIMEOUT = 15;
    public const MAX_CONNECT_ATTEMPTS = 3;

    /** @var \Comfino\Common\Api\Client */
    private static $apiClient;

    public static function getInstance(?bool $sandboxMode = null, ?string $apiKey = null): \Comfino\Common\Api\Client
    {
        if ($sandboxMode === null) {
            $sandboxMode = ConfigManager::isSandboxMode();
        }

        if ($apiKey === null) {
            if ($sandboxMode) {
                $apiKey = (string) ConfigManager::getConfigurationValue('COMFINO_SANDBOX_API_KEY');
            } else {
                $apiKey = (string) ConfigManager::getConfigurationValue('COMFINO_API_KEY');
            }
        }

        if (self::$apiClient === null) {
            [$connectionTimeout, $transferTimeout, $connectionMaxNumAttempts] = self::getTransportSettings();

            self::$apiClient = (new ApiClientFactory())->createClient(
                $apiKey,
                sprintf(
                    'WC Comfino [%s], WP [%s], WC [%s], PHP [%s], %s',
                    ...array_merge(
                        array_values(ConfigManager::getEnvironmentInfo([
                            'plugin_version',
                            'wordpress_version',
                            'shop_version',
                            'php_version',
                        ])),
                        [Main::getShopDomain()]
                    )
                ),
                ConfigManager::getApiHost(),
                Main::getShopLanguage(),
                $connectionTimeout,
                $transferTimeout,
                $connectionMaxNumAttempts,
                self::getDevEnvCurlOptions()
            );

            self::$apiClient->setClientHostName(Main::getShopDomain());
            self::$apiClient->addCustomHeader('Comfino-Build-Timestamp', (string) PaymentGateway::BUILD_TS);
        } else {
            self::$apiClient->setCustomApiHost(ConfigManager::getApiHost());
            self::$apiClient->setApiKey($apiKey);
            self::$apiClient->setApiLanguage(Main::getShopLanguage());
        }

        if ($sandboxMode) {
            self::$apiClient->enableSandboxMode();
        } else {
            self::$apiClient->disableSandboxMode();
        }

        return self::$apiClient;
    }

    /**
     * Clamps the transport options to the plugin bounds, whatever value is stored (the options are remotely writable).
     * The transfer timeout always stays above the connect timeout, so the shared client does not rewrite it. Requests
     * that only render storefront pages make a single attempt: a failed read is cached and must not stall the page.
     *
     * @return int[] [connect timeout, transfer timeout, connect attempts]
     */
    public static function getTransportSettings(?bool $storefrontRequest = null): array
    {
        $connectionTimeout = max(1, min(self::MAX_CONNECT_TIMEOUT, (int) ConfigManager::getConfigurationValue('COMFINO_API_CONNECT_TIMEOUT', 1)));
        $transferTimeout = max(
            $connectionTimeout + 1,
            min(self::MAX_TRANSFER_TIMEOUT, (int) ConfigManager::getConfigurationValue('COMFINO_API_TIMEOUT', 3))
        );
        $connectionMaxNumAttempts = max(
            1,
            min(self::MAX_CONNECT_ATTEMPTS, (int) ConfigManager::getConfigurationValue('COMFINO_API_CONNECT_NUM_ATTEMPTS', 3))
        );

        if ($storefrontRequest ?? self::isStorefrontRenderRequest()) {
            $connectionMaxNumAttempts = 1;
        }

        return [$connectionTimeout, $transferTimeout, $connectionMaxNumAttempts];
    }

    /**
     * Tells whether the current request only renders storefront pages or fragments (product page, cart, checkout
     * refresh). Order submission, the admin panel, cron and the plugin's own REST endpoints keep the configured retries.
     */
    private static function isStorefrontRenderRequest(): bool
    {
        if ((function_exists('is_admin') && is_admin()) || (function_exists('wp_doing_cron') && wp_doing_cron())) {
            return false;
        }

        $requestUri = Main::getCurrentUrl();

        if ($requestUri === '' || strpos($requestUri, '/' . PaymentGateway::GATEWAY_ID . '/') !== false) {
            return false;
        }

        // Read-only routing checks, no state change: no nonce involved.
        $wcAjaxAction = sanitize_key(wp_unslash($_GET['wc-ajax'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $requestMethod = sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if ($wcAjaxAction === 'checkout' || isset($_POST['woocommerce_pay'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            return false;
        }

        if ($requestMethod === 'POST' && strpos($requestUri, '/wc/store/') !== false && strpos($requestUri, '/checkout') !== false) {
            return false;
        }

        return true;
    }

    /**
     * The shared client restricts cURL to HTTPS. A local development API host set via COMFINO_DEV_API_HOST may be plain
     * HTTP, so HTTP is allowed again for that case only: dev environment variable set to TRUE, dev env vars option
     * active and an http:// API host. Production stays HTTPS-only.
     */
    private static function getDevEnvCurlOptions(): array
    {
        if (!ConfigManager::useDevEnvVars() || stripos((string) ConfigManager::getApiHost(), 'http://') !== 0) {
            return [];
        }

        return [
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ];
    }

    /**
     * Pins this instance's trackId to the checkout-scoped cookie value, so a checkout-page paywall render and the
     * later separate order-create request share the same trackId. Checkout-only: never call this from product-page
     * rendering, where a fresh trackId per page load is still correct behavior.
     *
     * When the merchant enabled statistics consent, a valid SDK-owned journey cookie (set only with the shopper's
     * analytics consent) takes precedence, so product-page widget events and checkout share one trackId. The journey
     * cookie is never read with the setting off, and never written here.
     */
    public static function pinCheckoutTrackId(): void
    {
        $client = self::getInstance();

        if (ConfigManager::isPaywallTrackingEnabled() && self::isValidTrackIdCookie(self::JOURNEY_TRACK_ID_COOKIE)) {
            $client->setTrackId($_COOKIE[self::JOURNEY_TRACK_ID_COOKIE]);
        } elseif (self::isValidTrackIdCookie(self::CHECKOUT_TRACK_ID_COOKIE)) {
            $client->setTrackId($_COOKIE[self::CHECKOUT_TRACK_ID_COOKIE]);
        }

        $trackId = $client->getTrackId();

        if (!headers_sent()) {
            $expires = time() + self::CHECKOUT_TRACK_ID_COOKIE_TTL;

            if (PHP_VERSION_ID >= 70300) {
                setcookie(self::CHECKOUT_TRACK_ID_COOKIE, $trackId, [
                    'expires' => $expires,
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                /* PHP 7.1/7.2 target: setcookie()'s array-options 4th param (needed for a clean SameSite flag) requires
                   PHP 7.3+. SameSite is set via the well-known path-suffix workaround instead. Since PHP 8.1, setcookie()
                   rejects ";"/" " in the "path" arg, so this branch must stay 7.3-excluded. */
                setcookie(
                    self::CHECKOUT_TRACK_ID_COOKIE,
                    $trackId,
                    $expires,
                    '/; SameSite=Lax',
                    '',
                    true,
                    true
                );
            }
        }
    }

    public static function processApiError(
        string $errorPrefix,
        \Throwable $exception,
        string $context = OperationContext::ApiCommunication
    ): array {
        $userErrorMessage = __(
            'There was a technical problem. Please try again in a moment and it should work!',
            'comfino-payment-gateway'
        );

        $statusCode = 500;
        $isTimeout = false;
        $connectAttemptIdx = 1;
        [$connectionTimeout, $transferTimeout] = self::getTransportSettings(false);

        if ($exception instanceof HttpErrorExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $url = $exception->getUrl();
            $requestBody = $exception->getRequestBody();
            $responseBody = $exception->getResponseBody();

            if ($exception instanceof AccessDenied && $statusCode === 404) {
                $userErrorMessage = $exception->getMessage();
            } elseif ($exception instanceof ConnectionTimeout) {
                $isTimeout = true;
                $connectAttemptIdx = $exception->getConnectAttemptIdx();
                $connectionTimeout = $exception->getConnectionTimeout();
                $transferTimeout = $exception->getTransferTimeout();

                DebugLogger::logEvent(
                    '[API_TIMEOUT]',
                    $errorPrefix,
                    [
                        'exception' => $exception->getPrevious() !== null ? get_class($exception->getPrevious()) : '',
                        'code' => $exception->getPrevious() !== null ? $exception->getPrevious()->getCode() : 0,
                        'connect_attempt_idx' => $exception->getConnectAttemptIdx(),
                        'connection_timeout' => $exception->getConnectionTimeout(),
                        'transfer_timeout' => $exception->getTransferTimeout(),
                    ]
                );
            } elseif ($statusCode < 500 && !$exception instanceof TooManyRequests) {
                $userErrorMessage = __(
                    'We have a configuration problem. The store is already working on a solution!',
                    'comfino-payment-gateway'
                );
            } elseif ($statusCode < 504 || $exception instanceof TooManyRequests) {
                // A rate-limited request (429) is a temporary outage for the customer, not a shop misconfiguration.
                $userErrorMessage = __(
                    'It looks like we have an outage. We\'ll fix it as soon as possible!',
                    'comfino-payment-gateway'
                );
            }
        } elseif ($exception instanceof NetworkExceptionInterface) {
            $exception->getRequest()->getBody()->rewind();

            DebugLogger::logEvent('[API_NETWORK_ERROR]', $errorPrefix, ['error_message' => $exception->getMessage()]);

            $url = $exception->getRequest()->getRequestTarget();
            $requestBody = $exception->getRequest()->getBody()->getContents();
            $responseBody = null;
        } else {
            $url = null;
            $requestBody = null;
            $responseBody = null;
        }

        $responseBody = ResponseBodySummarizer::summarize($responseBody);

        DebugLogger::logEvent(
            '[API_ERROR]',
            $errorPrefix,
            [
                'exception' => get_class($exception),
                'error_message' => $exception->getMessage(),
                'error_code' => $exception->getCode(),
                'error_file' => $exception->getFile(),
                'error_line' => $exception->getLine(),
                'error_trace' => $exception->getTraceAsString(),
            ]
        );

        /* A 404 and an idempotent failure (e.g. a 409 on an order already cancelled at ComfinoPay) need no report: the
           requested state already holds or the resource is gone. */
        if ($statusCode !== 404 && !($exception instanceof AccessDenied && $exception->isIdempotentFailure())) {
            ErrorLogger::sendError(
                $exception,
                $context,
                (string) $exception->getCode(),
                $exception->getMessage(),
                $url !== '' ? $url : null,
                $requestBody !== '' ? $requestBody : null,
                $responseBody !== '' ? $responseBody : null,
                $exception->getTraceAsString()
            );
        }

        return [
            'title' => $userErrorMessage,
            'error_details' => FrontendHelper::prepareErrorDetails(
                $userErrorMessage,
                $statusCode,
                ConfigManager::useDevEnvVars(),
                $exception,
                $isTimeout,
                $connectAttemptIdx,
                $connectionTimeout,
                $transferTimeout,
                $url,
                $requestBody,
                $responseBody
            ),
        ];
    }

    private static function isValidTrackIdCookie(string $cookieName): bool
    {
        return isset($_COOKIE[$cookieName]) &&
            is_string($_COOKIE[$cookieName]) &&
            preg_match(self::CHECKOUT_TRACK_ID_PATTERN, $_COOKIE[$cookieName]) === 1;
    }
}
