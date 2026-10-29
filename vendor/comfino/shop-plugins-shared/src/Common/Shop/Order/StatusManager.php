<?php

declare(strict_types=1);

namespace Comfino\Common\Shop\Order;

use Comfino\Common\Shop\OrderStatusAdapterInterface;

class StatusManager
{
    /**
     * @var \Comfino\Common\Shop\OrderStatusAdapterInterface
     */
    private $orderStatusAdapter;
    /**
     * @var string
     */
    private $scope = '';
    public const STATUS_CREATED = 'CREATED';
    public const STATUS_WAITING_FOR_FILLING = 'WAITING_FOR_FILLING';
    public const STATUS_WAITING_FOR_CONFIRMATION = 'WAITING_FOR_CONFIRMATION';
    public const STATUS_WAITING_FOR_PAYMENT = 'WAITING_FOR_PAYMENT';
    public const STATUS_ACCEPTED = 'ACCEPTED';
    public const STATUS_PAID = 'PAID';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_RESIGN = 'RESIGN';
    public const STATUS_CANCELLED_BY_SHOP = 'CANCELLED_BY_SHOP';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUSES = [
        self::STATUS_CREATED,
        self::STATUS_WAITING_FOR_FILLING,
        self::STATUS_WAITING_FOR_CONFIRMATION,
        self::STATUS_WAITING_FOR_PAYMENT,
        self::STATUS_ACCEPTED,
        self::STATUS_PAID,
        self::STATUS_REJECTED,
        self::STATUS_RESIGN,
        self::STATUS_CANCELLED_BY_SHOP,
        self::STATUS_CANCELLED,
    ];

    public const DEFAULT_IGNORED_STATUSES = [
        self::STATUS_WAITING_FOR_FILLING,
        self::STATUS_WAITING_FOR_CONFIRMATION,
        self::STATUS_WAITING_FOR_PAYMENT,
        self::STATUS_PAID,
    ];

    public const DEFAULT_FORBIDDEN_STATUSES = [self::STATUS_RESIGN];

    private static $instances = [];

    /**
     * @param \Comfino\Common\Shop\OrderStatusAdapterInterface $orderStatusAdapter
     * @param string $scope
     */
    public static function getInstance($orderStatusAdapter, $scope = ''): self
    {
        return self::$instances[$scope] = self::$instances[$scope] ?? new self($orderStatusAdapter, $scope);
    }

    private function __construct(OrderStatusAdapterInterface $orderStatusAdapter, string $scope = '')
    {
        $this->orderStatusAdapter = $orderStatusAdapter;
        $this->scope = $scope;
    }

    /**
     * @param string $externalId
     * @param string $status
     */
    public function setOrderStatus($externalId, $status): void
    {
        $this->applicationContext()->apply(function () use ($externalId, $status) {
            return $this->orderStatusAdapter->setStatus($externalId, $status);
        });
    }

    public function applicationContext(): StatusApplicationContext
    {
        return StatusApplicationContext::forScope($this->scope);
    }
}
