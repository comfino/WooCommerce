<?php
/**
 * Template for debug mode warning notice.
 *
 * Displayed to administrators when debug mode is enabled.
 * Warns about potential performance impact and production use.
 *
 * @var string $settings_url URL to plugin settings page
 * @var string $nonce_value Nonce for AJAX dismissal action
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="notice notice-warning is-dismissible" id="comfino-debug-mode-notice">
    <p>
        <strong><?php echo esc_html__('Comfino Debug Mode Active', 'comfino-payment-gateway') ?>:</strong>
        <?php echo esc_html__('Detailed logging is enabled. This may impact performance and should not be used in production environments.', 'comfino-payment-gateway') ?>
        <?php
        echo wp_kses(
            sprintf(
                /* translators: %s: Link to plugin settings */
                __('You can disable debug mode in the <a href="%s">plugin settings</a>.', 'comfino-payment-gateway'),
                esc_url($settings_url)
            ),
            ['a' => ['href' => []]]
        );
        ?>
    </p>
</div>
<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#comfino-debug-mode-notice').on('click', '.notice-dismiss', function() {
        $.post(ajaxurl, {
            action: 'comfino_dismiss_debug_notice',
            nonce: '<?php echo esc_js($nonce_value) ?>'
        });
    });
});
</script>
