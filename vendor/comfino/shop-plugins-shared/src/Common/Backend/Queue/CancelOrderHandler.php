<?php

declare(strict_types=1);

namespace Comfino\Common\Backend\Queue;

use Comfino\Api\Client;

final class CancelOrderHandler implements RetryableOperationHandlerInterface
{
    /**
     * @var \Comfino\Api\Client
     */
    private $apiClient;
    
    public const OPERATION_TYPE = 'cancel_order';

    public function __construct(Client $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    /**
     * @param mixed[] $payload
     */
    public function execute($payload): void
    {
        $orderId = (string) ($payload['orderId'] ?? '');

        if ($orderId === '') {
            throw new \InvalidArgumentException('The cancel_order payload requires a non-empty "orderId".');
        }

        $this->apiClient->cancelOrder($orderId);
    }
}
