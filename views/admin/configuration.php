<?php

use Comfino\Main;

if (!defined('ABSPATH')) {
    exit;
}

function prepare_tab_url(string $subsection): string
{
    $urlParts = wp_parse_url(Main::getCurrentUrl());
    $queryArgs = [];

    parse_str($urlParts['query'], $queryArgs);
    $queryArgs['subsection'] = $subsection;

    return $urlParts['path'] . '?' . http_build_query(array_map('strip_tags', $queryArgs));
}

/** @var WP $wp */
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
/** @var string $debug_log */
/** @var string $api_host */
/** @var string $shop_domain */
/** @var string $widget_key */
/** @var string $is_dev_env */
/** @var string $build_ts */
?>
<h2><?php echo esc_html($title); ?></h2>
<p><?php echo esc_html($description); ?></p>
<img style="width: 300px" src="<?php echo esc_url($logo_url); ?>" alt="Comfino logo"> <span style="font-weight: bold; font-size: 16px; vertical-align: bottom"><?php echo esc_html($plugin_version); ?></span>
<p>
    <?php echo esc_html($contact_msg1); ?>
    <a href="mailto:<?php echo esc_html($support_email_address); ?>?subject=<?php echo esc_html($support_email_subject); ?>&body=<?php echo esc_html($support_email_body); ?>">
        <?php echo esc_html($support_email_address); ?>
    </a>
    <?php echo esc_html($contact_msg2); ?>
</p>
<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
    <a href="<?php echo esc_attr(prepare_tab_url('payment_settings')); ?>" class="nav-tab<?php echo $active_tab === 'payment_settings' ? ' nav-tab-active' : ''; ?>"><?php echo esc_html__('Payment settings', 'comfino-payment-gateway'); ?></a>
    <a href="<?php echo esc_attr(prepare_tab_url('sale_settings')); ?>" class="nav-tab<?php echo $active_tab === 'sale_settings' ? ' nav-tab-active' : ''; ?>"><?php echo esc_html__('Sale settings', 'comfino-payment-gateway'); ?></a>
    <a href="<?php echo esc_attr(prepare_tab_url('widget_settings')); ?>" class="nav-tab<?php echo $active_tab === 'widget_settings' ? ' nav-tab-active' : ''; ?>"><?php echo esc_html__('Widget settings', 'comfino-payment-gateway'); ?></a>
    <a href="<?php echo esc_attr(prepare_tab_url('abandoned_cart_settings')); ?>" class="nav-tab<?php echo $active_tab === 'abandoned_cart_settings' ? ' nav-tab-active' : ''; ?>"><?php echo esc_html__('Abandoned cart settings', 'comfino-payment-gateway'); ?></a>
    <a href="<?php echo esc_attr(prepare_tab_url('developer_settings')); ?>" class="nav-tab<?php echo $active_tab === 'developer_settings' ? ' nav-tab-active' : ''; ?>"><?php echo esc_html__('Developer settings', 'comfino-payment-gateway'); ?></a>
    <a href="<?php echo esc_attr(prepare_tab_url('plugin_diagnostics')); ?>" class="nav-tab<?php echo $active_tab === 'plugin_diagnostics' ? ' nav-tab-active' : ''; ?>"><?php echo esc_html__('Plugin diagnostics', 'comfino-payment-gateway'); ?></a>
</nav>
<table class="form-table">
    <?php
    switch ($active_tab) {
        case 'payment_settings':
        case 'sale_settings':
        case 'widget_settings':
        case 'abandoned_cart_settings':
        case 'developer_settings':
            echo wp_kses($settings_html, 'post');
            break;

        case 'plugin_diagnostics':
            ?>
            <tr valign="top"><th scope="row" class="titledesc"></th><td><?php echo esc_html($shop_info); ?></td></tr>
            <tr valign="top">
                <th scope="row" class="titledesc"></th>
                <td>
                    <hr>
                    <p><b>Comfino API host:</b> <?php echo esc_html($api_host); ?></p>
                    <p><b>Plugin build time:</b> <?php echo esc_html($build_ts); ?></p>
                    <p><b>Shop domain:</b> <?php echo esc_html($shop_domain); ?></p>
                    <p><b>Widget key:</b> <?php echo esc_html($widget_key); ?></p>
                    <?php
                    if (!empty(getenv('COMFINO_DEBUG')) || !empty(getenv('COMFINO_DEV'))) {
                        $devEnvVariables = ['DEBUG', 'DEV', 'DEV_API_HOST', 'DEV_WIDGET_SCRIPT_URL'];
                        ?>
                        <p><b>Plugin dev-debug mode:</b> <?php echo esc_html($is_dev_env); ?></p>
                        <?php
                        echo wp_kses(
                            sprintf(
                                '<hr><h4>Development environment variables:</h4><ul>%s</ul>',
                                implode('', array_map(
                                    static function (string $envVariable): string {
                                        $varName = "COMFINO_$envVariable";
                                        return "<li><b>$varName</b> = \"" . getenv($varName) . '"</li>';
                                    },
                                    $devEnvVariables
                                ))
                            ),
                            ['hr' => [], 'h4' => [], 'ul' => [], 'li' => [], 'b' => []]
                        );
                    }
                    ?>
                </td>
            </tr>
            <tr valign="top"><th scope="row" class="titledesc"><label for="errors-log"><?php echo esc_html__('Errors log', 'comfino-payment-gateway'); ?></label></th>
                <td><textarea id="errors-log" rows="20" cols="60" readonly class="input-text wide-input" style="width: 800px; height: 400px"><?php echo esc_textarea($errors_log); ?></textarea></td></tr>
            <tr valign="top"><th scope="row" class="titledesc"><label for="debug-log"><?php echo esc_html__('Debug log', 'comfino-payment-gateway'); ?></label></th>
                <td><textarea id="debug-log" rows="40" cols="60" readonly class="input-text wide-input" style="width: 800px; height: 400px"><?php echo esc_textarea($debug_log); ?></textarea></td></tr>
            <?php
            break;
    }
    ?>
</table>
