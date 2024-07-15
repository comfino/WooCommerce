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

use Comfino\Common\Shop\Order\StatusManager;
use Comfino\Common\Shop\OrderStatusAdapterInterface;
use Comfino\Configuration\ConfigManager;

if (!defined('_PS_VERSION_')) {
    exit;
}

class StatusAdapter implements OrderStatusAdapterInterface
{
    public function setStatus($orderId, $status): void
    {
        $order = new \Order($orderId);

        if (!\ValidateCore::isLoadedObject($order)) {
            throw new \RuntimeException(sprintf('Order not found by id: %s', $orderId));
        }

        $inputStatus = \Tools::strtoupper($status);

        if (in_array($inputStatus, StatusManager::STATUSES, true)) {
            $custom_status_new = "COMFINO_$inputStatus";
        } else {
            return;
        }

        $currentInternalStatusId = (int) $order->getCurrentState();
        $newCustomStatusId = (int) \Configuration::get($custom_status_new);

        if ($newCustomStatusId !== $currentInternalStatusId) {
            $order->setCurrentState($newCustomStatusId);

            $statusMap = ConfigManager::getStatusMap();

            if (!array_key_exists($inputStatus, $statusMap)) {
                return;
            }

            $newInternalStatusId = (int) \Configuration::get($statusMap[$inputStatus]);

            foreach ($order->getHistory(0) as $historyEntry) {
                if ($historyEntry['id_order_state'] === $newInternalStatusId) {
                    return;
                }
            }

            $order->setCurrentState($newInternalStatusId);
        }
    }
}
