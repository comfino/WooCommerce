<?php

namespace Comfino\Order;

use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Common\Shop\OrderStatusAdapterInterface;
use Comfino\Configuration\ConfigManager;

if (!defined('ABSPATH')) {
    exit;
}

class StatusAdapter implements OrderStatusAdapterInterface
{
    private static $loggedStates = [
        StatusManager::STATUS_ACCEPTED,
        StatusManager::STATUS_CANCELLED,
        StatusManager::STATUS_CANCELLED_BY_SHOP,
        StatusManager::STATUS_REJECTED,
        StatusManager::STATUS_RESIGN,
    ];

    public function setStatus($orderId, $status): void
    {
        $order = wc_get_order($orderId);

        if (!$order) {
            throw new \RuntimeException(sprintf('Order not found by id: %s', $orderId));
        }

        $inputStatus = strtoupper($status);

        if (!in_array($inputStatus, StatusManager::STATUSES, true)) {
            return;
        }

        if (in_array($inputStatus, self::$loggedStates, true)) {
            $order->add_order_note(__('Comfino status', 'comfino-payment-gateway') . __($inputStatus, 'comfino-payment-gateway'));
        }

        $statusMap = ConfigManager::getStatusMap();

        if (!array_key_exists($inputStatus, $statusMap)) {
            return;
        }

        if ($statusMap[$inputStatus] === 'completed') {
            $order->payment_complete();
        } elseif ($statusMap[$inputStatus] === 'cancelled') {
            $order->update_status('cancelled');
        }
    }
}
