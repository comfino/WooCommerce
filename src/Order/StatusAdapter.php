<?php

namespace Comfino\Order;

use Comfino\Api\Exception\NotFound;
use Comfino\Api\Exception\RequestValidationError;
use Comfino\Api\Exception\ServiceUnavailable;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Common\Shop\OrderStatusAdapterInterface;
use Comfino\Configuration\ConfigManager;
use Comfino\DebugLogger;
use Comfino\PaymentGateway;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adapter for handling order status updates from Comfino API webhooks.
 *
 * This class implements the OrderStatusAdapterInterface from the shared library
 * and provides WooCommerce-specific logic for updating order statuses based on
 * payment status changes received from Comfino API.
 *
 * Supports two order identification modes:
 * - Legacy: Numeric order ID (e.g., "12345")
 * - Modern: Order reference string (e.g., "XKTARQXWV")
 *
 * Status update flow:
 * 1. Loads order by ID or reference.
 * 2. Validates order is paid via Comfino.
 * 3. Maps Comfino status to internal WooCommerce status.
 * 4. Applies custom status if not already in history.
 * 5. Maps to standard WooCommerce status and applies if configured.
 */
class StatusAdapter implements OrderStatusAdapterInterface
{
    private static $loggedStates = [
        StatusManager::STATUS_ACCEPTED,
        StatusManager::STATUS_CANCELLED,
        StatusManager::STATUS_CANCELLED_BY_SHOP,
        StatusManager::STATUS_REJECTED,
        StatusManager::STATUS_RESIGN,
    ];

    /**
     * Updates WooCommerce order status based on Comfino payment status.
     *
     * This method is called by StatusNotification REST endpoint when receiving
     * status update webhooks from Comfino API. It:
     * - Determines order lookup method (by ID or reference).
     * - Validates order belongs to Comfino payment module.
     * - Converts Comfino status to WooCommerce internal status.
     * - Applies standard statuses if not already in history.
     *
     * The method avoids creating duplicate status history entries by checking
     * if the status was already applied to the order.
     *
     * @param string|int $orderId Order identifier - either numeric ID or reference string
     * @param string $status Comfino payment status (e.g., "ACCEPTED", "REJECTED", "CANCELLED")
     *
     * @return void
     *
     * @throws NotFound If order not found by provided ID or reference
     * @throws RequestValidationError If order exists but is not a Comfino order
     * @throws ServiceUnavailable If database error occurs during order loading
     */
    public function setStatus($orderId, $status): void
    {
        DebugLogger::logEvent(
            '[ORDER_STATUS_UPDATE]',
            'StatusAdapter::setStatus: Order status update from Comfino API.',
            ['orderId' => $orderId, 'status' => $status]
        );

        // Determine if orderId is numeric (legacy) or reference (new) and order sequential numbers are active.
        $loadById = is_numeric($orderId) && ctype_digit((string) $orderId) && !ConfigManager::isOrderReferenceEnabled();

        if ($loadById) {
            // Legacy path: Load by numeric ID using HPOS-compatible method.
            try {
                $order = OrderManager::loadOrder($orderId);
            } catch (\RuntimeException $e) {
                throw new ServiceUnavailable(esc_html(sprintf('Order %s loading error.', $orderId)));
            }
        } else {
            // New path: Load by reference (order number) using comprehensive plugin support.
            try {
                $order = OrderManager::loadOrderByNumber($orderId);
            } catch (\RuntimeException $e) {
                throw new ServiceUnavailable(esc_html(sprintf('Order "%s" loading error.', $orderId)));
            }
        }

        if ($order === null) {
            throw new NotFound(esc_html(sprintf('Order not found by %s: %s', $loadById ? 'id' : 'reference', $orderId)));
        }

        if ($order->get_payment_method() !== PaymentGateway::GATEWAY_ID) {
            // Process orders paid by Comfino only.
            throw new RequestValidationError(esc_html(sprintf('Order %s is not a valid Comfino order.', $orderId)));
        }

        $inputStatus = strtoupper($status);

        DebugLogger::logEvent(
            '[ORDER_STATUS_UPDATE]',
            sprintf(
                "StatusAdapter::setStatus (order %s: %s, status: \"%s\", internal ID: %d)",
                $loadById ? 'ID' : 'reference',
                $orderId,
                $inputStatus,
                $order->get_id()
            )
        );

        if (!in_array($inputStatus, StatusManager::STATUSES, true)) {
            return;
        }

        if (in_array($inputStatus, self::$loggedStates, true)) {
            $order->add_order_note(__('Comfino status', 'comfino-payment-gateway') . ": $inputStatus");
        }

        DebugLogger::logEvent(
            '[ORDER_STATUS_UPDATE]',
            "current internal status ID: {$order->get_status()}, new custom status ID: $inputStatus"
        );

        $statusMap = ConfigManager::getStatusMap();

        if (!array_key_exists($inputStatus, $statusMap)) {
            return;
        }

        $wcStatus = $statusMap[$inputStatus];

        DebugLogger::logEvent('[ORDER_STATUS_UPDATE]', "new internal status ID: $wcStatus");

        if ($wcStatus === 'completed') {
            $order->payment_complete();
        } elseif ($wcStatus === 'cancelled') {
            $order->update_status('cancelled');
        }
    }
}
