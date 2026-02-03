<?php
/**
 * Template for plugin configuration panel.
 *
 * Displays the main configuration interface with tabbed navigation for
 * payment settings, sale settings, widget settings, abandoned cart settings,
 * developer settings, and plugin diagnostics.
 *
 * @var WP $wp WordPress global
 * @var string $title Page title
 * @var string $description Page description
 * @var string $plugin_version Plugin version number
 * @var string $contact_msg1 Contact message part 1
 * @var string $contact_msg2 Contact message part 2
 * @var string $support_email_address Support email address
 * @var string $support_email_subject Support email subject
 * @var string $support_email_body Support email body
 * @var string $active_tab Currently active tab slug
 * @var string $settings_html Generated settings HTML for current tab
 * @var array $settings_allowed_html Allowed HTML tags for settings
 * @var string $shop_info Shop environment information (diagnostics tab)
 * @var string $errors_log Error log contents (diagnostics tab)
 * @var string $debug_log Debug log contents (diagnostics tab)
 * @var string $api_host Comfino API host URL (diagnostics tab)
 * @var string $shop_domain Shop domain name (diagnostics tab)
 * @var string $widget_key Widget key (diagnostics tab)
 * @var string $new_widget_status New widget API status (diagnostics tab)
 * @var bool $is_dev_env Development environment flag (diagnostics tab)
 * @var string $build_ts Plugin build timestamp (diagnostics tab)
 * @var string|null $github_version Latest GitHub version or null (diagnostics tab)
 * @var int|null $github_version_checked_at Timestamp of last version check (diagnostics tab)
 * @var bool $auto_updates_enabled WordPress auto-updates enabled flag (diagnostics tab)
 * @var string $comfino_logo_img Comfino logo HTML
 * @var array $comfino_logo_allowed_html Allowed HTML tags for logo
 * @var string $cache_root_path Cache root directory path (diagnostics tab)
 * @var string $cache_path Cache directory path (diagnostics tab)
 */

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
                        <b>Latest available version:</b>
                        <?php if ($auto_updates_enabled): ?>
                            <span style="color: #888;">Managed by WordPress auto-updates</span>
                        <?php elseif ($github_version !== null): ?>
                            <b style="<?php echo version_compare($github_version, $plugin_version, '>') ? 'color: orange;' : 'color: green;'; ?>"><?php echo esc_html($github_version); ?></b>
                            <?php if (version_compare($github_version, $plugin_version, '>')): ?>
                                (<a href="https://github.com/comfino/WooCommerce/releases" target="_blank">Download from GitHub</a>)
                            <?php else: ?>
                                (up to date)
                            <?php endif; ?>
                            <?php if ($github_version_checked_at): ?>
                                <small style="color: #666;"><?php esc_html(sprintf('Last checked: %s UTC', gmdate('Y-m-d H:i:s', $github_version_checked_at))); ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #888;">'Checking...</span>
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

                        $comfino_internal_flags = '<li><b>comfino_plugin_current_version</b>: ' . get_option('comfino_plugin_current_version') . '</li>';
                        $comfino_internal_flags .= '<li><b>comfino_plugin_updated</b>: ' . get_transient('comfino_plugin_updated') . '</li>';
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
            <?php
            // Plugin reset section
            include __DIR__ . '/_configure/module-reset.php';

            // Error log section
            $comfino_errors_log = $errors_log;

            include __DIR__ . '/_configure/error-log.php';

            // Debug log section
            $comfino_debug_log = $debug_log;

            include __DIR__ . '/_configure/debug-log.php';

            // Installation logs section
            include __DIR__ . '/_configure/installation-logs.php';
            break;
    }
    ?>
</table>
<script>
function comfinoSubmitAction(action, nonce)
{
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?php echo esc_url(admin_url('admin-post.php')); ?>';

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = action;
    form.appendChild(actionInput);

    const nonceInput = document.createElement('input');
    nonceInput.type = 'hidden';
    nonceInput.name = 'comfino_nonce';
    nonceInput.value = nonce;
    form.appendChild(nonceInput);

    const refererInput = document.createElement('input');
    refererInput.type = 'hidden';
    refererInput.name = '_wp_http_referer';
    refererInput.value = window.location.href;
    form.appendChild(refererInput);

    document.body.appendChild(form);

    form.submit();
}
</script>
<?php wp_nonce_field('comfino_settings', 'comfino_nonce', false); ?>
