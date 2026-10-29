<?php

namespace Comfino\Order;

use Comfino\Api\ApiClient;
use Comfino\Common\Shop\Order\StatusApplicationContext;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Configuration\ConfigManager;
use Comfino\DebugLogger;
use Comfino\Extended\Api\Dto\Plugin\OperationContext;
use Comfino\Main;
use Comfino\PaymentGateway;
use Comfino\View\TemplateManager;

if (!defined('ABSPATH')) {
    exit;
}

final class ShopStatusManager
{
    public const DEFAULT_STATUS_MAP = [
        StatusManager::STATUS_ACCEPTED => 'completed',
        StatusManager::STATUS_CANCELLED => 'cancelled',
        StatusManager::STATUS_REJECTED => 'cancelled',
        StatusManager::STATUS_CANCELLED_BY_SHOP => 'cancelled',
    ];

    /** ComfinoPay statuses after which ComfinoPay already treats the order as closed. */
    public const TERMINAL_STATUSES = [
        StatusManager::STATUS_CANCELLED,
        StatusManager::STATUS_CANCELLED_BY_SHOP,
        StatusManager::STATUS_REJECTED,
        StatusManager::STATUS_RESIGN,
    ];

    /** Order meta holding the terminal ComfinoPay status applied by a status notification. */
    public const TERMINAL_STATUS_META_KEY = '_comfino_terminal_status';

    /** Cancel suppressed because the order meta holds a terminal ComfinoPay status. */
    public const CANCEL_SUPPRESSED_BY_META = 'meta';
    /** Cancel suppressed because a status note of a pre-4.4.0 order holds a terminal ComfinoPay status. */
    public const CANCEL_SUPPRESSED_BY_NOTE = 'note';
    /** Cancel suppressed because the status change is being applied from a ComfinoPay status notification. */
    public const CANCEL_SUPPRESSED_BY_CONTEXT = 'context';

    /**
     * Tells whether a cancellation of the order must not be sent to the ComfinoPay API, and why.
     *
     * The order checks run first: they need nothing from the webhook path, so they also work across requests (an admin
     * cancelling an order ComfinoPay rejected earlier). Orders created before 4.4.0 have no terminal status meta and
     * fall back to the status notes.
     *
     * @return string|null One of the CANCEL_SUPPRESSED_BY_* constants, or null when the cancellation must be sent
     */
    public static function getCancelSuppressionReason(\WC_Order $order): ?string
    {
        if (in_array($order->get_meta(self::TERMINAL_STATUS_META_KEY), self::TERMINAL_STATUSES, true)) {
            return self::CANCEL_SUPPRESSED_BY_META;
        }

        if (count(OrderManager::getOrderStatusNotes($order->get_id(), self::TERMINAL_STATUSES)) > 0) {
            return self::CANCEL_SUPPRESSED_BY_NOTE;
        }

        /* Any status applied from a ComfinoPay notification runs inside the shared application context, not only the
           cancellation statuses, so a status map entry pointing another status at "cancelled" is covered too. */
        if (StatusApplicationContext::isActive()) {
            return self::CANCEL_SUPPRESSED_BY_CONTEXT;
        }

        return null;
    }

    public static function orderStatusUpdateEventHandler(\WC_Order $order, string $oldStatus, string $newStatus): void
    {
        if (!ConfigManager::isEnabled()) {
            return;
        }

        DebugLogger::logEvent(
            'orderStatusUpdateEventHandler',
            'Order status changed.',
            ['$oldStatus' => $oldStatus, '$newStatus' => $newStatus, 'payment_method' => $order->get_payment_method()]
        );

        switch ($newStatus) {
            case 'failed':
                if (ConfigManager::isAbandonedCartEnabled() &&
                    $order->get_payment_method() !== PaymentGateway::GATEWAY_ID &&
                    in_array($oldStatus, ['on-hold', 'pending'], true)
                ) {
                    // Send e-mail and API notifications about abandoned cart not paid by ComfinoPay.
                    self::sendEmail($order);
                    ApiClient::getInstance()->notifyAbandonedCart('send-mail');
                }

                break;

            case 'cancelled':
                if ($order->get_payment_method() === PaymentGateway::GATEWAY_ID) {
                    // Process orders paid by ComfinoPay only.
                    if (($suppressionReason = self::getCancelSuppressionReason($order)) !== null) {
                        // ComfinoPay already knows the order is closed - do not send the cancellation back.
                        DebugLogger::logEvent(
                            '[ORDER_STATUS_UPDATE]',
                            'Order cancellation not sent to ComfinoPay API.',
                            ['orderId' => $order->get_id(), 'newStatus' => $newStatus, 'guard' => $suppressionReason]
                        );

                        break;
                    }

                    // Get order ID or reference based on configuration.
                    if (ConfigManager::isOrderReferenceEnabled()) {
                        $orderId = $order->get_order_number();
                    } else {
                        $orderId = (string) $order->get_id();
                    }

                    try {
                        // Send notification about canceled order paid by ComfinoPay.
                        ApiClient::getInstance()->cancelOrder($orderId);

                        $order->add_order_note(__('Order cancellation sent to ComfinoPay.', 'comfino-payment-gateway'));
                    } catch (\Throwable $e) {
                        ApiClient::processApiError('Order cancellation error on page "' . Main::getCurrentUrl() . '" (ComfinoPay API)', $e, OperationContext::OrderCancellation);

                        $order->add_order_note(__('Order cancellation could not be sent to ComfinoPay.', 'comfino-payment-gateway'));
                    }
                }

                break;
        }
    }

    private static function sendEmail(\WC_Order $order): void
    {
        $recipient = $order->get_billing_email();

        $headers = "Content-Type: text/html\r\n";
        $subject = __('Order reminder', 'comfino-payment-gateway');
        $contents = TemplateManager::renderView(
            'failed-order',
            'emails',
            [
                'order' => $order,
                'email_heading' => false,
                'sent_to_admin' => false,
                'plain_text' => false,
                'email' => $recipient,
                'additional_content' => false,
            ]
        );

        (WC()->mailer())->send($recipient, $subject, $contents, $headers);
    }
}
