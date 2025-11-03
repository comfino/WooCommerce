<?php

declare(strict_types=1);

namespace Comfino\Common\Api\Response;

use Comfino\Api\Request;
use Comfino\Api\SerializerInterface;
use Comfino\Common\Exception\ConnectionTimeout;
use ComfinoExternal\Psr\Http\Message\ResponseInterface;

class ValidateOrder extends \Comfino\Api\Response\ValidateOrder
{
    /** @var bool Connection timeout error.
     * @readonly */
    public $isTimeout;
    /** @var int Connection trial counter.
     * @readonly */
    public $connectAttemptIdx;
    /** @var int Last connection timeout in seconds.
     * @readonly */
    public $connectionTimeout;
    /** @var int Last transfer timeout in seconds.
     * @readonly */
    public $transferTimeout;

    /**
     * @param Request $request
     * @param ResponseInterface $response
     * @param SerializerInterface $serializer
     * @param \Throwable|null $exception Exception object in case of validation or communication error.
     */
    public function __construct(Request $request, ResponseInterface $response, SerializerInterface $serializer, ?\Throwable $exception = null)
    {
        parent::__construct($request, $response, $serializer, $exception);

        $isTimeout = false;
        $connectAttemptIdx = 0;
        $connectionTimeout = 0;
        $transferTimeout = 0;

        if ($exception instanceof ConnectionTimeout) {
            // We have a timeout error.
            $isTimeout = true;
            $connectAttemptIdx = $exception->getConnectAttemptIdx();
            $connectionTimeout = $exception->getConnectionTimeout();
            $transferTimeout = $exception->getTransferTimeout();
        }

        $this->isTimeout = $isTimeout;
        $this->connectAttemptIdx = $connectAttemptIdx;
        $this->connectionTimeout = $connectionTimeout;
        $this->transferTimeout = $transferTimeout;
    }
}
