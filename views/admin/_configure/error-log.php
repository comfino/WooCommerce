<?php
/**
 * Error log template
 *
 * Displays error log contents with clear functionality.
 *
 * @var string $comfino_errors_log Error log contents
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<tr valign="top">
    <th scope="row" class="titledesc">
        <label for="errors-log"><?php echo esc_html__('Errors log', 'comfino-payment-gateway') ?></label>
    </th>
    <td>
        <textarea id="errors-log" rows="20" cols="60" readonly class="input-text wide-input" style="width: 800px; height: 400px"><?php echo esc_textarea($comfino_errors_log) ?></textarea>
        <p>
            <button type="button" class="button button-secondary" onclick="if (confirm('<?php echo esc_js(__('Are you sure you want to clear the error log?', 'comfino-payment-gateway')) ?>')) { comfinoSubmitAction('comfino_clear_error_log', '<?php echo esc_js(wp_create_nonce('comfino_settings')) ?>'); }">
                <?php echo esc_html__('Clear error log', 'comfino-payment-gateway') ?>
            </button>
        </p>
    </td>
</tr>
