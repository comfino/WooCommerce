<?php

if (!defined('ABSPATH')) {
    exit;
}

/** @var string $title */
/** @var string $description */
/** @var string $logo_url */
/** @var string $plugin_version */
/** @var string $contact_msg1 */
/** @var string $contact_msg2 */
/** @var string $support_email_address */
/** @var string $support_email_subject */
/** @var string $support_email_body */
/** @var string $active_tab */
/** @var string $settings_html */
/** @var string $shop_info */
/** @var string $errors_log */
?>
<h2><?php echo esc_html($title); ?></h2>
<p><?php echo esc_html($description); ?></p>
<img style="width: 300px" src="<?php echo esc_url($logo_url); ?>" alt="Comfino logo"> <span style="font-weight: bold; font-size: 16px; vertical-align: bottom"><?php echo esc_html($plugin_version); ?></span>';
<p>
    <?php echo esc_html($contact_msg1); ?>
    <a href="mailto:<?php echo esc_html($support_email_address); ?>?subject=<?php echo esc_html($support_email_subject); ?>&body=<?php echo esc_html($support_email_body); ?>">
        <?php echo esc_html($support_email_address); ?>
    </a>
    <?php echo esc_html($contact_msg2); ?>
</p>
<nav class="nav-tab-wrapper woo-nav-tab-wrapper">';
<a href="<?php echo site_url(add_query_arg($wp->request, ['subsection' => 'payment_settings'])); ?>" class="nav-tab<?php echo $active_tab === 'payment_settings' ? ' nav-tab-active' : ''; ?>"><?php echo esc_html__('Payment settings', 'comfino-payment-gateway'); ?></a>';
<a href="<?php echo site_url(add_query_arg($wp->request, ['subsection' => 'sale_settings'])); ?>" class="nav-tab<?php echo $active_tab === 'sale_settings' ? ' nav-tab-active' : ''; ?>"><?php echo esc_html__('Sale settings', 'comfino-payment-gateway'); ?></a>';
<a href="<?php echo site_url(add_query_arg($wp->request, ['subsection' => 'widget_settings'])); ?>" class="nav-tab<?php echo $active_tab === 'widget_settings' ? ' nav-tab-active' : ''; ?>"><?php echo esc_html__('Widget settings', 'comfino-payment-gateway'); ?></a>';
<a href="<?php echo site_url(add_query_arg($wp->request, ['subsection' => 'abandoned_cart_settings'])); ?>" class="nav-tab<?php echo $active_tab === 'abandoned_cart_settings' ? ' nav-tab-active' : ''; ?>"><?php echo esc_html__('Abandoned cart settings', 'comfino-payment-gateway'); ?></a>';
<a href="<?php echo site_url(add_query_arg($wp->request, ['subsection' => 'developer_settings'])); ?>" class="nav-tab<?php echo $active_tab === 'developer_settings' ? ' nav-tab-active' : ''; ?>"><?php echo esc_html__('Developer settings', 'comfino-payment-gateway'); ?></a>';
<a href="<?php echo site_url(add_query_arg($wp->request, ['subsection' => 'plugin_diagnostics'])); ?>" class="nav-tab<?php echo $active_tab === 'plugin_diagnostics' ? ' nav-tab-active' : ''; ?>"><?php echo esc_html__('Plugin diagnostics', 'comfino-payment-gateway'); ?></a>';
</nav>';
<table class="form-table">';
<?php
switch ($active_tab) {
    case 'payment_settings':
    case 'sale_settings':
    case 'widget_settings':
    case 'abandoned_cart_settings':
    case 'developer_settings':
        echo $settings_html;
        break;

    case 'plugin_diagnostics':
?>
        <tr valign="top"><th scope="row" class="titledesc"></th><td><?php echo esc_html($shop_info); ?></td></tr>
        <tr valign="top"><th scope="row" class="titledesc"><label for="errors-log"><?php echo esc_html__('Errors log', 'comfino-payment-gateway'); ?></label></th>
        <td><textarea id="errors-log" cols="20" rows="3" class="input-text wide-input" style="width: 800px; height: 400px"><?php echo esc_textarea($errors_log); ?></textarea></td></tr>
<?php
        break;
}
?>
</table>
