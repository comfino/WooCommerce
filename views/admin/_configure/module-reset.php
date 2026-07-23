<?php
/**
 * Template for the plugin reset action in the diagnostics tab.
 *
 * Renders an informational notice and a confirmation button that triggers
 * the comfino_plugin_reset action via comfinoSubmitAction(). The reset
 * repairs missing configuration options and clears all module caches
 * without deleting existing configuration data.
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<tr valign="top">
    <th scope="row" class="titledesc">
        <label><?php echo esc_html__('Plugin reset', 'comfino-payment-gateway') ?></label>
    </th>
    <td>
        <div class="notice notice-info inline" style="margin: 0 0 15px 0; padding: 10px;">
            <p><strong><?php echo esc_html__('Plugin reset', 'comfino-payment-gateway') ?></strong></p>
            <p><?php echo esc_html__('This operation will', 'comfino-payment-gateway') ?>:</p>
            <ul style="margin-top: 10px; margin-left: 20px; list-style: disc;">
                <li><?php echo esc_html__('Add missing configuration options (preserves existing values).', 'comfino-payment-gateway') ?></li>
                <li><?php echo esc_html__('Clear module cache.', 'comfino-payment-gateway') ?></li>
            </ul>
            <p style="margin-top: 10px;"><em><?php echo esc_html__('Note: This operation does NOT delete any existing configuration or data.', 'comfino-payment-gateway') ?></em></p>
        </div>
        <p>
            <button type="button" class="button button-secondary" onclick="if (confirm('<?php echo esc_js(__('Are you sure you want to reset the plugin? This will repair missing options and clear all caches.', 'comfino-payment-gateway')) ?>')) { comfinoSubmitAction('comfino_plugin_reset', '<?php echo esc_js(wp_create_nonce('comfino_settings')) ?>'); }">
                <?php echo esc_html__('Reset plugin', 'comfino-payment-gateway') ?>
            </button>
        </p>
    </td>
</tr>
