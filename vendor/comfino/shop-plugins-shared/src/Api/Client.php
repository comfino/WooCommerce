<?php

namespace Comfino\Api;

use Comfino\Api\Dto\Payment\LoanQueryCriteria;
use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Api\Exception\AccessDenied;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ResponseValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use Comfino\Api\Request\CancelOrder as CancelOrderRequest;
use Comfino\Api\Request\CreateOrder as CreateOrderRequest;
use Comfino\Api\Request\GetFinancialProductDetails as GetFinancialProductDetailsRequest;
use Comfino\Api\Request\GetFinancialProducts as GetFinancialProductsRequest;
use Comfino\Api\Request\GetOrder as GetOrderRequest;
use Comfino\Api\Request\GetPaywall as GetPaywallRequest;
use Comfino\Api\Request\GetPaywallItemDetails as GetPaywallItemDetailsRequest;
use Comfino\Api\Request\GetProductTypes as GetProductTypesRequest;
use Comfino\Api\Request\GetWidgetKey as GetWidgetKeyRequest;
use Comfino\Api\Request\GetWidgetTypes as GetWidgetTypesRequest;
use Comfino\Api\Request\IsShopAccountActive as IsShopAccountActiveRequest;
use Comfino\Api\Response\Base as BaseApiResponse;
use Comfino\Api\Response\CreateOrder as CreateOrderResponse;
use Comfino\Api\Response\GetFinancialProductDetails as GetFinancialProductDetailsResponse;
use Comfino\Api\Response\GetFinancialProducts as GetFinancialProductsResponse;
use Comfino\Api\Response\GetOrder as GetOrderResponse;
use Comfino\Api\Response\GetPaywall as GetPaywallResponse;
use Comfino\Api\Response\GetPaywallItemDetails as GetPaywallItemDetailsResponse;
use Comfino\Api\Response\GetProductTypes as GetProductTypesResponse;
use Comfino\Api\Response\GetWidgetKey as GetWidgetKeyResponse;
use Comfino\Api\Response\GetWidgetTypes as GetWidgetTypesResponse;
use Comfino\Api\Response\IsShopAccountActive as IsShopAccountActiveResponse;
use Comfino\Api\Serializer\Json as JsonSerializer;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Shop\Order\CartInterface;
use Comfino\Shop\Order\OrderInterface;
use ComfinoExternal\Psr\Http\Client\ClientExceptionInterface;
use ComfinoExternal\Psr\Http\Client\ClientInterface;
use ComfinoExternal\Psr\Http\Message\RequestFactoryInterface;
use ComfinoExternal\Psr\Http\Message\ResponseInterface;
use ComfinoExternal\Psr\Http\Message\StreamFactoryInterface;

/**
 * Comfino API client.
 *
 * @version 1.0
 * @author Artur Kozubski <akozubski@comperia.pl>
 */
class Client
{
    /**
     * @var RequestFactoryInterface
     * @readonly
     */
    protected $requestFactory;
    /**
     * @var StreamFactoryInterface
     * @readonly
     */
    protected $streamFactory;
    /**
     * @var ClientInterface
     */
    protected $client;
    /**
     * @var string|null
     */
    protected $apiKey;
    /**
     * @var int
     */
    protected $apiVersion = 1;
    /**
     * @var SerializerInterface|null
     */
    protected $serializer;
    protected const CLIENT_VERSION = '1.0';
    protected const PRODUCTION_HOST = 'https://api-ecommerce.comfino.pl';
    protected const SANDBOX_HOST = 'https://api-ecommerce.craty.pl';

    /** @var string */
    protected $apiLanguage = 'pl';
    /** @var string|null */
    protected $customApiHost;
    /** @var string|null */
    protected $customUserAgent;
    /** @var string[] */
    protected $customHeaders = [];
    /** @var bool */
    protected $isSandboxMode = false;
    /** @var Request|null */
    protected $request;

    /**
     * Comfino API client.
     *
     * @param RequestFactoryInterface $requestFactory External PSR-18 compatible HTTP request factory.
     * @param StreamFactoryInterface $streamFactory External PSR-18 compatible stream factory.
     * @param ClientInterface $client External PSR-18 compatible HTTP client which will be used to communicate with the API.
     * @param string|null $apiKey Unique authentication key required for access to the Comfino API.
     * @param int $apiVersion Selected default API version (default: v1).
     * @param SerializerInterface|null $serializer JSON serializer.
     */
    public function __construct(RequestFactoryInterface $requestFactory, StreamFactoryInterface $streamFactory, ClientInterface $client, ?string $apiKey, int $apiVersion = 1, ?SerializerInterface $serializer = null)
    {
        $serializer = $serializer ?? null ?? new JsonSerializer();
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->client = $client;
        $this->apiKey = $apiKey;
        $this->apiVersion = $apiVersion;
        $this->serializer = $serializer;
    }

    /**
     * Sets custom request/response serializer.
     *
     * @param SerializerInterface $serializer
     *
     * @return void
     */
    public function setSerializer($serializer): void
    {
        $this->serializer = $serializer;
    }

    /**
     * Selects current API version.
     *
     * @param int $version Desired API version.
     *
     * @return void
     */
    public function setApiVersion($version): void
    {
        $this->apiVersion = $version;
    }

    /**
     * Returns current API key.
     *
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * Sets current API key.
     *
     * @param string $apiKey API key.
     *
     * @return void
     */
    public function setApiKey($apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Returns current API language.
     *
     * @return string Language code (eg: pl, en)
     */
    public function getApiLanguage(): string
    {
        return $this->apiLanguage;
    }

    /**
     * Selects current API language.
     *
     * @param string $language Language code (eg: pl, en).
     *
     * @return void
     */
    public function setApiLanguage($language): void
    {
        $this->apiLanguage = $language;
    }

    /**
     * Returns current API host.
     *
     * @return string
     */
    public function getApiHost(): string
    {
        return $this->customApiHost ?? ($this->isSandboxMode ? self::SANDBOX_HOST : self::PRODUCTION_HOST);
    }

    /**
     * Sets custom API host.
     *
     * @param string|null $host Custom API host.
     *
     * @return void
     */
    public function setCustomApiHost($host): void
    {
        $this->customApiHost = $host;
    }

    /**
     * Sets custom User-Agent header.
     *
     * @param string|null $userAgent
     *
     * @return void
     */
    public function setCustomUserAgent($userAgent): void
    {
        $this->customUserAgent = $userAgent;
    }

    /**
     * Adds a custom HTTP header to the API request call.
     *
     * @param string $headerName
     * @param string $headerValue
     *
     * @return void
     */
    public function addCustomHeader($headerName, $headerValue): void
    {
        $this->customHeaders[$headerName] = $headerValue;
    }

    public function enableSandboxMode(): void
    {
        $this->isSandboxMode = true;
    }

    public function disableSandboxMode(): void
    {
        $this->isSandboxMode = false;
    }

    /**
     * @param \ComfinoExternal\Psr\Http\Client\ClientInterface $client
     */
    public function setClient($client): void
    {
        $this->client = $client;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return self::CLIENT_VERSION;
    }

    /**
     * Returns last API request.
     *
     * @return Request|null
     */
    public function getRequest(): ?Request
    {
        return $this->request;
    }

    /**
     * Checks if registered user shop account is active.
     *
     * @param string|null $cacheInvalidateUrl Integrated platform API endpoint for local cache invalidation.
     * @param string|null $configurationUrl Integrated platform API endpoint for local configuration management.
     *
     * @return bool
     *
     * @throws RequestValidationError
     * @throws ResponseValidationError
     * @throws AuthorizationError
     * @throws AccessDenied
     * @throws ServiceUnavailable
     * @throws ClientExceptionInterface
     */
    public function isShopAccountActive($cacheInvalidateUrl = null, $configurationUrl = null): bool
    {
        try {
            $this->request = (new IsShopAccountActiveRequest($cacheInvalidateUrl, $configurationUrl))->setSerializer($this->serializer);

            return (new IsShopAccountActiveResponse($this->request, $this->sendRequest($this->request), $this->serializer))->isActive;
        } catch (HttpErrorExceptionInterface $e) {
            if (isset($this->request)) {
                $e->setRequestBody($this->request->getRequestBody() ?? '');
            }

            throw $e;
        }
    }

    /**
     * Returns a list of financial products according to the specified criteria and calculations result based on passed cart contents.
     *
     * @param LoanQueryCriteria $queryCriteria
     * @param CartInterface $cart
     *
     * @return GetFinancialProductDetailsResponse
     *
     * @throws RequestValidationError
     * @throws ResponseValidationError
     * @throws AuthorizationError
     * @throws AccessDenied
     * @throws ServiceUnavailable
     * @throws ClientExceptionInterface
     */
    public function getFinancialProductDetails($queryCriteria, $cart): GetFinancialProductDetailsResponse
    {
        try {
            $this->request = (new GetFinancialProductDetailsRequest($queryCriteria, $cart))->setSerializer($this->serializer);

            return new GetFinancialProductDetailsResponse($this->request, $this->sendRequest($this->request), $this->serializer);
        } catch (HttpErrorExceptionInterface $e) {
            if (isset($this->request)) {
                $e->setRequestBody($this->request->getRequestBody() ?? '');
            }

            throw $e;
        }
    }

    /**
     * Returns a list of financial products according to the specified criteria.
     *
     * @param LoanQueryCriteria $queryCriteria
     *
     * @return GetFinancialProductsResponse
     *
     * @throws RequestValidationError
     * @throws ResponseValidationError
     * @throws AuthorizationError
     * @throws AccessDenied
     * @throws ServiceUnavailable
     * @throws ClientExceptionInterface
     */
    public function getFinancialProducts($queryCriteria): GetFinancialProductsResponse
    {
        try {
            $this->request = (new GetFinancialProductsRequest($queryCriteria))->setSerializer($this->serializer);

            return new GetFinancialProductsResponse($this->request, $this->sendRequest($this->request), $this->serializer);
        } catch (HttpErrorExceptionInterface $e) {
            if (isset($this->request)) {
                $e->setRequestBody($this->request->getRequestBody() ?? '');
            }

            throw $e;
        }
    }

    /**
     * Submits a loan application.
     *
     * @param OrderInterface $order Full order data (cart, loan details).
     *
     * @return CreateOrderResponse
     *
     * @throws RequestValidationError
     * @throws ResponseValidationError
     * @throws AuthorizationError
     * @throws AccessDenied
     * @throws ServiceUnavailable
     * @throws ClientExceptionInterface
     */
    public function createOrder($order): CreateOrderResponse
    {
        try {
            $this->request = (new CreateOrderRequest($order))->setSerializer($this->serializer);

            return new CreateOrderResponse($this->request, $this->sendRequest($this->request), $this->serializer);
        } catch (HttpErrorExceptionInterface $e) {
            if (isset($this->request)) {
                $e->setRequestBody($this->request->getRequestBody() ?? '');
            }

            throw $e;
        }
    }

    /**
     * Returns a details of specified loan application.
     *
     * @param string $orderId Loan application ID returned by createOrder action.
     *
     * @return GetOrderResponse
     *
     * @throws RequestValidationError
     * @throws ResponseValidationError
     * @throws AuthorizationError
     * @throws AccessDenied
     * @throws ServiceUnavailable
     * @throws ClientExceptionInterface
     */
    public function getOrder($orderId): GetOrderResponse
    {
        try {
            $this->request = (new GetOrderRequest($orderId))->setSerializer($this->serializer);

            return new GetOrderResponse($this->request, $this->sendRequest($this->request), $this->serializer);
        } catch (HttpErrorExceptionInterface $e) {
            if (isset($this->request)) {
                $e->setRequestBody($this->request->getRequestBody() ?? '');
            }

            throw $e;
        }
    }

    /**
     * Cancels a loan application.
     *
     * @param string $orderId Loan application ID returned by createOrder action.
     * @throws RequestValidationError
     * @throws ResponseValidationError
     * @throws AuthorizationError
     * @throws AccessDenied
     * @throws ServiceUnavailable
     * @throws ClientExceptionInterface
     */
    public function cancelOrder($orderId): void
    {
        try {
            $this->request = (new CancelOrderRequest($orderId))->setSerializer($this->serializer);

            new BaseApiResponse($this->request, $this->sendRequest($this->request), $this->serializer);
        } catch (HttpErrorExceptionInterface $e) {
            if (isset($this->request)) {
                $e->setRequestBody($this->request->getRequestBody() ?? '');
            }

            throw $e;
        }
    }

    /**
     * Returns a list of available financial product types associated with an authorized shop account.
     *
     * @throws RequestValidationError
     * @throws ResponseValidationError
     * @throws AuthorizationError
     * @throws AccessDenied
     * @throws ServiceUnavailable
     * @throws ClientExceptionInterface
     * @param \Comfino\FinancialProduct\ProductTypesListTypeEnum $listType
     */
    public function getProductTypes($listType): GetProductTypesResponse
    {
        try {
            $this->request = (new GetProductTypesRequest($listType))->setSerializer($this->serializer);

            return new GetProductTypesResponse($this->request, $this->sendRequest($this->request), $this->serializer);
        } catch (HttpErrorExceptionInterface $e) {
            if (isset($this->request)) {
                $e->setRequestBody($this->request->getRequestBody() ?? '');
            }

            throw $e;
        }
    }

    /**
     * Returns a widget key associated with an authorized shop account.
     *
     * @throws RequestValidationError
     * @throws ResponseValidationError
     * @throws AuthorizationError
     * @throws AccessDenied
     * @throws ServiceUnavailable
     * @throws ClientExceptionInterface
     */
    public function getWidgetKey(): string
    {
        try {
            $this->request = (new GetWidgetKeyRequest())->setSerializer($this->serializer);

            return (new GetWidgetKeyResponse($this->request, $this->sendRequest($this->request), $this->serializer))->widgetKey;
        } catch (HttpErrorExceptionInterface $e) {
            if (isset($this->request)) {
                $e->setRequestBody($this->request->getRequestBody() ?? '');
            }

            throw $e;
        }
    }

    /**
     * Returns a list of available widget types associated with an authorized shop account.
     *
     * @param bool $useNewApi Whether to use a new widget type and new API endpoint.
     *
     * @throws RequestValidationError
     * @throws ResponseValidationError
     * @throws AuthorizationError
     * @throws AccessDenied
     * @throws ServiceUnavailable
     * @throws ClientExceptionInterface
     */
    public function getWidgetTypes($useNewApi = true): GetWidgetTypesResponse
    {
        try {
            $this->request = (new GetWidgetTypesRequest($useNewApi))->setSerializer($this->serializer);

            return new GetWidgetTypesResponse($this->request, $this->sendRequest($this->request), $this->serializer);
        } catch (HttpErrorExceptionInterface $e) {
            if (isset($this->request)) {
                $e->setRequestBody($this->request->getRequestBody() ?? '');
            }

            throw $e;
        }
    }

    /**
     * Returns a complete paywall page with list of financial products according to the specified criteria.
     *
     * @param LoanQueryCriteria $queryCriteria List filtering criteria.
     * @param string|null $recalculationUrl Paywall form action URL used for offer recalculations initialized by shop cart frontends.
     *
     * @return GetPaywallResponse
     *
     * @throws RequestValidationError
     * @throws ResponseValidationError
     * @throws AuthorizationError
     * @throws AccessDenied
     * @throws ServiceUnavailable
     * @throws ClientExceptionInterface
     */
    public function getPaywall($queryCriteria, $recalculationUrl = null): GetPaywallResponse
    {
        try {
            $this->request = (new GetPaywallRequest($queryCriteria, $recalculationUrl))->setSerializer($this->serializer);

            return new GetPaywallResponse($this->request, $this->sendRequest($this->request, 2), $this->serializer);
        } catch (HttpErrorExceptionInterface $e) {
            if (isset($this->request)) {
                $e->setRequestBody($this->request->getRequestBody() ?? '');
            }

            throw $e;
        }
    }

    /**
     * Returns a details of paywall item for specified financial product type (loan type) and shopping cart contents.
     *
     * @param int $loanAmount Requested loan amount.
     * @param LoanTypeEnum $loanType Financial product type (loan type).
     * @param CartInterface $cart Shopping cart.
     *
     * @return GetPaywallItemDetailsResponse
     *
     * @throws RequestValidationError
     * @throws ResponseValidationError
     * @throws AuthorizationError
     * @throws AccessDenied
     * @throws ServiceUnavailable
     * @throws ClientExceptionInterface
     */
    public function getPaywallItemDetails($loanAmount, $loanType, $cart): GetPaywallItemDetailsResponse
    {
        try {
            $this->request = (new GetPaywallItemDetailsRequest($loanAmount, $loanType, $cart))->setSerializer($this->serializer);

            return new GetPaywallItemDetailsResponse($this->request, $this->sendRequest($this->request), $this->serializer);
        } catch (HttpErrorExceptionInterface $e) {
            if (isset($this->request)) {
                $e->setRequestBody($this->request->getRequestBody() ?? '');
            }

            throw $e;
        }
    }

    /**
     * @throws RequestValidationError
     * @throws ResponseValidationError
     * @throws ClientExceptionInterface
     * @param \Comfino\Api\Request $request
     * @param int|null $apiVersion
     */
    protected function sendRequest($request, $apiVersion = null): ResponseInterface
    {
        $apiRequest = $request->getPsrRequest(
            $this->requestFactory,
            $this->streamFactory,
            $this->getApiHost(),
            $apiVersion ?? $this->apiVersion
        )
        ->withHeader('Content-Type', 'application/json')
        ->withHeader('Api-Language', $this->apiLanguage)
        ->withHeader('User-Agent', $this->getUserAgent());

        if (count($this->customHeaders) > 0) {
            foreach ($this->customHeaders as $headerName => $headerValue) {
                $apiRequest = $apiRequest->withHeader($headerName, $headerValue);
            }
        }

        return $this->client->sendRequest(
            !empty($this->apiKey) ? $apiRequest->withHeader('Api-Key', $this->apiKey) : $apiRequest
        );
    }

    protected function getUserAgent(): string
    {
        return $this->customUserAgent ?? "Comfino API client {$this->getVersion()}";
    }
}
