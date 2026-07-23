<?php
/**
 * Installation logs template
 *
 * Displays collapsible sections for installation, upgrade, and uninstallation logs.
 * Reads logs directly using Main static methods.
 */

use Comfino\Main;

if (!defined('ABSPATH')) {
    exit;
}

$comfino_install_log = Main::readInstallLog();
$comfino_upgrade_log = Main::readUpgradeLog();
$comfino_uninstall_log = Main::readUninstallLog();
?>
<tr valign="top">
    <th scope="row" class="titledesc">
        <label><?php echo esc_html__('Installation logs', 'comfino-payment-gateway') ?></label>
    </th>
    <td>
        <details style="margin-bottom: 20px;">
            <summary style="cursor: pointer; font-weight: bold; padding: 10px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 3px;"><?php echo esc_html__('Installation log', 'comfino-payment-gateway') ?></summary>
            <div style="margin-top: 10px; padding: 10px; background: #fff; border: 1px solid #c3c4c7;">
                <?php if (!empty($comfino_install_log)): ?>
                    <textarea rows="15" cols="60" readonly class="input-text wide-input" style="width: 100%; font-family: monospace; font-size: 12px;"><?php echo esc_textarea($comfino_install_log) ?></textarea>
                <?php else: ?>
                    <p style="color: #888;"><?php echo esc_html__('No installation log available.', 'comfino-payment-gateway') ?></p>
                <?php endif; ?>
            </div>
        </details>
        <details style="margin-bottom: 20px;">
            <summary style="cursor: pointer; font-weight: bold; padding: 10px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 3px;"><?php echo esc_html__('Upgrade log', 'comfino-payment-gateway') ?></summary>
            <div style="margin-top: 10px; padding: 10px; background: #fff; border: 1px solid #c3c4c7;">
                <?php if (!empty($comfino_upgrade_log)): ?>
                    <textarea rows="15" cols="60" readonly class="input-text wide-input" style="width: 100%; font-family: monospace; font-size: 12px;"><?php echo esc_textarea($comfino_upgrade_log) ?></textarea>
                <?php else: ?>
                    <p style="color: #888;"><?php echo esc_html__('No upgrade log available.', 'comfino-payment-gateway') ?></p>
                <?php endif; ?>
            </div>
        </details>
        <details style="margin-bottom: 20px;">
            <summary style="cursor: pointer; font-weight: bold; padding: 10px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 3px;"><?php echo esc_html__('Uninstallation log', 'comfino-payment-gateway') ?></summary>
            <div style="margin-top: 10px; padding: 10px; background: #fff; border: 1px solid #c3c4c7;">
                <?php if (!empty($comfino_uninstall_log)): ?>
                    <textarea rows="15" cols="60" readonly class="input-text wide-input" style="width: 100%; font-family: monospace; font-size: 12px;"><?php echo esc_textarea($comfino_uninstall_log) ?></textarea>
                <?php else: ?>
                    <p style="color: #888;"><?php echo esc_html__('No uninstallation log available.', 'comfino-payment-gateway') ?></p>
                <?php endif; ?>
            </div>
        </details>
    </td>
</tr>
