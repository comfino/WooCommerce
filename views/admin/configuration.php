<?php

use Comfino\Common\Backend\FileUtils;
use Comfino\Configuration\ConfigManager;
use Comfino\Main;

if (!defined('ABSPATH')) {
    exit;
}

function comfino_prepare_tab_url(string $subsection): string
{
    $urlParts = wp_parse_url(Main::getCurrentUrl());
    $queryArgs = [];

    parse_str($urlParts['query'], $queryArgs);
    unset($queryArgs['comfino_nonce']);

    $queryArgs['subsection'] = $subsection;

    return wp_nonce_url($urlParts['path'] . '?' . http_build_query(array_map('strip_tags', $queryArgs)), 'comfino_settings', 'comfino_nonce');
}

/** @var WP $wp */
/** @var string $title */
/** @var string $description */
/** @var string $plugin_version */
/** @var string $contact_msg1 */
/** @var string $contact_msg2 */
/** @var string $support_email_address */
/** @var string $support_email_subject */
/** @var string $support_email_body */
/** @var string $active_tab */
/** @var string $settings_html */
/** @var array $settings_allowed_html */
/** @var string $shop_info */
/** @var string $errors_log */
/** @var string $debug_log */
/** @var string $api_host */
/** @var string $shop_domain */
/** @var string $widget_key */
/** @var string $new_widget_status */
/** @var bool $is_dev_env */
/** @var string $build_ts */
/** @var string|null $github_version */
/** @var int|null $github_version_checked_at */
/** @var bool $auto_updates_enabled */
/** @var string $comfino_logo_img */
/** @var array $comfino_logo_allowed_html */
/** @var string $cache_root_path */
/** @var string $cache_path */
?>
<h2><?php echo esc_html($title); ?></h2>
<p><?php echo esc_html($description); ?></p>
<?php echo wp_kses($comfino_logo_img, $comfino_logo_allowed_html); ?> <span style="font-weight: bold; font-size: 16px; vertical-align: bottom"><?php echo esc_html($plugin_version); ?></span>
<p>
    <?php echo esc_html($contact_msg1); ?>
    <a href="mailto:<?php echo esc_html($support_email_address); ?>?subject=<?php echo esc_html($support_email_subject); ?>&body=<?php echo esc_html($support_email_body); ?>">
        <?php echo esc_html($support_email_address); ?>
    </a>
    <?php echo esc_html($contact_msg2); ?>
</p>
<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
    <a href="<?php echo esc_attr(comfino_prepare_tab_url('payment_settings')); ?>" class="nav-tab<?php echo $active_tab === 'payment_settings' ? ' nav-tab-active' : ''; ?>"><?php echo esc_html__('Payment settings', 'comfino-payment-gateway'); ?></a>
    <a href="<?php echo esc_attr(comfino_prepare_tab_url('sale_settings')); ?>" class="nav-tab<?php echo $active_tab === 'sale_settings' ? ' nav-tab-active' : ''; ?>"><?php echo esc_html__('Sale settings', 'comfino-payment-gateway'); ?></a>
    <a href="<?php echo esc_attr(comfino_prepare_tab_url('widget_settings')); ?>" class="nav-tab<?php echo $active_tab === 'widget_settings' ? ' nav-tab-active' : ''; ?>"><?php echo esc_html__('Widget settings', 'comfino-payment-gateway'); ?></a>
    <a href="<?php echo esc_attr(comfino_prepare_tab_url('abandoned_cart_settings')); ?>" class="nav-tab<?php echo $active_tab === 'abandoned_cart_settings' ? ' nav-tab-active' : ''; ?>"><?php echo esc_html__('Abandoned cart settings', 'comfino-payment-gateway'); ?></a>
    <a href="<?php echo esc_attr(comfino_prepare_tab_url('developer_settings')); ?>" class="nav-tab<?php echo $active_tab === 'developer_settings' ? ' nav-tab-active' : ''; ?>"><?php echo esc_html__('Developer settings', 'comfino-payment-gateway'); ?></a>
    <a href="<?php echo esc_attr(comfino_prepare_tab_url('plugin_diagnostics')); ?>" class="nav-tab<?php echo $active_tab === 'plugin_diagnostics' ? ' nav-tab-active' : ''; ?>"><?php echo esc_html__('Plugin diagnostics', 'comfino-payment-gateway'); ?></a>
</nav>
<table class="form-table">
    <?php
    switch ($active_tab) {
        case 'payment_settings':
        case 'sale_settings':
        case 'widget_settings':
        case 'abandoned_cart_settings':
        case 'developer_settings':
            echo wp_kses($settings_html, $settings_allowed_html);
            break;

        case 'plugin_diagnostics':
            ?>
            <tr valign="top"><th scope="row" class="titledesc"></th><td><?php echo esc_html($shop_info); ?></td></tr>
            <tr valign="top">
                <th scope="row" class="titledesc"></th>
                <td>
                    <hr>
                    <p><b>Comfino API host:</b> <?php echo esc_html($api_host); ?></p>
                    <p><b>Plugin build time:</b> <?php echo esc_html($build_ts); ?> UTC</p>
                    <p><b>Shop domain:</b> <?php echo esc_html($shop_domain); ?></p>
                    <p><b>Widget key:</b> <?php echo esc_html($widget_key); ?></p>
                    <p><b>New widget API:</b> <?php echo esc_html($new_widget_status); ?></p>
                    <p>
                        <b><?php echo esc_html__('Latest available version:', 'comfino-payment-gateway'); ?></b>
                        <?php if ($auto_updates_enabled): ?>
                            <span style="color: #888;"><?php echo esc_html__('Managed by WordPress auto-updates', 'comfino-payment-gateway'); ?></span>
                        <?php elseif ($github_version !== null): ?>
                            <b style="<?php echo version_compare($github_version, $plugin_version, '>') ? 'color: orange;' : 'color: green;'; ?>"><?php echo esc_html($github_version); ?></b>
                            <?php if (version_compare($github_version, $plugin_version, '>')): ?>
                                (<a href="https://github.com/comfino/WooCommerce/releases" target="_blank"><?php echo esc_html__('Download from GitHub', 'comfino-payment-gateway'); ?></a>)
                            <?php else: ?>
                                (<?php echo esc_html__('up to date', 'comfino-payment-gateway'); ?>)
                            <?php endif; ?>
                            <?php if ($github_version_checked_at): ?>
                                <small style="color: #666;">
                                <?php
                                    /* translators: %s: Date and time (UTC) of last GitHub version check in Y-m-d H:i:s format */
                                    echo esc_html(sprintf(__('Last checked: %s UTC', 'comfino-payment-gateway'), gmdate('Y-m-d H:i:s', $github_version_checked_at)));
                                ?>
                                </small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #888;"><?php echo esc_html__('Checking...', 'comfino-payment-gateway'); ?></span>
                        <?php endif; ?>
                    </p>
                    <p>
                        <b>Cache root directory writable:</b> <?php if (FileUtils::isWritable($cache_root_path)): ?><b style="color: green">YES</b><?php else: ?><b style="color: red">NO</b><?php endif; ?>
                        <?php if (getenv('COMFINO_DEV_ENV') === 'TRUE'): ?>(<i><?php echo esc_html($cache_root_path); ?></i>)<?php endif; ?>
                    </p>
                    <p>
                        <b>Cache directory writable:</b> <?php if (FileUtils::isWritable($cache_path)): ?><b style="color: green">YES</b><?php else: ?><b style="color: red">NO</b><?php endif; ?>
                        <?php if (getenv('COMFINO_DEV_ENV') === 'TRUE'): ?>(<i><?php echo esc_html($cache_path); ?></i>)<?php endif; ?>
                    </p>
                    <?php
                    if (getenv('COMFINO_DEV_ENV') === 'TRUE') {
                        ?>
                        <p><b>Plugin dev-debug mode:</b> <?php if ($is_dev_env): ?><b style="color: green">YES</b><?php else: ?><b style="color: red">NO</b><?php endif; ?></p>
                        <?php
                        echo wp_kses(
                            sprintf(
                                '<hr><h4>Development environment variables:</h4><ul>%s</ul>',
                                implode('', array_map(
                                    static function (string $env_variable): string {
                                        $var_name = "COMFINO_$env_variable";
                                        return "<li><b>$var_name</b> = \"" . getenv($var_name) . '"</li>';
                                    },
                                    [
                                        'DEV_ENV', 'DEV_API_HOST', 'DEV_STATIC_RESOURCES_BASE_URL',
                                        'DEV_WIDGET_SCRIPT_URL', 'DEV_USE_UNMINIFIED_SCRIPTS',
                                    ]
                                ))
                            ),
                            ['hr' => [], 'h4' => [], 'ul' => [], 'li' => [], 'b' => []]
                        );

                        $comfino_internal_options = '';

                        foreach (ConfigManager::getConfigurationValues('hidden_settings') as $comfino_option_name => $comfino_option_value) {
                            if (is_array($comfino_option_value) || is_bool($comfino_option_value)) {
                                $comfino_option_value = wp_json_encode($comfino_option_value);
                            }

                            $comfino_internal_options .= "<li><b>$comfino_option_name</b> = \"$comfino_option_value\"</li>";
                        }

                        echo wp_kses(
                            "<hr><h4>Internal configuration options:</h4><ul>$comfino_internal_options</ul>",
                            ['hr' => [], 'h4' => [], 'ul' => [], 'li' => [], 'b' => []]
                        );

                        $comfino_internal_flags = '<li><b>comfino_plugin_updated</b>: ' . get_transient('comfino_plugin_updated') . '</li>';
                        $comfino_internal_flags .= '<li><b>comfino_plugin_prev_version</b>: ' . get_transient('comfino_plugin_prev_version') . '</li>';
                        $comfino_internal_flags .= '<li><b>comfino_plugin_updated_at</b>: ' . gmdate('Y-m-d H:i:s', get_transient('comfino_plugin_updated_at')) . ' UTC</li>';

                        echo wp_kses(
                            "<hr><h4>Internal flags:</h4><ul>$comfino_internal_flags</ul>",
                            ['hr' => [], 'h4' => [], 'ul' => [], 'li' => [], 'b' => []]
                        );
                    }
                    ?>
                </td>
            </tr>
            <tr valign="top"><th scope="row" class="titledesc"><label for="errors-log"><?php echo esc_html__('Errors log', 'comfino-payment-gateway'); ?></label></th>
                <td><textarea id="errors-log" rows="20" cols="60" readonly class="input-text wide-input" style="width: 800px; height: 400px"><?php echo esc_textarea($errors_log); ?></textarea></td>
            </tr>
            <tr valign="top"><th scope="row" class="titledesc"><label for="debug-log"><?php echo esc_html__('Debug log', 'comfino-payment-gateway'); ?></label></th>
                <td><textarea id="debug-log" rows="40" cols="60" readonly class="input-text wide-input" style="width: 800px; height: 400px"><?php echo esc_textarea($debug_log); ?></textarea></td>
            </tr>
            <?php
            break;
    }
    ?>
</table>
<?php wp_nonce_field('comfino_settings', 'comfino_nonce', false); ?>
