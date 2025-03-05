<?php

if (!defined('ABSPATH')) {
    exit;
}

/** @var string $language */
/** @var string $title */
/** @var array $styles */
/** @var array $error_details */
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($language); ?>">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo esc_html($title); ?></title>
        <?php wp_print_styles($styles); ?>
        <?php wp_print_head_scripts(); ?>
    </head>
    <body>
        <div id="paywall-error-container" class="paywall-error-container"></div>
        <?php wp_print_footer_scripts(); ?>
        <script data-cmp-ab="2">ComfinoPaywall.processError(document.getElementById('paywall-error-container'), <?php echo wp_json_encode($error_details); ?>);</script>
    </body>
</html>
