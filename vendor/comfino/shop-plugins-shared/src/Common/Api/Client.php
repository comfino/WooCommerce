<?php

declare(strict_types=1);

namespace Comfino\Common\Api;

use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Request;
use Comfino\Api\Request\CreateOrder as CreateOrderRequest;
use Comfino\Common\Api\Response\ValidateOrder as ValidateOrderResponse;
use Comfino\Common\Exception\ConnectionTimeout;
use Comfino\Shop\Order\OrderInterface;
use ComfinoExternal\Psr\Http\Client\ClientExceptionInterface;
use ComfinoExternal\Psr\Http\Message\ResponseInterface;
use ComfinoExternal\Sunrise\Http\Factory\RequestFactory;
use ComfinoExternal\Sunrise\Http\Factory\ResponseFactory;
use ComfinoExternal\Sunrise\Http\Factory\StreamFactory;
use ComfinoExternal\Sunrise\Http\Message\Response;

class Client extends \Comfino\Extended\Api\Client
{
    /**
     * @var int
     */
    protected $connectionTimeout = 1;
    /**
     * @var int
     */
    protected $transferTimeout = 3;
    /**
     * @var int
     */
    protected $connectionMaxNumAttempts = 3;
    /**
     * @var array
     */
    protected $options = [];
    /**
     * @var \ComfinoExternal\Sunrise\Http\Factory\ResponseFactory
     */
    protected static $responseFactory;

    /**
     * @param string|null $apiKey
     * @param int $connectionTimeout
     * @param int $transferTimeout
     * @param int $connectionMaxNumAttempts
     * @param array $options
     */
    public function __construct(
        ?string $apiKey,
        int $connectionTimeout = 1,
        int $transferTimeout = 3,
        int $connectionMaxNumAttempts = 3,
        array $options = []
    ) {
        $this->connectionTimeout = $connectionTimeout;
        $this->transferTimeout = $transferTimeout;
        $this->connectionMaxNumAttempts = $connectionMaxNumAttempts;
        $this->options = $options;
        if ($this->connectionTimeout >= $this->transferTimeout) {
            $this->transferTimeout = 3 * $this->connectionTimeout;
        }

        if ($this->connectionMaxNumAttempts === 0) {
            $this->connectionMaxNumAttempts = 3;
        }

        self::$responseFactory = new ResponseFactory();

        parent::__construct(
            new RequestFactory(),
            new StreamFactory(),
            $this->createClient($this->connectionTimeout, $this->transferTimeout, $this->options),
            $apiKey
        );
    }

    /**
     * @param \Comfino\Shop\Order\OrderInterface $order
     */
    public function validateOrder($order): \Comfino\Api\Response\ValidateOrder
    {
        try {
            $this->request = (new CreateOrderRequest($order, $this->apiKey ?? '', true))->setSerializer($this->serializer);

            return new ValidateOrderResponse($this->request, $this->sendRequest($this->request), $this->serializer);
        } catch (\Throwable $e) {
            return new ValidateOrderResponse(
                $this->request,
                $e instanceof RequestValidationError ? $e->getResponse() : $this->response,
                $this->serializer,
                $e
            );
        }
    }

    /**
     * @param int $connectionTimeout
     * @param int $transferTimeout
     * @param int $connectionMaxNumAttempts
     * @param array $options
     */
    public function resetClient($connectionTimeout, $transferTimeout, $connectionMaxNumAttempts, $options = []): void
    {
        $this->connectionMaxNumAttempts = $connectionMaxNumAttempts;

        sort($this->options);
        sort($options);

        if ($this->connectionTimeout === $connectionTimeout && $this->transferTimeout === $transferTimeout && $this->options === $options) {
            return;
        }

        $this->connectionTimeout = $connectionTimeout;
        $this->transferTimeout = $transferTimeout;
        $this->options = $options;

        if ($this->connectionTimeout >= $this->transferTimeout) {
            $this->transferTimeout = 3 * $this->connectionTimeout;
        }

        $this->client = $this->createClient($connectionTimeout, $transferTimeout, $options);
    }

    /**
     * @param int $connectAttemptIdx
     */
    public function calculateConnectionTimeout($connectAttemptIdx): int
    {
        if ($connectAttemptIdx <= 1 || $connectAttemptIdx > $this->connectionMaxNumAttempts || $this->connectionMaxNumAttempts <= 1) {
            return $this->connectionTimeout;
        }

        static $initSeqIndex = 0;

        if ($initSeqIndex === 0) {
            $initSeqIndex = $this->findFibonacciSequenceIndex($this->connectionTimeout);
        }

        return $this->calcFibonacciNumber($initSeqIndex + $connectAttemptIdx - 1);
    }

    /**
     * @param int $connectAttemptIdx
     */
    public function calculateTransferTimeout($connectAttemptIdx): int
    {
        if ($connectAttemptIdx <= 1 || $connectAttemptIdx > $this->connectionMaxNumAttempts || $this->connectionMaxNumAttempts <= 1) {
            return $this->transferTimeout;
        }

        static $initSeqIndex = 0;

        if ($initSeqIndex === 0) {
            $initSeqIndex = $this->findFibonacciSequenceIndex($this->transferTimeout);
        }

        return $this->calcFibonacciNumber($initSeqIndex + $connectAttemptIdx - 1);
    }

    /**
     * @throws ClientExceptionInterface
     * @param \Comfino\Api\Request $request
     * @param int|null $apiVersion
     */
    protected function sendRequest($request, $apiVersion = null): ResponseInterface
    {
        $connectionTimeout = $this->connectionTimeout;
        $transferTimeout = $this->transferTimeout;

        for ($connectAttemptIdx = 1; $connectAttemptIdx <= $this->connectionMaxNumAttempts; $connectAttemptIdx++) {
            try {
                return parent::sendRequest($request, $apiVersion);
            } catch (ClientExceptionInterface $e) {
                if ($e->getCode() === CURLE_OPERATION_TIMEDOUT) {
                    if ($connectAttemptIdx < $this->connectionMaxNumAttempts) {
                        $connectionTimeout = $this->calculateConnectionTimeout($connectAttemptIdx);
                        $transferTimeout = $this->calculateTransferTimeout($connectAttemptIdx);

                        $this->client = $this->createClient($connectionTimeout, $transferTimeout, $this->options);
                    } else {
                        throw new ConnectionTimeout(
                            $e->getMessage(),
                            $e->getCode(),
                            $e,
                            $connectAttemptIdx,
                            $connectionTimeout,
                            $transferTimeout,
                            $request->getRequestUri() ?? '',
                            $request->getRequestBody() ?? ''
                        );
                    }
                } else {
                    throw $e;
                }
            }
        }

        return new Response();
    }

    /**
     * @param int $connectionTimeout
     * @param int $transferTimeout
     * @param mixed[] $options
     */
    protected function createClient($connectionTimeout, $transferTimeout, $options = []): \ComfinoExternal\Sunrise\Http\Client\Curl\Client
    {
        $clientOptions = [CURLOPT_CONNECTTIMEOUT => $connectionTimeout, CURLOPT_TIMEOUT => $transferTimeout];

        foreach ($options as $optionIdx => $valueValue) {
            $clientOptions[$optionIdx] = $valueValue;
        }

        return new \ComfinoExternal\Sunrise\Http\Client\Curl\Client(self::$responseFactory, $clientOptions);
    }

    /**
     * @param int $fibNum
     * @return int
     */
    protected function findFibonacciSequenceIndex($fibNum): int
    {
        return (int) round(2.078087 * log($fibNum) + 1.672276);
    }

    /**
     * @param int $n
     * @return int
     */
    protected function calcFibonacciNumber($n): int
    {
        static $phi = 1.6180339; 
        static $fibSequence = [0, 1, 1, 2, 3, 5];

        if ($n < 6) {
            return $fibSequence[$n];
        }

        $i = 5;
        $fn = 5;

        while ($i++ < $n) {
            $fn = (int) round($fn * $phi);
        }

        return $fn;
    }
}
