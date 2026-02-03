<?php
/**
 * Debug log template
 *
 * Displays debug log contents with clear functionality.
 *
 * @var string $comfino_debug_log Debug log contents
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<tr valign="top">
    <th scope="row" class="titledesc">
        <label for="debug-log"><?php echo esc_html__('Debug log', 'comfino-payment-gateway'); ?></label>
    </th>
    <td>
        <textarea id="debug-log" rows="40" cols="60" readonly class="input-text wide-input" style="width: 800px; height: 400px"><?php echo esc_textarea($comfino_debug_log); ?></textarea>
        <p>
            <button type="button" class="button button-secondary" onclick="if (confirm('<?php echo esc_js(__('Are you sure you want to clear the debug log?', 'comfino-payment-gateway')); ?>')) { comfinoSubmitAction('comfino_clear_debug_log', '<?php echo esc_js(wp_create_nonce('comfino_settings')); ?>'); }">
                <?php echo esc_html__('Clear debug log', 'comfino-payment-gateway'); ?>
            </button>
        </p>
    </td>
</tr>
