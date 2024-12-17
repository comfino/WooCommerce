<?php

if (!defined('ABSPATH')) {
    exit;
}

/** @var array $frontend_elements */
?>
<!DOCTYPE html>
<html>
    <head>
        <title><?php echo esc_html__('Comfino - installment and deferred on-line payments', 'comfino-payment-gateway'); ?></title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <?php do_action('embed_head'); ?>
    </head>
    <body>
        <div id="paywall-container"></div>
        <script data-cmp-ab="2">ComfinoPaywall.init('targetOrigin', document.location.href, document.getElementById('paywall-container'), <?php echo wp_json_encode($frontend_elements); ?>);</script>
        <?php do_action('embed_footer'); ?>
    </body>
</html>
