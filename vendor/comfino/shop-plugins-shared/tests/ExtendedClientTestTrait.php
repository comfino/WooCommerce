<?php

namespace Comfino;

use Comfino\Api\Client;
use Comfino\Extended\Api\Dto\Plugin\ShopPluginError;
use Comfino\Extended\Api\Serializer\Json as JsonSerializer;
use Http\Message\RequestMatcher\RequestMatcher;
use ComfinoExternal\Psr\Http\Message\RequestInterface;
use ComfinoExternal\Psr\Http\Message\ResponseInterface;
use ComfinoExternal\Sunrise\Http\Factory\RequestFactory;
use ComfinoExternal\Sunrise\Http\Factory\ResponseFactory;
use ComfinoExternal\Sunrise\Http\Factory\StreamFactory;

trait ExtendedClientTestTrait
{
    use ReflectionTrait;

    /**
     * @var string
     */
    protected $productionApiHost;

    public function testSendLoggedError(): void
    {
        $shopPluginError = new ShopPluginError(
            'host',
            'platform',
            ['attrib1' => 'value1', 'attrib2' => 'value2'],
            '123EF',
            'Error message.'
        );
        $hashKey = 'Comfino API client 1.0';
        $errorDetails = gzcompress('{"host":"host","platform":"platform","environment":{"attrib1":"value1","attrib2":"value2"},"error_code":"123EF","error_message":"Error message.","api_request_url":null,"api_request":null,"api_response":null,"stack_trace":null}', 9);
        $encodedErrorDetails = base64_encode($errorDetails);

        $request = [
            'error_details' => $encodedErrorDetails,
            'hash' => hash_hmac('sha3-256', $encodedErrorDetails, $hashKey),
        ];

        $apiClient = $this->initApiClient('/v1/log-plugin-error', 'POST', null, (new JsonSerializer())->serialize($request), null, 'API-KEY');
        $status = $apiClient->sendLoggedError($shopPluginError);

        $this->assertTrue($status);
    }

    protected function setUp(): void
    {
        $this->productionApiHost = parse_url($this->getConstantFromClass(Client::class, 'PRODUCTION_HOST'), PHP_URL_HOST);
    }

    private function initApiClient(string $endpointPath, string $method, ?array $queryParameters = null, ?string $requestBody = null, $responseData = null, ?string $apiKey = null, bool $isPublicEndpoint = false, int $responseStatus = 200): Extended\Api\Client
    {
        $client = new \Http\Mock\Client();
        $client->on(
            new RequestMatcher($endpointPath, $this->productionApiHost, $method, 'https'),
            function (RequestInterface $request) use ($queryParameters, $requestBody, $responseData, $apiKey, $isPublicEndpoint, $responseStatus) {
                return $this->processRequest($request, $queryParameters, $requestBody, $responseData, $apiKey, $isPublicEndpoint, $responseStatus);
            }
        );

        return new Extended\Api\Client(new RequestFactory(), new StreamFactory(), $client, $apiKey);
    }

    private function processRequest(RequestInterface $request, ?array $queryParameters = null, ?string $requestBody = null, $responseData = null, ?string $apiKey = null, bool $isPublicEndpoint = false, int $responseStatus = 200): ResponseInterface
    {
        if (!$isPublicEndpoint && (!$request->hasHeader('Api-Key') || $request->getHeaderLine('Api-Key') !== $apiKey)) {
            return (new ResponseFactory())->createJsonResponse(401, ['message' => 'Invalid credentials.']);
        }

        if ($requestBody !== null) {
            $this->assertEquals($requestBody, $request->getBody()->getContents(), 'Request body is invalid.');
        } else {
            $this->assertEquals('', $request->getBody()->getContents(), 'Request body is invalid.');
        }

        if (is_array($queryParameters) && count($queryParameters)) {
            $this->assertEquals(http_build_query($queryParameters), $request->getUri()->getQuery(), 'Request URL query string is invalid.');
        } else {
            $this->assertEquals('', $request->getUri()->getQuery(), 'Request URL query string is invalid.');
        }

        return (new ResponseFactory())->createJsonResponse($responseStatus, $responseData);
    }
}
