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
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('comfino_settings', 'comfino_nonce'); ?>
            <input type="hidden" name="action" value="comfino_clear_debug_log">
            <p>
                <button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to clear the debug log?', 'comfino-payment-gateway')); ?>');">
                    <?php echo esc_html__('Clear debug log', 'comfino-payment-gateway'); ?>
                </button>
            </p>
        </form>
    </td>
</tr>
