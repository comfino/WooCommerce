<?php

namespace Comfino\Order;

use Comfino\Api\ApiClient;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Configuration\ConfigManager;
use Comfino\DebugLogger;
use Comfino\Main;
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
    ];

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
                if (ConfigManager::isAbandonedCartEnabled() && $order->get_payment_method() !== 'comfino' && in_array($oldStatus, ['on-hold', 'pending'], true)) {
                    // Send e-mail and API notifications about abandoned cart not paid by Comfino.
                    self::sendEmail($order);
                    ApiClient::getInstance()->notifyAbandonedCart('send-mail');
                }

                break;

            case 'cancelled':
                if ($order->get_payment_method() === 'comfino') {
                    // Process orders paid by Comfino only.

                    if (count(OrderManager::getOrderStatusNotes($order->get_id(), [StatusManager::STATUS_CANCELLED_BY_SHOP, StatusManager::STATUS_RESIGN])) > 0) {
                        break;
                    }

                    try {
                        // Send notification about cancelled order paid by Comfino.
                        ApiClient::getInstance()->cancelOrder((string) $order->get_id());
                    } catch (\Throwable $e) {
                        ApiClient::processApiError('Order cancellation error on page "' . Main::getCurrentUrl() . '" (Comfino API)', $e);
                    }

                    $order->add_order_note(__('Order cancellation sent to Comfino.', 'comfino-payment-gateway'));
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
