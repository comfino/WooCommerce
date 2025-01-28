<?php

if (!defined('ABSPATH')) {
    exit;
}

/** @var string $language */
/** @var array $styles */
/** @var string $shop_url */
/** @var string $paywall_hash */
/** @var array $frontend_elements */
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($language); ?>">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo esc_html__('Comfino - installment and deferred on-line payments', 'comfino-payment-gateway'); ?></title>
        <?php wp_print_styles($styles); ?>
        <?php wp_print_head_scripts(); ?>
    </head>
    <body>
        <div id="paywall-container"></div>
        <?php wp_print_footer_scripts(); ?>
        <script data-cmp-ab="2">ComfinoPaywall.init('<?php echo esc_js(esc_url_raw($shop_url)); ?>', document.location.href, '<?php echo esc_js($paywall_hash); ?>', document.getElementById('paywall-container'), <?php echo wp_json_encode($frontend_elements); ?>);</script>
    </body>
</html>
