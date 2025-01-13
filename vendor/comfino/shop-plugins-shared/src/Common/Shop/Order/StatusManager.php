<?php

namespace Comfino\Common\Shop\Order;

use Comfino\Common\Shop\OrderStatusAdapterInterface;

final class StatusManager
{
    /**
     * @readonly
     * @var \Comfino\Common\Shop\OrderStatusAdapterInterface
     */
    private $orderStatusAdapter;
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

    /**
     * @var $this|null
     */
    private static $instance;

    public static function getInstance(OrderStatusAdapterInterface $orderStatusAdapter): self
    {
        if (self::$instance === null) {
            self::$instance = new self($orderStatusAdapter);
        }

        return self::$instance;
    }

    private function __construct(OrderStatusAdapterInterface $orderStatusAdapter)
    {
        $this->orderStatusAdapter = $orderStatusAdapter;
    }

    public function setOrderStatus(string $externalId, string $status): void
    {
        $this->orderStatusAdapter->setStatus($externalId, $status);
    }
}
