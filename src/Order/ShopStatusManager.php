<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Comfino\Order;

use Comfino\Api\ApiClient;
use Comfino\Common\Shop\Order\StatusManager;
use Comfino\ErrorLogger;
use Comfino\Main;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class ShopStatusManager
{
    public const DEFAULT_STATUS_MAP = [
        StatusManager::STATUS_ACCEPTED => 'PS_OS_WS_PAYMENT',
        StatusManager::STATUS_CANCELLED => 'PS_OS_CANCELED',
        StatusManager::STATUS_REJECTED => 'PS_OS_CANCELED',
    ];

    private const CUSTOM_ORDER_STATUSES = [
        'COMFINO_' . StatusManager::STATUS_CREATED => [
            'name' => 'Order created - waiting for payment (Comfino)',
            'name_pl' => 'Zamówienie utworzone - oczekiwanie na płatność (Comfino)',
            'color' => '#87b921',
            'paid' => false,
            'deleted' => false,
        ],
        'COMFINO_' . StatusManager::STATUS_ACCEPTED => [
            'name' => 'Credit granted (Comfino)',
            'name_pl' => 'Kredyt udzielony (Comfino)',
            'color' => '#227b34',
            'paid' => true,
            'deleted' => false,
        ],
        'COMFINO_' . StatusManager::STATUS_REJECTED => [
            'name' => 'Credit rejected (Comfino)',
            'name_pl' => 'Wniosek kredytowy odrzucony (Comfino)',
            'color' => '#ba3f1d',
            'paid' => false,
            'deleted' => false,
        ],
        'COMFINO_' . StatusManager::STATUS_CANCELLED => [
            'name' => 'Cancelled (Comfino)',
            'name_pl' => 'Anulowano (Comfino)',
            'color' => '#ba3f1d',
            'paid' => false,
            'deleted' => false,
        ],
    ];

    public static function addCustomOrderStatuses(): void
    {
        $languages = \Language::getLanguages(false);

        foreach (self::CUSTOM_ORDER_STATUSES as $statusCode => $statusDetails) {
            $comfinoStatusId = \Configuration::get($statusCode);

            if (!empty($comfinoStatusId) && \Validate::isInt($comfinoStatusId)) {
                $orderStatus = new \OrderState($comfinoStatusId);

                if (\Validate::isLoadedObject($orderStatus)) {
                    // Update existing status definition.
                    $orderStatus->color = $statusDetails['color'];
                    $orderStatus->paid = $statusDetails['paid'];
                    $orderStatus->deleted = $statusDetails['deleted'];

                    $orderStatus->update();

                    continue;
                }
            } elseif ($statusDetails['deleted']) {
                // Ignore deleted statuses in first time plugin installations.
                continue;
            }

            // Add a new status definition.
            $orderStatus = new \OrderState();
            $orderStatus->send_email = false;
            $orderStatus->invoice = false;
            $orderStatus->color = $statusDetails['color'];
            $orderStatus->unremovable = false;
            $orderStatus->logable = false;
            $orderStatus->module_name = 'comfino';
            $orderStatus->paid = $statusDetails['paid'];

            foreach ($languages as $language) {
                $status_name = $language['iso_code'] === 'pl' ? $statusDetails['name_pl'] : $statusDetails['name'];
                $orderStatus->name[$language['id_lang']] = $status_name;
            }

            if ($orderStatus->add()) {
                \Configuration::updateValue($statusCode, $orderStatus->id);
            }
        }
    }

    public static function updateOrderStatuses(): void
    {
        $languages = \Language::getLanguages(false);

        foreach (self::CUSTOM_ORDER_STATUSES as $statusCode => $statusDetails) {
            $comfinoStatusId = \Configuration::get($statusCode);

            if (!empty($comfinoStatusId) && \Validate::isInt($comfinoStatusId)) {
                $orderStatus = new \OrderState($comfinoStatusId);

                if (\Validate::isLoadedObject($orderStatus)) {
                    // Update existing status definition.
                    foreach ($languages as $language) {
                        if ($language['iso_code'] === 'pl') {
                            $orderStatus->name[$language['id_lang']] = $statusDetails['name_pl'];
                        } else {
                            $orderStatus->name[$language['id_lang']] = $statusDetails['name'];
                        }
                    }

                    $orderStatus->color = $statusDetails['color'];
                    $orderStatus->paid = $statusDetails['paid'];
                    $orderStatus->deleted = $statusDetails['deleted'];

                    $orderStatus->save();
                }
            }
        }
    }

    public static function orderStatusUpdateEventHandler(\PaymentModule $module, array $params): void
    {
        $order = new \Order($params['id_order']);

        if (stripos($order->payment, 'comfino') !== false) {
            // Process orders paid by Comfino only.

            /** @var \OrderState $newOrderState */
            $newOrderState = $params['newOrderStatus'];

            $newOrderStateId = (int) $newOrderState->id;
            $canceledOrderStateId = (int) \Configuration::get('PS_OS_CANCELED');

            if ($newOrderStateId === $canceledOrderStateId) {
                // Send notification about cancelled order paid by Comfino.
                ErrorLogger::init(Main::getPluginDirectory());

                try {
                    ApiClient::getInstance()->cancelOrder($params['id_order']);
                } catch (\Throwable $e) {
                    ApiClient::processApiError(
                        'Order cancellation error on page "' . $_SERVER['REQUEST_URI'] . '" (Comfino API)', $e
                    );
                }
            }
        }
    }
}
