<?php
/**
 * Template for PHP compatibility error notices.
 *
 * Used when the plugin is loaded in an incompatible PHP environment.
 * This template uses PHP 5.6+ compatible syntax only.
 *
 * @var string $current_php_version Current PHP version
 * @var string $required_php_version Required PHP version
 * @var string $plugin_version Current plugin version
 * @var bool $can_deactivate Whether current user can deactivate plugins
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="error">
    <p>
        <?php echo
        wp_kses(
            sprintf(
                /* translators: 1: Plugin version 2: Required PHP version 3: Current PHP version 4: Link to compatible version */
                __('<strong>Comfino Payment Gateway:</strong> Plugin version %1$s requires PHP %2$s or higher. You are running PHP %3$s. Please upgrade your PHP version or downgrade to %4$s (compatible with PHP 7.0+).', 'comfino-payment-gateway'),
                esc_html($plugin_version),
                esc_html($required_php_version),
                esc_html($current_php_version),
                '<a href="https://github.com/comfino/WooCommerce/releases/tag/3.4.1" target="_blank">Comfino Payment Gateway v3.4.1</a>'
            ),
            ['strong' => [], 'a' => ['href' => [], 'target' => []]]
        )
        ?>
    </p>
</div>
<?php if ($can_deactivate): ?>
    <div class="error">
        <p>
            <?php echo
            wp_kses(
                __('<strong>Comfino Payment Gateway:</strong> The plugin has been automatically deactivated to prevent fatal errors. Please resolve the PHP version issue before reactivating.', 'comfino-payment-gateway'),
                ['strong' => []]
            )
            ?>
        </p>
    </div>
<?php endif; ?>
