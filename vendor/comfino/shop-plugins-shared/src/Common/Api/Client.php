<?php

namespace Comfino\Common\Api;

use Comfino\Api\Request;
use Comfino\Common\Exception\ConnectionTimeout;
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
     * @param string|null $apiKey Unique authentication key required for access to the Comfino API.
     * @param int $connectionTimeout API connection timeout in seconds.
     * @param int $transferTimeout Data transfer from API timeout in seconds. Must be greater than connection timeout.
     * @param int $connectionMaxNumAttempts Maximum number of connection attempts in case of timeout.
     * @param array $options cURL client extra options.
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

        parent::__construct(
            new RequestFactory(),
            new StreamFactory(),
            $this->createClient($this->connectionTimeout, $this->transferTimeout, $this->options),
            $apiKey
        );
    }

    /**
     * Resets internal cURL client object and updates connection options.
     *
     * @param int $connectionTimeout API connection timeout in seconds.
     * @param int $transferTimeout Data transfer from API timeout in seconds. Must be greater than connection timeout.
     * @param int $connectionMaxNumAttempts Maximum number of connection attempts in case of timeout.
     * @param array $options
     * @return void
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
                        // Connection or transfer timeout - try again with higher timeout limits.
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
        if (self::$responseFactory === null) {
            self::$responseFactory = new ResponseFactory();
        }

        $clientOptions = [CURLOPT_CONNECTTIMEOUT => $connectionTimeout, CURLOPT_TIMEOUT => $transferTimeout];

        foreach ($options as $optionIdx => $valueValue) {
            $clientOptions[$optionIdx] = $valueValue;
        }

        return new \ComfinoExternal\Sunrise\Http\Client\Curl\Client(self::$responseFactory, $clientOptions);
    }

    /**
     * Returns sequence index of given Fibonacci number.
     *
     * @see https://en.wikipedia.org/wiki/Fibonacci_sequence
     *
     * @param int $fibNum Fibonacci number to check.
     * @return int Zero based sequence index of given Fibonacci number.
     */
    protected function findFibonacciSequenceIndex($fibNum): int
    {
        return round(2.078087 * log($fibNum) + 1.672276);
    }

    /**
     * Calculates a value of the n-th element from Fibonacci sequence.
     *
     * @see https://en.wikipedia.org/wiki/Fibonacci_sequence
     * @see https://en.wikipedia.org/wiki/Golden_ratio
     *
     * @param int $n Fibonacci sequence position counted from zero.
     * @return int N-th Fibonacci number.
     */
    protected function calcFibonacciNumber($n): int
    {
        static $phi = 1.6180339; // Golden ratio approximation.
        static $fibSequence = [0, 1, 1, 2, 3, 5];

        if ($n < 6) {
            return $fibSequence[$n];
        }

        $i = 5;
        $fn = 5;

        while ($i++ < $n) {
            $fn = round($fn * $phi);
        }

        return $fn;
    }
}
