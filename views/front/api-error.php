<?php

if (!defined('ABSPATH')) {
    exit;
}

/** @var string $language */
/** @var string $title */
/** @var array $styles */
/** @var array $error_details */
/** @var bool $full_document_structure */
?>
<?php if ($full_document_structure): ?>
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
<?php endif; ?>
        <div id="paywall-container"></div>
        <script data-cmp-ab="2">ComfinoPaywall<?php if (!$full_document_structure): ?>Frontend<?php endif; ?>.processError(<?php echo wp_json_encode($error_details); ?>);</script>
<?php if ($full_document_structure): ?>
    </body>
</html>
<?php endif; ?>
